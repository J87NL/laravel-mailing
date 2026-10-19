<?php

declare(strict_types=1);

use J87NL\Mailing\Actions\StartCampaign;
use J87NL\Mailing\Enums\ConsentAction;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Enums\SuppressionReason;
use J87NL\Mailing\Models\ConsentRecord;
use J87NL\Mailing\Models\Send;
use J87NL\Mailing\Models\Subscriber;
use J87NL\Mailing\Models\Subscription;
use J87NL\Mailing\Models\Suppression;

test('unconfirmed sign-ups are purged after 30 days', function (): void {
    $list = makeList();

    $this->travel(-31)->days();
    $old = makeSubscription($list, 'oud@example.org', SubscriptionStatus::Pending);
    ConsentRecord::query()
        ->create([
            'subscriber_id' => $old->subscriber_id,
            'list_id' => $list->id,
            'action' => ConsentAction::Requested,
        ]);
    $this->travelBack();

    $this->travel(-29)->days();
    $recent = makeSubscription($list, 'recent@example.org', SubscriptionStatus::Pending);
    $this->travelBack();

    $this->artisan('mailing:prune')->assertSuccessful();

    expect(Subscription::query()
        ->pluck('id')->all())->toBe([$recent->id])
        ->and(Subscriber::query()
            ->pluck('email')->all())->toBe(['recent@example.org'])
        ->and(ConsentRecord::query()
            ->count())->toBe(0);
});

test('consent records of unsubscribed addresses are kept two years while suppressions stay', function (): void {
    $list = makeList();
    $kept = makeSubscription($list, 'kort@example.org', SubscriptionStatus::Unsubscribed);
    $kept->forceFill([
        'unsubscribed_at' => now()->subYear(),
    ])->save();

    $expired = makeSubscription($list, 'lang@example.org', SubscriptionStatus::Unsubscribed);
    $expired->forceFill([
        'unsubscribed_at' => now()->subYears(2)->subDay(),
    ])->save();
    ConsentRecord::query()
        ->create([
            'subscriber_id' => $expired->subscriber_id,
            'list_id' => $list->id,
            'action' => ConsentAction::Unsubscribed,
        ]);
    Suppression::query()
        ->create([
            'email' => 'lang@example.org',
            'reason' => SuppressionReason::UnsubscribeAll,
        ]);

    $this->travel(31)->days();

    $this->artisan('mailing:prune')->assertSuccessful();

    expect(Subscription::query()
        ->pluck('id')->all())->toBe([$kept->id])
        ->and(Subscriber::query()
            ->where('email', 'lang@example.org')->exists())->toBeFalse()
        ->and(ConsentRecord::query()
            ->count())->toBe(0)
        ->and(Suppression::covers('lang@example.org'))->toBeTrue();
});

test('active subscriptions are never purged', function (): void {
    $list = makeList();

    $this->travel(-5)->years();
    $subscription = makeSubscription($list);
    $this->travelBack();

    $this->artisan('mailing:prune')->assertSuccessful();

    expect($subscription->fresh())->not->toBeNull();
});

test('a renewed sign-up that is never confirmed is purged even when the address was confirmed before', function (): void {
    $list = makeList();

    $this->travel(-31)->days();
    makeSubscription($list, 'terug@example.org', SubscriptionStatus::Pending)
        ->forceFill([
            'confirmed_at' => now()->subYear(),
        ])
        ->save();
    $this->travelBack();

    $this->artisan('mailing:prune')->assertSuccessful();

    expect(Subscription::query()
        ->count())->toBe(0);
});

test('purging a renewed sign-up keeps the earlier unsubscribe history and ends the subscription again', function (): void {
    $list = makeList();

    $this->travel(-90)->days();
    $subscription = makeSubscription($list, 'terug@example.org');
    $confirmed = ConsentRecord::query()
        ->create([
            'subscriber_id' => $subscription->subscriber_id,
            'list_id' => $list->id,
            'action' => ConsentAction::Confirmed,
        ]);
    $this->travelBack();

    $this->travel(-60)->days();
    $unsubscribed = ConsentRecord::query()
        ->create([
            'subscriber_id' => $subscription->subscriber_id,
            'list_id' => $list->id,
            'action' => ConsentAction::Unsubscribed,
        ]);
    $this->travelBack();

    $this->travel(-31)->days();
    ConsentRecord::query()
        ->create([
            'subscriber_id' => $subscription->subscriber_id,
            'list_id' => $list->id,
            'action' => ConsentAction::Requested,
        ]);
    $subscription->forceFill([
        'status' => SubscriptionStatus::Pending,
        'confirmed_at' => null,
        'unsubscribed_at' => null,
    ])->save();
    $this->travelBack();

    $this->artisan('mailing:prune')->assertSuccessful();

    $subscription->refresh();

    expect(ConsentRecord::query()
        ->orderBy('id')
        ->pluck('id')->all())->toBe([
            $confirmed->id,
            $unsubscribed->id,
        ])
        ->and($subscription->status)->toBe(SubscriptionStatus::Unsubscribed)
        ->and($subscription->unsubscribed_at?->toDateTimeString())->toBe($unsubscribed->created_at?->toDateTimeString())
        ->and($subscription->confirmed_at?->toDateTimeString())->toBe($confirmed->created_at?->toDateTimeString());
});

test('send records are deleted after 13 months', function (): void {
    $list = makeList();
    makeSubscription($list);

    $this->travel(-14)->months();
    app(StartCampaign::class)->handle(makeCampaign($list));
    $this->travelBack();

    app(StartCampaign::class)->handle(makeCampaign($list));

    $this->artisan('mailing:prune')->assertSuccessful();

    expect(Send::query()
        ->count())->toBe(1);
});
