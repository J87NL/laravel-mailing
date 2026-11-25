<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use J87NL\Mailing\Enums\ConsentAction;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Enums\SuppressionReason;
use J87NL\Mailing\MailingPermission;
use J87NL\Mailing\Models\ConsentRecord;
use J87NL\Mailing\Models\Subscriber;
use J87NL\Mailing\Models\Subscription;
use J87NL\Mailing\Models\Suppression;

beforeEach(function (): void {
    Mail::fake();

    $this->list = makeList();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function addSubscriberPayload(int $listId, array $overrides = []): array
{
    return [
        'list_id' => (string) $listId,
        'email' => 'Jan@Example.org',
        'name' => 'Jan',
        'consent_source' => 'Aanmelding aan de kassa',
        'consent_note' => 'Jan vroeg op de markt van 12 mei om de nieuwsbrief',
        ...$overrides,
    ];
}

test('adding a subscriber needs the subscribers permission', function (): void {
    $this->actingWithPermissions([MailingPermission::Lists->value]);

    $this->get(route('mailing.subscribers.create'))->assertForbidden();
    $this->post(route('mailing.subscribers.store'), addSubscriberPayload($this->list->id))->assertForbidden();

    expect(Subscription::query()
        ->count())->toBe(0);
});

test('adding a subscriber needs a list first', function (): void {
    $this->list->delete();

    $this->actingWithPermissions([MailingPermission::Subscribers->value]);

    $this->get(route('mailing.subscribers.create'))
        ->assertRedirect(route('mailing.subscribers.index'))
        ->assertSessionHasErrors('subscriber');
});

test('an admin subscribes an address directly with a consent record naming the admin', function (): void {
    $this->actingWithPermissions([MailingPermission::Subscribers->value]);

    $this->get(route('mailing.subscribers.create'), [
        'X-Inertia' => 'true',
    ])->assertOk();

    $response = $this->post(route('mailing.subscribers.store'), addSubscriberPayload($this->list->id))
        ->assertSessionHasNoErrors();

    $subscription = Subscription::query()
        ->with('subscriber')
        ->sole();

    $response->assertRedirect(route('mailing.subscribers.show', $subscription->subscriber_id))
        ->assertInertiaFlash('toast.message', __('mailing::subscribers.flash.added'));

    $record = ConsentRecord::query()
        ->sole();

    expect($subscription)
        ->status->toBe(SubscriptionStatus::Subscribed)
        ->confirmed_at->not->toBeNull()
        ->consent_source->toBe('host: Aanmelding aan de kassa')
        ->and($subscription->subscriber->email)->toBe('jan@example.org')
        ->and($subscription->subscriber->name)->toBe('Jan')
        ->and($record)
        ->action->toBe(ConsentAction::Confirmed)
        ->list_id->toBe($this->list->id)
        ->note->toBe('Jan vroeg op de markt van 12 mei om de nieuwsbrief')
        ->actor_id->toBe(1);

    Mail::assertNothingQueued();
    Mail::assertNothingSent();
});

test('an address that is already subscribed stays as it is with a neutral message', function (): void {
    $subscription = makeSubscription($this->list, 'jan@example.org');

    $this->actingWithPermissions([MailingPermission::Subscribers->value]);

    $this->post(route('mailing.subscribers.store'), addSubscriberPayload($this->list->id))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('mailing.subscribers.show', $subscription->subscriber_id))
        ->assertInertiaFlash('toast.message', __('mailing::subscribers.flash.already_subscribed'));

    expect(ConsentRecord::query()
        ->count())->toBe(0);
});

test('a suppressed address is refused', function (): void {
    Suppression::query()
        ->create([
            'email' => 'jan@example.org',
            'reason' => SuppressionReason::HardBounce,
        ]);

    $this->actingWithPermissions([MailingPermission::Subscribers->value]);

    $this->from(route('mailing.subscribers.create'))
        ->post(route('mailing.subscribers.store'), addSubscriberPayload($this->list->id))
        ->assertRedirect(route('mailing.subscribers.create'))
        ->assertSessionHasErrors([
            'email' => __('mailing::subscribers.errors.suppressed'),
        ]);

    expect(Subscription::query()
        ->count())->toBe(0)
        ->and(ConsentRecord::query()
            ->count())->toBe(0);
});

test('an address that unsubscribed from the list is refused', function (): void {
    $subscription = makeSubscription($this->list, 'jan@example.org', SubscriptionStatus::Unsubscribed);

    $this->actingWithPermissions([MailingPermission::Subscribers->value]);

    $this->from(route('mailing.subscribers.create'))
        ->post(route('mailing.subscribers.store'), addSubscriberPayload($this->list->id))
        ->assertRedirect(route('mailing.subscribers.create'))
        ->assertSessionHasErrors([
            'email' => __('mailing::subscribers.errors.left_list'),
        ]);

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Unsubscribed)
        ->and(ConsentRecord::query()
            ->count())->toBe(0);
});

test('adding a subscriber needs a valid address, a known list and both consent fields', function (): void {
    $this->actingWithPermissions([MailingPermission::Subscribers->value]);

    $this->post(route('mailing.subscribers.store'), addSubscriberPayload($this->list->id, [
        'list_id' => '999',
        'email' => 'geen-adres',
        'consent_source' => '',
        'consent_note' => '   ',
    ]))->assertSessionHasErrors([
        'list_id',
        'email',
        'consent_source',
        'consent_note',
    ]);

    expect(Subscriber::query()
        ->count())->toBe(0);
});
