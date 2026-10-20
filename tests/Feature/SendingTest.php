<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use J87NL\Mailing\Actions\ChangeCampaignStatus;
use J87NL\Mailing\Actions\EraseSubscriber;
use J87NL\Mailing\Actions\StartCampaign;
use J87NL\Mailing\Enums\BounceType;
use J87NL\Mailing\Enums\CampaignStatus;
use J87NL\Mailing\Enums\SendStatus;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Enums\SuppressionReason;
use J87NL\Mailing\Jobs\SendCampaignMessage;
use J87NL\Mailing\MailingPermission;
use J87NL\Mailing\Models\BounceEvent;
use J87NL\Mailing\Models\Send;
use J87NL\Mailing\Models\Suppression;
use J87NL\Mailing\Sending\Dispatcher;
use J87NL\Mailing\Sending\SendingLimits;
use J87NL\Mailing\Sending\Verp;
use J87NL\Mailing\Tests\Fakes\FailingTransport;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;

beforeEach(function (): void {
    $this->list = makeList([
        'throttle_per_minute' => 50,
        'daily_cap' => null,
    ]);
});

test('unconfirmed, unsubscribed and suppressed addresses never receive a campaign', function (): void {
    makeSubscription($this->list, 'lid@example.org');
    makeSubscription($this->list, 'wacht@example.org', SubscriptionStatus::Pending);
    makeSubscription($this->list, 'weg@example.org', SubscriptionStatus::Unsubscribed);
    makeSubscription($this->list, 'geblokkeerd@example.org');
    Suppression::query()
        ->create([
            'email' => 'geblokkeerd@example.org',
            'reason' => SuppressionReason::HardBounce,
        ]);

    $campaign = makeCampaign($this->list);
    app(StartCampaign::class)->handle($campaign);

    expect(Send::query()
        ->pluck('email')->all())->toBe(['lid@example.org']);

    runDispatcher();

    expect(sentMessages()->map(fn (SentMessage $message): string => $message->getEnvelope()->getRecipients()[0]->getAddress())->all())->toBe(['lid@example.org'])
        ->and($campaign->fresh()->status)->toBe(CampaignStatus::Sent)
        ->and($campaign->fresh()->finished_at)->not->toBeNull();
});

test('campaign mails carry the List headers, a plain-text part, the stored Message-ID and a VERP Return-Path', function (): void {
    $subscription = makeSubscription($this->list);
    app(StartCampaign::class)->handle(makeCampaign($this->list));

    runDispatcher();

    $send = Send::query()
        ->sole();
    $message = sentMessages()->sole()->getOriginalMessage();

    expect($message)->toBeInstanceOf(Email::class);

    $headers = $message->getHeaders();

    expect($send->status)->toBe(SendStatus::HandedOver)
        ->and($send->attempts)->toBe(1)
        ->and($headers->get('List-Unsubscribe')?->getBodyAsString())->toContain(sprintf('/mailing/unsubscribe/%s', $subscription->unsubscribe_token))
        ->and($headers->get('List-Unsubscribe-Post')?->getBodyAsString())->toBe('List-Unsubscribe=One-Click')
        ->and($headers->get('List-Id')?->getBodyAsString())->toBe('"Nieuwsbrief" <nieuws.rommelmarkt.test>')
        ->and($headers->get('Message-ID')?->getBodyAsString())->toBe($send->message_id)
        ->and($headers->get('Return-Path')?->getBodyAsString())->toBe(sprintf('<%s>', Verp::address($send->id)))
        ->and(sentMessages()->sole()->getEnvelope()->getSender()->getAddress())->toBe(Verp::address($send->id))
        ->and($message->getTextBody())->toContain('Op zaterdag 3 april staan we weer klaar.')
        ->and($message->getTextBody())->toContain(sprintf('/mailing/unsubscribe/%s', $subscription->unsubscribe_token))
        ->and($message->getHtmlBody())->toContain('De data voor volgend jaar')
        ->and($message->getHtmlBody())->toContain('style="margin:0 0 16px;');
});

test('the dispatcher respects the per-list throttle and the daily cap', function (): void {
    $this->list->update([
        'throttle_per_minute' => 2,
        'daily_cap' => 3,
    ]);

    foreach (range(1, 5) as $number) {
        makeSubscription($this->list, sprintf('lid%d@example.org', $number));
    }

    app(StartCampaign::class)->handle(makeCampaign($this->list));

    $start = CarbonImmutable::parse('2026-11-02 10:00:00');
    $this->travelTo($start);

    expect(runDispatcher($start)['dispatched'])->toBe(2)
        ->and(runDispatcher($start)['dispatched'])->toBe(0);

    expect(runDispatcher($start->addMinute())['dispatched'])->toBe(1)
        ->and(runDispatcher($start->addMinutes(2))['dispatched'])->toBe(0)
        ->and(runDispatcher($start->addDay()->addMinutes(20))['dispatched'])->toBe(2);

    expect(sentMessages())->toHaveCount(5);
});

test('the dispatcher respects the per-domain limit for large mailbox providers', function (): void {
    config()->set('mailing.sending.domain_limits', [
        'gmail.com' => 1,
    ]);

    makeSubscription($this->list, 'een@gmail.com');
    makeSubscription($this->list, 'twee@gmail.com');
    makeSubscription($this->list, 'drie@ziggo.nl');

    app(StartCampaign::class)->handle(makeCampaign($this->list));

    $now = CarbonImmutable::parse('2026-11-02 10:00:00');
    $this->travelTo($now);

    expect(runDispatcher($now)['dispatched'])->toBe(2)
        ->and(runDispatcher($now->addMinute())['dispatched'])->toBe(1);
});

test('a send is claimed once, so a repeated job sends nothing twice', function (): void {
    makeSubscription($this->list);
    app(StartCampaign::class)->handle(makeCampaign($this->list));

    $send = Send::query()
        ->sole();

    SendCampaignMessage::dispatchSync($send->id, 0);
    SendCampaignMessage::dispatchSync($send->id, 0);

    expect(sentMessages())->toHaveCount(1)
        ->and($send->fresh()->attempts)->toBe(1);
});

test('a stale sending row becomes failed and is never retried automatically', function (): void {
    makeSubscription($this->list);
    $campaign = makeCampaign($this->list);
    app(StartCampaign::class)->handle($campaign);

    $send = Send::query()
        ->sole();
    $send->forceFill([
        'status' => SendStatus::Sending,
        'sending_started_at' => now()->subMinutes(31),
    ])->save();

    expect(runDispatcher()['interrupted'])->toBe(1)
        ->and($send->fresh()->status)->toBe(SendStatus::Failed)
        ->and($send->fresh()->last_response)->toBe('interrupted');

    runDispatcher(CarbonImmutable::now()->addHours(5));

    expect(sentMessages())->toHaveCount(0)
        ->and($send->fresh()->status)->toBe(SendStatus::Failed);

    expect(app(ChangeCampaignStatus::class)->retryFailed($campaign))->toBe(1);

    runDispatcher(CarbonImmutable::now()->addHours(5));

    expect(sentMessages())->toHaveCount(1);
});

test('a 4xx answer is retried after 15 minutes, 1 hour and 4 hours and then fails', function (): void {
    Mail::extend('failing', fn (): FailingTransport => new FailingTransport);
    config()->set('mail.mailers.failing', [
        'transport' => 'failing',
    ]);
    config()->set('mailing.mailer_drivers.failing', 'postfix');

    $this->list->update([
        'mailer' => 'failing',
    ]);
    makeSubscription($this->list);
    app(StartCampaign::class)->handle(makeCampaign($this->list));

    $send = Send::query()
        ->sole();
    $now = CarbonImmutable::parse('2026-11-02 10:00:00');
    $this->travelTo($now);

    runDispatcher($now);

    expect($send->fresh()->status)->toBe(SendStatus::Deferred)
        ->and($send->fresh()->next_attempt_at?->equalTo($now->addMinutes(15)))->toBeTrue();

    runDispatcher($now->addMinutes(14));

    expect($send->fresh()->attempts)->toBe(1);

    foreach ([[15, 60], [75, 240]] as [$after, $delay]) {
        $this->travelTo($now->addMinutes($after));
        runDispatcher($now->addMinutes($after));

        expect($send->fresh()->status)->toBe(SendStatus::Deferred)
            ->and($send->fresh()->next_attempt_at?->equalTo($now->addMinutes($after + $delay)))->toBeTrue();
    }

    $this->travelTo($now->addMinutes(315));
    runDispatcher($now->addMinutes(315));

    expect($send->fresh()->status)->toBe(SendStatus::Failed)
        ->and($send->fresh()->attempts)->toBe(4);
});

test('a 5xx answer fails without retries', function (): void {
    FailingTransport::$code = 554;
    Mail::extend('failing', fn (): FailingTransport => new FailingTransport);
    config()->set('mail.mailers.failing', [
        'transport' => 'failing',
    ]);

    $this->list->update([
        'mailer' => 'failing',
    ]);
    makeSubscription($this->list);
    app(StartCampaign::class)->handle(makeCampaign($this->list));

    runDispatcher();

    expect(Send::query()
        ->sole()->status)->toBe(SendStatus::Failed);

    FailingTransport::$code = 450;
});

test('pausing stops the dispatcher, resuming continues and cancelling drops the queue', function (): void {
    makeSubscription($this->list, 'een@example.org');
    makeSubscription($this->list, 'twee@example.org');
    $campaign = makeCampaign($this->list);
    app(StartCampaign::class)->handle($campaign);

    $status = app(ChangeCampaignStatus::class);

    expect($status->pause($campaign))->toBeTrue()
        ->and(runDispatcher()['dispatched'])->toBe(0)
        ->and($status->resume($campaign))->toBeTrue();

    $this->list->update([
        'throttle_per_minute' => 1,
    ]);

    runDispatcher();

    expect(sentMessages())->toHaveCount(1)
        ->and($status->cancel($campaign))->toBeTrue()
        ->and(Send::query()
            ->where('status', SendStatus::Cancelled)->count())->toBe(1)
        ->and(runDispatcher(CarbonImmutable::now()->addMinute())['dispatched'])->toBe(0);
});

test('a job for a paused campaign leaves its send in the queue', function (): void {
    makeSubscription($this->list);
    $campaign = makeCampaign($this->list);
    app(StartCampaign::class)->handle($campaign);
    app(ChangeCampaignStatus::class)->pause($campaign);

    SendCampaignMessage::dispatchSync(Send::query()
        ->sole()->id, 0);

    expect(Send::query()
        ->sole()->status)->toBe(SendStatus::Queued)
        ->and(sentMessages())->toHaveCount(0);
});

test('a scheduled campaign starts when its time has come and snapshots its recipients then', function (): void {
    makeSubscription($this->list, 'eerst@example.org');
    $campaign = makeCampaign($this->list);
    $at = CarbonImmutable::now()->addHour();

    app(ChangeCampaignStatus::class)->schedule($campaign, $at);

    expect(runDispatcher()['started'])->toBe(0);

    makeSubscription($this->list, 'later@example.org');

    expect(runDispatcher($at)['started'])->toBe(1)
        ->and(Send::query()
            ->count())->toBe(2)
        ->and($campaign->fresh()->started_at)->not->toBeNull();
});

test('a job dispatched for an earlier attempt sends nothing once the send was deferred', function (): void {
    makeSubscription($this->list);
    app(StartCampaign::class)->handle(makeCampaign($this->list));

    $send = Send::query()
        ->sole();
    $send->forceFill([
        'status' => SendStatus::Deferred,
        'attempts' => 1,
        'next_attempt_at' => now()->addMinutes(15),
    ])->save();

    SendCampaignMessage::dispatchSync($send->id, 0);

    expect(sentMessages())->toHaveCount(0)
        ->and($send->fresh()->attempts)->toBe(1)
        ->and($send->fresh()->status)->toBe(SendStatus::Deferred);
});

test('retrying failed sends leaves sends that were already handed over alone', function (): void {
    makeSubscription($this->list, 'een@example.org');
    makeSubscription($this->list, 'twee@example.org');
    $campaign = makeCampaign($this->list);
    app(StartCampaign::class)->handle($campaign);

    Send::query()
        ->where('email', 'een@example.org')
        ->update([
            'status' => SendStatus::Failed,
        ]);
    Send::query()
        ->where('email', 'twee@example.org')
        ->update([
            'status' => SendStatus::Failed,
            'handed_over_at' => now(),
        ]);

    expect(app(ChangeCampaignStatus::class)->retryFailed($campaign))->toBe(1)
        ->and(Send::query()
            ->where('email', 'twee@example.org')
            ->sole()->status)->toBe(SendStatus::Failed);
});

test('the daily cap starts at midnight in the mailing time zone', function (): void {
    $this->list->update([
        'daily_cap' => 1,
    ]);
    $limits = app(SendingLimits::class);
    $lateEvening = CarbonImmutable::parse('2026-11-02 22:30:00');

    expect($limits->claim($this->list, 'een@example.org', $lateEvening))->toBeTrue()
        ->and($limits->claim($this->list, 'twee@example.org', $lateEvening->addMinutes(20)))->toBeFalse()
        ->and($limits->claim($this->list, 'drie@example.org', $lateEvening->addMinutes(40)))->toBeTrue();
});

test('interrupted and skipped sends are explained in the send table', function (): void {
    makeSubscription($this->list);
    $campaign = makeCampaign($this->list);
    app(StartCampaign::class)->handle($campaign);

    Send::query()
        ->update([
            'status' => SendStatus::Failed,
            'last_response' => Dispatcher::INTERRUPTED,
        ]);

    $this->actingWithPermissions([MailingPermission::CampaignsEdit->value]);

    $rows = $this->get(route('mailing.campaigns.show', $campaign), [
        'X-Inertia' => 'true',
    ])->json('props.sends.data');

    expect(collect($rows)->pluck('last_response')->all())->toBe([
        __('mailing::campaigns.responses.interrupted'),
    ]);
});

test('jobs that waited for a stalled worker send nothing, so the throttle holds when it comes back', function (): void {
    Queue::fake();

    $this->list->update([
        'throttle_per_minute' => 2,
    ]);

    foreach (range(1, 6) as $number) {
        makeSubscription($this->list, sprintf('lid%d@example.org', $number));
    }

    app(StartCampaign::class)->handle(makeCampaign($this->list));

    $start = CarbonImmutable::parse('2026-11-02 10:00:00');

    foreach (range(0, 4) as $minute) {
        $this->travelTo($start->addMinutes($minute));
        runDispatcher($start->addMinutes($minute));
    }

    $jobs = Queue::pushed(SendCampaignMessage::class);

    expect($jobs)->toHaveCount(10);

    $jobs->each(fn (SendCampaignMessage $job) => app()->call([$job, 'handle']));

    $handedOver = Send::query()
        ->where('status', SendStatus::HandedOver)
        ->count();

    expect(sentMessages())->toHaveCount(2)
        ->and($handedOver)->toBe(2);
});

test('a transport error without an SMTP code fails the send as interrupted instead of resending it', function (): void {
    FailingTransport::$code = 0;
    Mail::extend('failing', fn (): FailingTransport => new FailingTransport);
    config()->set('mail.mailers.failing', [
        'transport' => 'failing',
    ]);

    $this->list->update([
        'mailer' => 'failing',
    ]);
    makeSubscription($this->list);
    app(StartCampaign::class)->handle(makeCampaign($this->list));

    runDispatcher();

    $send = Send::query()
        ->sole();

    expect($send->status)->toBe(SendStatus::Failed)
        ->and($send->last_response)->toBe(Dispatcher::INTERRUPTED);

    FailingTransport::$code = 450;
});

test('failed sends of a cancelled campaign are never retried', function (): void {
    makeSubscription($this->list);
    $campaign = makeCampaign($this->list);
    app(StartCampaign::class)->handle($campaign);

    Send::query()
        ->update([
            'status' => SendStatus::Failed,
        ]);
    app(ChangeCampaignStatus::class)->cancel($campaign);

    expect(app(ChangeCampaignStatus::class)->retryFailed($campaign))->toBe(0)
        ->and(Send::query()
            ->sole()->status)->toBe(SendStatus::Failed);
});

test('the body is sanitized again when the mail is rendered', function (): void {
    makeSubscription($this->list);
    app(StartCampaign::class)->handle(makeCampaign($this->list, [
        'body_html' => '<p onclick="steal()">Hallo</p><script>alert(1)</script>',
    ]));

    runDispatcher();

    $html = (string) sentMessages()->sole()->getOriginalMessage()->getHtmlBody();

    expect($html)->toContain('Hallo')
        ->and($html)->not->toContain('<script')
        ->and($html)->not->toContain('onclick');
});

test('erasing an address also deletes its send and bounce records', function (): void {
    $subscription = makeSubscription($this->list);
    app(StartCampaign::class)->handle(makeCampaign($this->list));
    runDispatcher();

    BounceEvent::query()
        ->create([
            'email' => 'lid@example.org',
            'send_id' => Send::query()
                ->value('id'),
            'type' => BounceType::Soft,
            'source' => 'dsn',
        ]);

    app(EraseSubscriber::class)->handle($subscription->subscriber, false);

    expect(Send::query()
        ->count())->toBe(0)
        ->and(BounceEvent::query()
            ->count())->toBe(0);
});
