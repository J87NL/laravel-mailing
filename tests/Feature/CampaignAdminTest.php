<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use J87NL\Mailing\Contracts\DnsResolver;
use J87NL\Mailing\Contracts\SpamScorer;
use J87NL\Mailing\Enums\CampaignStatus;
use J87NL\Mailing\Enums\SuppressionReason;
use J87NL\Mailing\Forms\ScheduleForm;
use J87NL\Mailing\Html\HtmlToText;
use J87NL\Mailing\MailingPermission;
use J87NL\Mailing\Models\Campaign;
use J87NL\Mailing\Models\Suppression;
use J87NL\Mailing\Preflight\PreflightCheck;
use J87NL\Mailing\Preflight\PreflightItem;
use J87NL\Mailing\Tests\Fakes\FakeDnsResolver;
use J87NL\Mailing\Tests\Fakes\FakeSpamScorer;

function healthyDns(): FakeDnsResolver
{
    $dns = app(DnsResolver::class);

    expect($dns)->toBeInstanceOf(FakeDnsResolver::class);

    $dns->txt = [
        'rommelmarkt.test' => ['v=spf1 mx ip4:192.0.2.10 -all'],
        'mail._domainkey.rommelmarkt.test' => ['v=DKIM1; k=rsa; p=MIIBIjANBg'],
        '_dmarc.rommelmarkt.test' => ['v=DMARC1; p=none; rua=mailto:dmarc@j87.nl'],
    ];
    $dns->a = [
        'rommelmarkt.test' => [
            '93.184.215.14',
        ],
    ];

    return $dns;
}

/**
 * @return array<string, string>
 */
function preflightStatuses(Campaign $campaign): array
{
    return collect(app(PreflightCheck::class)->run($campaign->fresh()))
        ->mapWithKeys(fn (PreflightItem $item): array => [$item->key => $item->status])
        ->all();
}

beforeEach(function (): void {
    config()->set('mailing.preflight.spf.postfix', ['ip4:192.0.2.10']);
    config()->set('mailing.preflight.dkim_selectors.postfix', ['mail']);
    config()->set('mailing.preflight.sending_ips', ['192.0.2.10']);

    Http::fake([
        'https://rommelmarkt.test/*' => Http::response('', 200),
        '*' => Http::response('', 404),
    ]);

    $this->list = makeList();
    makeSubscription($this->list);
});

test('the composer sanitizes the body and generates the plain-text part', function (): void {
    $this->actingWithPermissions([MailingPermission::CampaignsEdit->value]);

    $this->post(route('mailing.campaigns.store'), [
        'list_id' => (string) $this->list->id,
        'subject' => 'Nieuws',
        'preheader' => 'Kort',
        'body_html' => '<p onclick="steal()">Hallo <a href="https://rommelmarkt.test/data">data</a></p><script>alert(1)</script><img src="x">',
        'body_text' => '',
    ])->assertRedirect();

    $campaign = Campaign::query()
        ->sole();

    expect($campaign->status)->toBe(CampaignStatus::Draft)
        ->and($campaign->body_html)->not->toContain('script')
        ->and($campaign->body_html)->not->toContain('onclick')
        ->and($campaign->body_html)->not->toContain('<img')
        ->and($campaign->body_text)->toBe('Hallo data (https://rommelmarkt.test/data)')
        ->and($campaign->created_by)->toBe(1);
});

test('a healthy campaign passes the pre-flight check', function (): void {
    healthyDns();

    expect(preflightStatuses(makeCampaign($this->list)))->toBe([
        'spf' => PreflightItem::OK,
        'dkim' => PreflightItem::OK,
        'dmarc' => PreflightItem::OK,
        'alignment' => PreflightItem::OK,
        'blocklists' => PreflightItem::OK,
        'spam_score' => PreflightItem::OK,
        'links' => PreflightItem::OK,
        'text' => PreflightItem::OK,
    ]);
});

test('red pre-flight items block scheduling', function (string $problem, string $key): void {
    $dns = healthyDns();
    $campaign = makeCampaign($this->list);

    match ($problem) {
        'no dmarc' => $dns->txt['_dmarc.rommelmarkt.test'] = [],
        'spf without the server' => $dns->txt['rommelmarkt.test'] = ['v=spf1 include:elders.example -all'],
        'blocklisted' => $dns->a['10.2.0.192.zen.spamhaus.org'] = ['127.0.0.2'],
        'spammy' => app(SpamScorer::class) instanceof FakeSpamScorer ? app(SpamScorer::class)->score = 9.5 : null,
        'broken link' => $campaign->update(['body_html' => '<p>Kijk op <a href="https://elders.example/weg">deze pagina</a> voor alles over de markt.</p>']),
        'image only' => $campaign->update(['body_html' => '<p> </p>', 'body_text' => '']),
    };

    expect(preflightStatuses($campaign)[$key])->toBe(PreflightItem::ERROR);

    $this->actingWithPermissions([
        MailingPermission::CampaignsEdit->value,
        MailingPermission::CampaignsSend->value,
    ]);

    $this->post(route('mailing.campaigns.schedule', $campaign))->assertSessionHasErrors('campaign');

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Draft);
})->with([
    'no dmarc' => ['no dmarc', 'dmarc'],
    'spf without the server' => ['spf without the server', 'spf'],
    'blocklisted' => ['blocklisted', 'blocklists'],
    'spammy' => ['spammy', 'spam_score'],
    'broken link' => ['broken link', 'links'],
    'image only' => ['image only', 'text'],
]);

test('sending needs its own permission, separate from editing', function (): void {
    healthyDns();
    $campaign = makeCampaign($this->list);

    $this->actingWithPermissions([MailingPermission::CampaignsEdit->value]);

    $page = $this->get(route('mailing.campaigns.show', $campaign), [
        'X-Inertia' => 'true',
    ])->assertOk();

    expect($page->json('props.scheduleForm'))->toBeNull()
        ->and($page->json('props.testForm'))->toBeNull()
        ->and($page->json('props.scheduleBlockedReason'))->toBe(__('mailing::campaigns.errors.needs_send'));

    $this->post(route('mailing.campaigns.schedule', $campaign))->assertForbidden();
    $this->post(route('mailing.campaigns.test', $campaign), ['recipients' => 'test@example.org'])->assertForbidden();

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Draft);

    $this->actingWithPermissions([
        MailingPermission::CampaignsEdit->value,
        MailingPermission::CampaignsSend->value,
    ]);

    $this->post(route('mailing.campaigns.schedule', $campaign), [
        'scheduled_at' => '',
    ])->assertRedirect(route('mailing.campaigns.show', $campaign));

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Scheduled);

    runDispatcher();

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Sent)
        ->and(sentMessages())->toHaveCount(1);
});

test('a test mail goes only to the entered addresses and creates no sends', function (): void {
    $campaign = makeCampaign($this->list);

    $this->actingWithPermissions([
        MailingPermission::CampaignsEdit->value,
        MailingPermission::CampaignsSend->value,
    ]);

    $this->post(route('mailing.campaigns.test', $campaign), [
        'recipients' => 'test-abc@srv1.mail-tester.com, johan@example.org',
    ])->assertRedirect(route('mailing.campaigns.show', $campaign));

    expect(sentMessages()->map(fn ($message): string => $message->getEnvelope()->getRecipients()[0]->getAddress())->all())->toBe([
        'test-abc@srv1.mail-tester.com',
        'johan@example.org',
    ])
        ->and($campaign->sends()->count())->toBe(0);

    $this->post(route('mailing.campaigns.test', $campaign), [
        'recipients' => 'geen-adres',
    ])->assertSessionHasErrors('recipients');
});

test('a campaign that is scheduled or sending can no longer be edited', function (CampaignStatus $status): void {
    $campaign = makeCampaign($this->list);
    $campaign->forceFill([
        'status' => $status,
    ])->save();

    $this->actingWithPermissions([MailingPermission::CampaignsEdit->value]);

    $this->put(route('mailing.campaigns.update', $campaign), [
        'list_id' => (string) $this->list->id,
        'subject' => 'Gewijzigd',
        'body_html' => '<p>Andere tekst</p>',
    ])->assertSessionHasErrors('campaign');

    expect($campaign->fresh()->subject)->toBe('Nieuws van de rommelmarkt');
})->with([
    'scheduled' => [CampaignStatus::Scheduled],
    'sending' => [CampaignStatus::Sending],
]);

test('an edited body regenerates a generated plain-text part but keeps a hand-written one', function (): void {
    $campaign = makeCampaign($this->list);

    $this->actingWithPermissions([MailingPermission::CampaignsEdit->value]);

    $generated = app(HtmlToText::class)->convert($campaign->body_html);

    $this->put(route('mailing.campaigns.update', $campaign), [
        'list_id' => (string) $this->list->id,
        'subject' => 'Nieuws',
        'body_html' => '<p>Een heel nieuwe tekst over de markt van april.</p>',
        'body_text' => $generated,
    ])->assertSessionHasNoErrors();

    expect($campaign->fresh()->body_text)->toBe('Een heel nieuwe tekst over de markt van april.');

    $this->put(route('mailing.campaigns.update', $campaign), [
        'list_id' => (string) $this->list->id,
        'subject' => 'Nieuws',
        'body_html' => '<p>Weer een andere tekst over de markt van april.</p>',
        'body_text' => 'Eigen tekst',
    ])->assertSessionHasNoErrors();

    expect($campaign->fresh()->body_text)->toBe('Eigen tekst');
});

test('the link check never calls addresses on the internal network', function (string $href): void {
    $dns = healthyDns();
    $dns->a['intern.rommelmarkt.test'] = [
        '10.0.0.5',
    ];
    $campaign = makeCampaign($this->list, [
        'body_html' => sprintf('<p>Kijk op <a href="%s">deze pagina</a> voor alles over de markt.</p>', $href),
    ]);

    expect(preflightStatuses($campaign)['links'])->toBe(PreflightItem::ERROR);

    Http::assertNothingSent();
})->with([
    'private address behind a name' => ['https://intern.rommelmarkt.test/beheer'],
    'loopback address' => ['https://127.0.0.1/'],
    'metadata address' => ['https://169.254.169.254/latest'],
    'name without an address' => ['https://onbekend.rommelmarkt.test/'],
]);

test('test mails skip suppressed addresses and are limited per hour', function (): void {
    $campaign = makeCampaign($this->list);
    Suppression::query()
        ->create([
            'email' => 'weg@example.org',
            'reason' => SuppressionReason::HardBounce,
        ]);

    $this->actingWithPermissions([
        MailingPermission::CampaignsEdit->value,
        MailingPermission::CampaignsSend->value,
    ]);

    $this->post(route('mailing.campaigns.test', $campaign), [
        'recipients' => 'weg@example.org',
    ])->assertSessionHasErrors([
        'recipients' => __('mailing::campaigns.errors.test_suppressed'),
    ]);

    foreach (range(1, 2) as $round) {
        $this->post(route('mailing.campaigns.test', $campaign), [
            'recipients' => 'a@example.org, b@example.org, c@example.org, d@example.org, weg@example.org',
        ])->assertSessionHasNoErrors();
    }

    $this->post(route('mailing.campaigns.test', $campaign), [
        'recipients' => 'a@example.org, b@example.org, c@example.org',
    ])->assertSessionHasErrors([
        'recipients' => __('mailing::campaigns.errors.test_limit', ['max' => 10]),
    ]);

    expect(sentMessages())->toHaveCount(8);
});

test('the schedule is entered and shown in the mailing time zone', function (): void {
    $campaign = makeCampaign($this->list);
    $campaign->forceFill([
        'status' => CampaignStatus::Scheduled,
        'scheduled_at' => '2026-11-02 09:00:00',
    ])->save();

    $this->actingWithPermissions([MailingPermission::CampaignsEdit->value]);

    $page = $this->get(route('mailing.campaigns.show', $campaign), [
        'X-Inertia' => 'true',
    ])->assertOk();

    expect($page->json('props.campaign.scheduledAt'))->toBe(CarbonImmutable::parse('2026-11-02 10:00:00')->isoFormat('D MMMM YYYY HH:mm'))
        ->and((new ScheduleForm)->fields()[0]->getTimezone())->toBe('Europe/Amsterdam');
});
