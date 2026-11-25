<?php

declare(strict_types=1);

use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use J87NL\Mailing\Actions\SubscribeAddress;
use J87NL\Mailing\Actions\SuppressAddress;
use J87NL\Mailing\Actions\UnsubscribeAddress;
use J87NL\Mailing\Enums\ConsentAction;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Enums\SuppressionReason;
use J87NL\Mailing\Models\ConsentRecord;
use J87NL\Mailing\Models\Subscriber;
use J87NL\Mailing\Models\Subscription;
use J87NL\Mailing\Models\Suppression;

beforeEach(function (): void {
    Mail::fake();

    $this->list = makeList();
});

test('a host puts a new address on a list as confirmed, without a confirmation mail', function (): void {
    $subscription = app(SubscribeAddress::class)->handle($this->list, ' Klant@Example.org ', 'Klantportaal', 'Vinkje bij het aanmaken van de gebruiker', 7, 'Klant');

    expect($subscription)->not->toBeNull()
        ->status->toBe(SubscriptionStatus::Subscribed)
        ->confirmed_at->not->toBeNull()
        ->consent_at->not->toBeNull()
        ->consent_source->toBe('host: Klantportaal')
        ->and($subscription->subscriber)
        ->email->toBe('klant@example.org')
        ->name->toBe('Klant')
        ->and(ConsentRecord::query()
            ->sole())
        ->action->toBe(ConsentAction::Confirmed)
        ->list_id->toBe($this->list->id)
        ->source->toBe('host: Klantportaal')
        ->note->toBe('Vinkje bij het aanmaken van de gebruiker')
        ->actor_id->toBe(7);

    Mail::assertNothingOutgoing();
});

test('a host never subscribes a suppressed address', function (): void {
    app(SuppressAddress::class)->handle('klant@example.org', SuppressionReason::HardBounce, 'dsn');

    expect(app(SubscribeAddress::class)->handle($this->list, 'klant@example.org', 'Klantportaal'))->toBeNull()
        ->and(Subscription::query()
            ->count())->toBe(0)
        ->and(Subscriber::query()
            ->count())->toBe(0);

    Mail::assertNothingOutgoing();
});

test('a suppression added after the first check still stops a host subscription without leaving a subscriber', function (): void {
    $suppressed = false;

    Event::listen(TransactionBeginning::class, function () use (&$suppressed): void {
        if ($suppressed) {
            return;
        }

        $suppressed = true;

        Suppression::query()
            ->create([
                'email' => 'klant@example.org',
                'reason' => SuppressionReason::Manual,
                'source' => 'admin',
            ]);
    });

    expect(app(SubscribeAddress::class)->handle($this->list, 'klant@example.org', 'Klantportaal'))->toBeNull()
        ->and(Subscription::query()
            ->count())->toBe(0)
        ->and(Subscriber::query()
            ->count())->toBe(0);
});

test('a host never subscribes an address again that left the list', function (SubscriptionStatus $status): void {
    $subscription = makeSubscription($this->list, 'klant@example.org', $status);

    expect(app(SubscribeAddress::class)->handle($this->list, 'klant@example.org', 'Klantportaal'))->toBeNull()
        ->and($subscription->fresh()->status)->toBe($status)
        ->and(ConsentRecord::query()
            ->count())->toBe(0);
})->with([
    'unsubscribed' => [SubscriptionStatus::Unsubscribed],
    'bounced' => [SubscriptionStatus::Bounced],
    'complained' => [SubscriptionStatus::Complained],
]);

test('a host confirms a pending subscription', function (): void {
    $pending = makeSubscription($this->list, 'klant@example.org', SubscriptionStatus::Pending);

    $subscription = app(SubscribeAddress::class)->handle($this->list, 'klant@example.org', 'Klantportaal', 'Vinkje', 7);

    expect($subscription?->id)->toBe($pending->id)
        ->and($pending->fresh())
        ->status->toBe(SubscriptionStatus::Subscribed)
        ->confirmed_at->not->toBeNull()
        ->consent_source->toBe('host: Klantportaal')
        ->and(ConsentRecord::query()
            ->sole())
        ->action->toBe(ConsentAction::Confirmed)
        ->note->toBe('Vinkje');
});

test('a host subscribing an address that is already subscribed changes nothing', function (): void {
    $existing = makeSubscription($this->list, 'klant@example.org');

    $subscription = app(SubscribeAddress::class)->handle($this->list, 'klant@example.org', 'Klantportaal');

    expect($subscription?->id)->toBe($existing->id)
        ->and($subscription?->consent_source)->toBeNull()
        ->and(ConsentRecord::query()
            ->count())->toBe(0);
});

test('a host subscription keeps the name a subscriber already has', function (): void {
    Subscriber::query()
        ->create([
            'email' => 'klant@example.org',
            'name' => 'Bestaande naam',
        ]);

    app(SubscribeAddress::class)->handle($this->list, 'klant@example.org', 'Klantportaal', null, null, 'Nieuwe naam');

    expect(Subscriber::query()
        ->sole()->name)->toBe('Bestaande naam');
});

test('a host subscription needs a valid address and a consent source', function (string $email, string $source): void {
    app(SubscribeAddress::class)->handle($this->list, $email, $source);
})->with([
    'invalid address' => ['geen-adres', 'Klantportaal'],
    'empty consent source' => ['klant@example.org', ' '],
])->throws(InvalidArgumentException::class);

test('a host unsubscribes an address from one list only', function (): void {
    $other = makeList([
        'key' => 'onderhoud',
    ]);
    $subscription = makeSubscription($this->list, 'klant@example.org');
    $otherSubscription = makeSubscription($other, 'klant@example.org');

    expect(app(UnsubscribeAddress::class)->handle($this->list, 'KLANT@example.org', 'Klant verwijderd', 7))->toBeTrue()
        ->and($subscription->fresh())
        ->status->toBe(SubscriptionStatus::Unsubscribed)
        ->unsubscribed_at->not->toBeNull()
        ->and($otherSubscription->fresh()->status)->toBe(SubscriptionStatus::Subscribed)
        ->and(ConsentRecord::query()
            ->sole())
        ->action->toBe(ConsentAction::Unsubscribed)
        ->list_id->toBe($this->list->id)
        ->source->toBe('host: Klant verwijderd')
        ->actor_id->toBe(7);
});

test('a host unsubscribing an address that is not on the list changes nothing', function (): void {
    makeSubscription($this->list, 'klant@example.org', SubscriptionStatus::Unsubscribed);

    expect(app(UnsubscribeAddress::class)->handle($this->list, 'klant@example.org', 'Klant verwijderd'))->toBeFalse()
        ->and(app(UnsubscribeAddress::class)->handle($this->list, 'onbekend@example.org', 'Klant verwijderd'))->toBeFalse()
        ->and(ConsentRecord::query()
            ->count())->toBe(0);
});

function failSubscriberInserts(int $times): Closure
{
    $attempts = 0;

    Subscriber::creating(function () use (&$attempts, $times): void {
        $attempts++;

        if ($attempts > $times) {
            return;
        }

        throw new UniqueConstraintViolationException('testing', 'insert into "mailing_subscribers"', [], new PDOException('UNIQUE constraint failed: mailing_subscribers.email'));
    });

    return function () use (&$attempts): int {
        return $attempts;
    };
}

test('a host subscription that loses a race on a new address tries once more outside a transaction', function (): void {
    DB::commit();
    $attempts = failSubscriberInserts(1);

    expect(DB::transactionLevel())->toBe(0)
        ->and(app(SubscribeAddress::class)->handle($this->list, 'klant@example.org', 'Klantportaal'))
        ->status->toBe(SubscriptionStatus::Subscribed)
        ->and($attempts())->toBe(2);
});

test('a host subscription that loses the race twice throws instead of trying again', function (): void {
    DB::commit();
    $attempts = failSubscriberInserts(2);

    expect(fn (): ?Subscription => app(SubscribeAddress::class)->handle($this->list, 'klant@example.org', 'Klantportaal'))
        ->toThrow(UniqueConstraintViolationException::class)
        ->and($attempts())->toBe(2);
});

test('a host subscription inside a transaction of the host never retries', function (): void {
    $attempts = failSubscriberInserts(1);

    expect(DB::transactionLevel())->toBeGreaterThan(0)
        ->and(fn (): ?Subscription => app(SubscribeAddress::class)->handle($this->list, 'klant@example.org', 'Klantportaal'))
        ->toThrow(UniqueConstraintViolationException::class)
        ->and($attempts())->toBe(1);
});
