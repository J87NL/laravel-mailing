<?php

declare(strict_types=1);

use Illuminate\Testing\TestResponse;
use J87NL\Mailing\Actions\ChangeCampaignStatus;
use J87NL\Mailing\Actions\StartCampaign;
use J87NL\Mailing\Enums\BounceType;
use J87NL\Mailing\Enums\SendStatus;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Enums\SuppressionReason;
use J87NL\Mailing\Models\BounceEvent;
use J87NL\Mailing\Models\Send;
use J87NL\Mailing\Models\Suppression;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Email;

beforeEach(function (): void {
    config()->set('mailing.mailer_drivers.array', 'lettermint');

    $list = makeList();
    $this->subscription = makeSubscription($list);
    app(StartCampaign::class)->handle(makeCampaign($list));
    runDispatcher();

    $this->send = Send::query()
        ->sole();
});

function lettermintPayload(string $event, Send $send): string
{
    return str_replace('__SEND__', (string) $send->id, (string) file_get_contents(sprintf('%s/../fixtures/lettermint/%s.json', __DIR__, $event)));
}

/**
 * @return array<string, string>
 */
function lettermintHeaders(string $body, ?int $timestamp = null, string $secret = 'whsec_test'): array
{
    $timestamp ??= now()->getTimestamp();

    return [
        'X-Lettermint-Signature' => sprintf('t=%d,v1=%s', $timestamp, hash_hmac('sha256', sprintf('%d.%s', $timestamp, $body), $secret)),
        'X-Lettermint-Delivery' => (string) $timestamp,
        'Content-Type' => 'application/json',
    ];
}

function postLettermint(string $body, array $headers): TestResponse
{
    return test()->call('POST', route('mailing.webhooks', ['driver' => 'lettermint']), [], [], [], collect($headers)
        ->mapWithKeys(fn (string $value, string $name): array => [sprintf('HTTP_%s', strtoupper(str_replace('-', '_', $name))) => $value])
        ->put('CONTENT_TYPE', 'application/json')
        ->all(), $body);
}

test('the Lettermint driver marks messages with the send id, the campaign tag and an idempotency key', function (): void {
    $message = sentMessages()->sole()->getOriginalMessage();

    expect($message)->toBeInstanceOf(Email::class);

    $metadata = collect($message->getHeaders()->all())->first(fn ($header): bool => $header instanceof MetadataHeader);
    $tag = collect($message->getHeaders()->all())->first(fn ($header): bool => $header instanceof TagHeader);

    expect($metadata?->getKey())->toBe('send_id')
        ->and($metadata?->getValue())->toBe((string) $this->send->id)
        ->and($tag?->getValue())->toBe(sprintf('campaign-%d', $this->send->campaign_id))
        ->and($message->getHeaders()->get('Idempotency-Key')?->getBodyAsString())->toBe(sprintf('mailing-send-%d', $this->send->id))
        ->and($message->getHeaders()->has('Return-Path'))->toBeFalse();
});

test('webhooks that fail the signature check are refused', function (string $case): void {
    $body = lettermintPayload('message.hard_bounced', $this->send);

    $headers = match ($case) {
        'missing' => [],
        'wrong secret' => lettermintHeaders($body, null, 'whsec_other'),
        'stale' => lettermintHeaders($body, now()->subMinutes(10)->getTimestamp()),
        'delivery mismatch' => [
            ...lettermintHeaders($body),
            'X-Lettermint-Delivery' => '1',
        ],
        'tampered body' => lettermintHeaders(str_replace('5.1.1', '5.1.2', $body)),
    };

    postLettermint($body, $headers)->assertUnauthorized();

    expect($this->send->fresh()->status)->toBe(SendStatus::HandedOver)
        ->and(Suppression::query()
            ->count())->toBe(0);
})->with([
    'missing',
    'wrong secret',
    'stale',
    'delivery mismatch',
    'tampered body',
]);

test('an unknown driver or a driver without webhooks is not found', function (): void {
    postLettermint('{}', [])->assertUnauthorized();

    $this->call('POST', route('mailing.webhooks', ['driver' => 'postfix']))->assertNotFound();
    $this->call('POST', route('mailing.webhooks', ['driver' => 'onbekend']))->assertNotFound();
});

test('each recorded Lettermint event maps to the right status', function (string $event, SendStatus $status, ?SuppressionReason $suppression, ?BounceType $bounce, SubscriptionStatus $subscription): void {
    $body = lettermintPayload($event, $this->send);

    postLettermint($body, lettermintHeaders($body))->assertOk()->assertJson([
        'applied' => 1,
    ]);

    expect($this->send->fresh()->status)->toBe($status)
        ->and(Suppression::query()
            ->first()?->reason)->toBe($suppression)
        ->and(BounceEvent::query()
            ->first()?->type)->toBe($bounce)
        ->and($this->subscription->fresh()->status)->toBe($subscription);
})->with([
    'delivered' => ['message.delivered', SendStatus::Delivered, null, null, SubscriptionStatus::Subscribed],
    'soft bounce' => ['message.soft_bounced', SendStatus::Deferred, null, BounceType::Soft, SubscriptionStatus::Subscribed],
    'hard bounce' => ['message.hard_bounced', SendStatus::Bounced, SuppressionReason::HardBounce, BounceType::Hard, SubscriptionStatus::Bounced],
    'complaint' => ['message.spam_complaint', SendStatus::Complained, SuppressionReason::Complaint, null, SubscriptionStatus::Complained],
    'failed' => ['message.failed', SendStatus::Failed, null, null, SubscriptionStatus::Subscribed],
    'unsubscribed' => ['message.unsubscribed', SendStatus::HandedOver, null, null, SubscriptionStatus::Unsubscribed],
]);

test('a repeated webhook delivery is applied once', function (): void {
    $body = lettermintPayload('message.soft_bounced', $this->send);

    postLettermint($body, lettermintHeaders($body))->assertJson(['applied' => 1]);
    postLettermint($body, lettermintHeaders($body))->assertJson(['applied' => 0]);

    expect(BounceEvent::query()
        ->count())->toBe(1);
});

test('provider retries of one deferred message never suppress the address', function (): void {
    foreach (range(1, 3) as $retry) {
        $body = str_replace('evt_soft', sprintf('evt_soft_%d', $retry), lettermintPayload('message.soft_bounced', $this->send));

        postLettermint($body, lettermintHeaders($body))->assertJson(['applied' => 1]);
    }

    expect(BounceEvent::query()
        ->count())->toBe(3)
        ->and(Suppression::query()
            ->count())->toBe(0)
        ->and($this->subscription->fresh()->status)->toBe(SubscriptionStatus::Subscribed);
});

test('a late delivery report never moves a bounced send back', function (): void {
    $bounce = lettermintPayload('message.hard_bounced', $this->send);
    postLettermint($bounce, lettermintHeaders($bounce))->assertOk();

    $delivered = lettermintPayload('message.delivered', $this->send);
    postLettermint($delivered, lettermintHeaders($delivered))->assertOk();

    expect($this->send->fresh()->status)->toBe(SendStatus::Bounced)
        ->and($this->send->fresh()->delivered_at)->toBeNull();
});

test('a delivery report after an interrupted send marks it delivered so it is never retried', function (): void {
    $this->send->forceFill([
        'status' => SendStatus::Failed,
        'handed_over_at' => null,
        'last_response' => 'interrupted',
    ])->save();

    $delivered = lettermintPayload('message.delivered', $this->send);
    postLettermint($delivered, lettermintHeaders($delivered))->assertOk();

    expect($this->send->fresh()->status)->toBe(SendStatus::Delivered)
        ->and(app(ChangeCampaignStatus::class)->retryFailed($this->send->campaign))->toBe(0);
});
