<?php

declare(strict_types=1);

use J87NL\Cms\Cache\PageCache;
use J87NL\Mailing\Enums\ConsentAction;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Enums\SuppressionReason;
use J87NL\Mailing\MailingPermission;
use J87NL\Mailing\Models\ConsentRecord;
use J87NL\Mailing\Models\MailingList;
use J87NL\Mailing\Models\Subscriber;
use J87NL\Mailing\Models\Subscription;
use J87NL\Mailing\Models\Suppression;

test('every mailing screen needs its permission', function (): void {
    $list = makeList();
    $subscription = makeSubscription($list);

    $this->actingWithPermissions([]);

    $this->get(route('mailing.lists.index'))->assertForbidden();
    $this->post(route('mailing.lists.store'), [])->assertForbidden();
    $this->get(route('mailing.subscribers.index'))->assertForbidden();
    $this->get(route('mailing.subscribers.show', $subscription->subscriber_id))->assertForbidden();
    $this->post(route('mailing.subscriptions.unsubscribe', $subscription))->assertForbidden();
    $this->get(route('mailing.suppressions.index'))->assertForbidden();
    $this->get(route('mailing.imports.index'))->assertForbidden();

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Subscribed);
});

test('an admin creates, edits and deletes a list with transport defaults from config', function (): void {
    $this->actingWithPermissions([MailingPermission::Lists->value]);

    $this->post(route('mailing.lists.store'), [
        'name' => 'Nieuwsbrief',
        'key' => 'nieuws',
        'description' => 'Twee keer per jaar',
        'is_public' => true,
    ])->assertRedirect(route('mailing.lists.index'));

    $list = MailingList::query()
        ->sole();

    expect($list)
        ->key->toBe('nieuws')
        ->mailer->toBe(config('mailing.list_defaults.mailer'))
        ->sender_identity->toBe('rommelmarkt')
        ->throttle_per_minute->toBe(20)
        ->daily_cap->toBe(500);

    $this->post(route('mailing.lists.store'), [
        'name' => 'Dubbel',
        'key' => 'nieuws',
    ])->assertSessionHasErrors('key');

    $this->post(route('mailing.lists.store'), [
        'name' => 'Fout',
        'key' => 'Met Spaties',
    ])->assertSessionHasErrors('key');

    $this->post(route('mailing.lists.store'), [
        'name' => 'Gereserveerd',
        'key' => 'webhooks',
    ])->assertSessionHasErrors('key');

    $this->put(route('mailing.lists.update', $list), [
        'name' => 'Rommelpost',
        'key' => 'nieuws',
        'is_public' => false,
    ])->assertRedirect(route('mailing.lists.index'));

    expect($list->fresh())
        ->name->toBe('Rommelpost')
        ->is_public->toBeFalse();

    $this->delete(route('mailing.lists.destroy', $list))->assertRedirect(route('mailing.lists.index'));

    $this->assertDatabaseCount('mailing_lists', 0);
});

test('a list with subscribers cannot be deleted and its delete action says why', function (): void {
    $list = makeList();
    makeSubscription($list);

    $this->actingWithPermissions([MailingPermission::Lists->value]);

    $actions = $this->get(route('mailing.lists.index'), [
        'X-Inertia' => 'true',
    ])
        ->assertOk()
        ->json('props.lists.rowActions.0');

    expect(collect($actions)->firstWhere('key', 'delete')['disabledReason'] ?? null)->toBe(__('mailing::lists.errors.has_subscriptions'));

    $this->delete(route('mailing.lists.destroy', $list))->assertSessionHasErrors('list');

    $this->assertDatabaseCount('mailing_lists', 1);
});

test('the transport settings need the settings permission and the control says so', function (): void {
    $list = makeList();

    $this->actingWithPermissions([MailingPermission::Lists->value]);

    $this->get(route('mailing.lists.transport.edit', $list))->assertForbidden();
    $this->put(route('mailing.lists.transport.update', $list), [
        'mailer' => 'smtp',
    ])->assertForbidden();

    $actions = $this->get(route('mailing.lists.index'), [
        'X-Inertia' => 'true',
    ])->json('props.lists.rowActions.0');

    expect(collect($actions)->firstWhere('key', 'transport')['disabledReason'] ?? null)->toBe(__('mailing::lists.errors.needs_settings'));

    $this->actingWithPermissions([
        MailingPermission::Lists->value,
        MailingPermission::Settings->value,
    ]);

    $this->put(route('mailing.lists.transport.update', $list), [
        'mailer' => 'smtp',
        'sender_identity' => 'rommelmarkt',
        'throttle_per_minute' => 10,
        'daily_cap' => '',
    ])->assertRedirect(route('mailing.lists.index'));

    expect($list->fresh())
        ->mailer->toBe('smtp')
        ->throttle_per_minute->toBe(10)
        ->daily_cap->toBeNull();

    $this->put(route('mailing.lists.transport.update', $list), [
        'mailer' => 'bestaat-niet',
        'sender_identity' => 'onbekend',
        'throttle_per_minute' => 0,
    ])->assertSessionHasErrors([
        'mailer',
        'sender_identity',
        'throttle_per_minute',
    ]);
});

test('an admin sees the history of an address and unsubscribes it by hand', function (): void {
    $list = makeList();
    $subscription = makeSubscription($list);

    ConsentRecord::query()
        ->create([
            'subscriber_id' => $subscription->subscriber_id,
            'list_id' => $list->id,
            'action' => ConsentAction::Confirmed,
            'source' => 'signup: /nieuwsbrief',
            'ip' => '192.0.2.1',
        ]);

    $this->actingWithPermissions([MailingPermission::Subscribers->value]);

    $page = $this->get(route('mailing.subscribers.show', $subscription->subscriber_id), [
        'X-Inertia' => 'true',
    ])->assertOk();

    expect($page->json('component'))->toBe('mailing/subscribers/Show')
        ->and($page->json('props.subscriber.email'))->toBe('lid@example.org')
        ->and($page->json('props.history.0.source'))->toBe(__('mailing::common.sources.signup', ['detail' => '/nieuwsbrief']))
        ->and($page->json('props.history.0.ip'))->toBe('192.0.2.1');

    $this->post(route('mailing.subscriptions.unsubscribe', $subscription))
        ->assertRedirect(route('mailing.subscribers.show', $subscription->subscriber_id));

    $record = ConsentRecord::query()
        ->latest('id')
        ->first();

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Unsubscribed)
        ->and($record?->action)->toBe(ConsentAction::Unsubscribed)
        ->and($record?->source)->toBe('admin')
        ->and($record?->actor_id)->toBe(1);
});

test('subscribers are exported as CSV with their lists', function (): void {
    $list = makeList();
    makeSubscription($list, 'jan@example.org');
    makeSubscription($list, 'piet@example.org');

    $this->actingWithPermissions([MailingPermission::Subscribers->value]);

    $page = $this->get(route('mailing.subscribers.index'), [
        'X-Inertia' => 'true',
    ])->assertOk();

    expect(collect($page->json('props.subscribers.data'))->pluck('email')->all())->toBe([
        'jan@example.org',
        'piet@example.org',
    ]);

    $exportUrl = (string) $page->json('props.subscribers.export.url');

    expect($exportUrl)->not->toBe('');

    $csv = $this->get($exportUrl)->assertOk()->streamedContent();

    expect($csv)->toContain('jan@example.org')
        ->and($csv)->toContain('piet@example.org')
        ->and($csv)->toContain('Nieuwsbrief: '.__('mailing::enums.subscription_status.subscribed'));
});

test('a manual suppression unsubscribes the address everywhere and can be lifted', function (): void {
    $list = makeList();
    $subscription = makeSubscription($list);

    $this->actingWithPermissions([MailingPermission::Subscribers->value]);

    $this->post(route('mailing.suppressions.store'), [
        'email' => 'LID@example.org',
        'note' => 'Wil niets meer ontvangen',
    ])->assertRedirect(route('mailing.suppressions.index'));

    $suppression = Suppression::query()
        ->sole();

    expect($suppression)
        ->email->toBe('lid@example.org')
        ->reason->toBe(SuppressionReason::Manual)
        ->created_by->toBe(1)
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Unsubscribed);

    $this->post(route('mailing.suppressions.store'), [
        'email' => 'lid@example.org',
    ])->assertSessionHasErrors('email');

    $actions = $this->get(route('mailing.suppressions.index'), [
        'X-Inertia' => 'true',
    ])->json('props.suppressions.rowActions.0');

    expect(collect($actions)->firstWhere('key', 'delete')['disabledReason'] ?? null)->toBe(__('mailing::suppressions.errors.needs_settings'));

    $this->delete(route('mailing.suppressions.destroy', $suppression))->assertForbidden();

    $this->actingWithPermissions([
        MailingPermission::Subscribers->value,
        MailingPermission::Settings->value,
    ]);

    $this->delete(route('mailing.suppressions.destroy', $suppression))->assertRedirect(route('mailing.suppressions.index'));

    $record = ConsentRecord::query()
        ->latest('id')
        ->first();

    expect(Suppression::query()
        ->count())->toBe(0)
        ->and($record?->action)->toBe(ConsentAction::Unsuppressed)
        ->and($record?->subscriber_id)->toBe($subscription->subscriber_id)
        ->and($record?->actor_id)->toBe(1);
});

test('an admin erases an address with its history and can keep it suppressed', function (): void {
    $list = makeList();
    $kept = makeSubscription($list, 'blijft-weg@example.org');
    $gone = makeSubscription($list, 'helemaal-weg@example.org');

    ConsentRecord::query()
        ->create([
            'subscriber_id' => $kept->subscriber_id,
            'list_id' => $list->id,
            'action' => ConsentAction::Confirmed,
        ]);

    $this->actingWithPermissions([]);

    $this->delete(route('mailing.subscribers.destroy', $kept->subscriber_id))->assertForbidden();

    $this->actingWithPermissions([MailingPermission::Subscribers->value]);

    $this->get(route('mailing.subscribers.erase', $kept->subscriber_id), [
        'X-Inertia' => 'true',
    ])->assertOk();

    $this->delete(route('mailing.subscribers.destroy', $kept->subscriber_id), [
        'keep_suppressed' => true,
    ])->assertRedirect(route('mailing.subscribers.index'));

    $this->delete(route('mailing.subscribers.destroy', $gone->subscriber_id), [
        'keep_suppressed' => false,
    ])->assertRedirect(route('mailing.subscribers.index'));

    expect(Subscriber::query()
        ->count())->toBe(0)
        ->and(Subscription::query()
            ->count())->toBe(0)
        ->and(ConsentRecord::query()
            ->count())->toBe(0)
        ->and(Suppression::covers('blijft-weg@example.org'))->toBeTrue()
        ->and(Suppression::covers('helemaal-weg@example.org'))->toBeFalse();
});

test('a list key cannot change after creation and list changes flush the page cache', function (): void {
    $list = makeList();
    $cache = app(PageCache::class);
    $version = $cache->version();

    $this->actingWithPermissions([MailingPermission::Lists->value]);

    $this->put(route('mailing.lists.update', $list), [
        'name' => 'Rommelpost',
        'key' => 'anders',
        'is_public' => false,
    ])->assertRedirect(route('mailing.lists.index'));

    expect($list->fresh()->key)->toBe('nieuws')
        ->and($cache->version())->toBeGreaterThan($version);
});

test('creating a list without any configured sender explains what is missing', function (): void {
    config()->set('mailing.senders', []);

    $this->actingWithPermissions([MailingPermission::Lists->value]);

    $this->post(route('mailing.lists.store'), [
        'name' => 'Nieuwsbrief',
        'key' => 'nieuws',
    ])->assertRedirect(route('mailing.lists.index'))->assertSessionHasErrors('list');

    $this->assertDatabaseCount('mailing_lists', 0);
});
