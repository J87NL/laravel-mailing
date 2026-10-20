<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use J87NL\Mailing\Contracts\DnsResolver;
use J87NL\Mailing\Contracts\SpamScorer;
use J87NL\Mailing\Enums\CampaignStatus;
use J87NL\Mailing\Enums\SuppressionReason;
use J87NL\Mailing\Forms\ScheduleForm;
use J87NL\Mailing\Html\HtmlToText;
use J87NL\Mailing\Mail\ListHeaders;
use J87NL\Mailing\MailingPermission;
use J87NL\Mailing\Models\Campaign;
use J87NL\Mailing\Models\Suppression;
use J87NL\Mailing\Preflight\PreflightCheck;
use J87NL\Mailing\Preflight\PreflightItem;
use J87NL\Mailing\Sending\Verp;
use J87NL\Mailing\Tests\Fakes\FakeDnsResolver;
use J87NL\Mailing\Tests\Fakes\FakeSpamScorer;
use J87NL\Mailing\Tests\Fakes\RecipientRejectingTransport;
use J87NL\Mailing\Tests\Fakes\RefusingTransport;
use J87NL\Mailing\Tests\Fakes\RejectingWith554Transport;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;

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

test('a test mail that the mail server refuses shows the cause instead of an error page', function (string $transport, string $cause): void {
    Exceptions::fake();
    Mail::extend('broken', fn (): AbstractTransport => new $transport);
    config()->set('mail.mailers.broken', [
        'transport' => 'broken',
    ]);
    $this->list->update([
        'mailer' => 'broken',
    ]);
    $campaign = makeCampaign($this->list);

    $this->actingWithPermissions([
        MailingPermission::CampaignsEdit->value,
        MailingPermission::CampaignsSend->value,
    ]);

    $this->post(route('mailing.campaigns.test', $campaign), [
        'recipients' => 'johan@example.org',
    ])
        ->assertRedirect(route('mailing.campaigns.show', $campaign))
        ->assertSessionHasErrors([
            'recipients' => __('mailing::campaigns.errors.test_failed', [
                'recipient' => 'johan@example.org',
                'cause' => __($cause, [
                    'code' => 554,
                ]),
            ]),
        ])
        ->assertSessionMissing('inertia.flash_data');

    Exceptions::assertReported(TransportException::class);

    expect(sentMessages())->toHaveCount(0)
        ->and($campaign->sends()->count())->toBe(0)
        ->and(RateLimiter::attempts('mailing-test-send:1'))->toBe(1);
})->with([
    'connection refused' => [
        RefusingTransport::class,
        'mailing::campaigns.transport_errors.connection',
    ],
    'mail rejected' => [
        RejectingWith554Transport::class,
        'mailing::campaigns.transport_errors.rejected',
    ],
]);

test('one refused test address does not stop the others', function (): void {
    RecipientRejectingTransport::$delivered = [];
    Mail::extend('rejecting', fn (): RecipientRejectingTransport => new RecipientRejectingTransport);
    config()->set('mail.mailers.rejecting', [
        'transport' => 'rejecting',
    ]);
    $this->list->update([
        'mailer' => 'rejecting',
    ]);
    $campaign = makeCampaign($this->list);

    $this->actingWithPermissions([
        MailingPermission::CampaignsEdit->value,
        MailingPermission::CampaignsSend->value,
    ]);

    $this->post(route('mailing.campaigns.test', $campaign), [
        'recipients' => sprintf('een@example.org, %s, drie@example.org', RecipientRejectingTransport::REJECTED),
    ])
        ->assertSessionHasErrors([
            'recipients' => __('mailing::campaigns.errors.test_failed', [
                'recipient' => RecipientRejectingTransport::REJECTED,
                'cause' => __('mailing::campaigns.transport_errors.rejected', [
                    'code' => 550,
                ]),
            ]),
        ])
        ->assertSessionHas('inertia.flash_data');

    expect(RecipientRejectingTransport::$delivered)->toBe([
        'een@example.org',
        'drie@example.org',
    ]);
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

test('a test send is the real message with List headers, the unsubscribe footer and the VERP Return-Path', function (): void {
    $campaign = makeCampaign($this->list);

    $this->actingWithPermissions([
        MailingPermission::CampaignsEdit->value,
        MailingPermission::CampaignsSend->value,
    ]);

    $this->post(route('mailing.campaigns.test', $campaign), [
        'recipients' => 'test@example.org',
    ])->assertSessionHasNoErrors();

    $sent = sentMessages()->sole();
    $message = $sent->getOriginalMessage();

    expect($message)->toBeInstanceOf(Email::class);

    $headers = $message->getHeaders();
    $unsubscribeUrl = route('mailing.unsubscribe.show', [
        'token' => ListHeaders::PREVIEW_TOKEN,
    ]);

    expect($headers->get('List-Unsubscribe')?->getBodyAsString())->toBe(sprintf('<%s>', $unsubscribeUrl))
        ->and($headers->get('List-Unsubscribe-Post')?->getBodyAsString())->toBe('List-Unsubscribe=One-Click')
        ->and($headers->get('List-Id')?->getBodyAsString())->toContain('nieuws.rommelmarkt.test')
        ->and($sent->getEnvelope()->getSender()->getAddress())->toBe(Verp::address(Verp::TEST_SEND_ID))
        ->and($message->getHtmlBody())->toContain(e($unsubscribeUrl))
        ->and($message->getTextBody())->toContain($unsubscribeUrl);
});

test('a test send avoids the invisible-text and hex Message-ID spam signals', function (): void {
    $campaign = makeCampaign($this->list);

    $this->actingWithPermissions([
        MailingPermission::CampaignsEdit->value,
        MailingPermission::CampaignsSend->value,
    ]);

    $this->post(route('mailing.campaigns.test', $campaign), [
        'recipients' => 'test@example.org',
    ])->assertSessionHasNoErrors();

    $message = sentMessages()->sole()->getOriginalMessage();
    $messageId = (string) $message->getHeaders()->get('Message-ID')?->getBodyAsString();

    expect($message->getHtmlBody())->toContain('De data voor volgend jaar')
        ->not->toContain('font-size:1px')
        ->not->toContain('opacity:0')
        ->and($messageId)->toMatch('/^<preview\.[a-z0-9]{16}@/')
        ->not->toMatch('/^<[0-9a-f]{32}@/');
});

test('SPF is checked on the bounce domain that Postfix uses as envelope sender', function (): void {
    config()->set('mailing.bounces.domain', 'bounces.rommelmarkt.test');
    healthyDns();

    $items = collect(app(PreflightCheck::class)->run(makeCampaign($this->list)))
        ->keyBy('key');

    expect($items['spf']->status)->toBe(PreflightItem::ERROR)
        ->and($items['spf']->message)->toContain('bounces.rommelmarkt.test')
        ->and($items['alignment']->status)->toBe(PreflightItem::OK);
});

test('the link check follows safe redirects and only warns about links that do not answer 200', function (): void {
    $dns = healthyDns();
    $dns->a['kort.example'] = [
        '93.184.215.20',
    ];
    $dns->a['afgeschermd.example'] = [
        '93.184.215.21',
    ];
    $dns->a['intern.rommelmarkt.test'] = [
        '10.0.0.5',
    ];
    $dns->a['traag.example'] = [
        '93.184.215.22',
    ];

    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake([
        'https://kort.example/naar-site' => Http::response('', 301, [
            'Location' => 'https://rommelmarkt.test/data',
        ]),
        'https://kort.example/naar-binnen' => Http::response('', 302, [
            'Location' => 'https://intern.rommelmarkt.test/beheer',
        ]),
        'https://traag.example/*' => fn () => throw new ConnectionException('Timed out'),
        'https://afgeschermd.example/*' => Http::response('', 403),
        'https://rommelmarkt.test/*' => Http::response('', 200),
    ]);

    $campaign = fn (string $href): Campaign => makeCampaign($this->list, [
        'body_html' => sprintf('<p>Kijk op <a href="%s">deze pagina</a> voor alles over de markt.</p>', $href),
    ]);

    expect(preflightStatuses($campaign('https://kort.example/naar-site'))['links'])->toBe(PreflightItem::OK)
        ->and(preflightStatuses($campaign('https://afgeschermd.example/pagina'))['links'])->toBe(PreflightItem::WARNING)
        ->and(preflightStatuses($campaign('https://traag.example/pagina'))['links'])->toBe(PreflightItem::WARNING)
        ->and(preflightStatuses($campaign('https://kort.example:8443/pagina'))['links'])->toBe(PreflightItem::ERROR)
        ->and(preflightStatuses($campaign('https://kort.example/naar-binnen'))['links'])->toBe(PreflightItem::ERROR);

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'intern.rommelmarkt.test'));
});
