<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use J87NL\Mailing\MailingPermission;
use J87NL\Mailing\Navigation\MailingNavigation;

test('the package adds one main menu item for the whole mailing module', function (): void {
    $items = collect(config('j87nl.navigation'))
        ->filter(fn (array $item): bool => str_starts_with($item['key'], 'mailing'))
        ->values();

    expect($items)->toHaveCount(1)
        ->and($items[0]['route'])->toBe('mailing.home')
        ->and($items[0]['permission'])->toBe(MailingNavigation::ABILITY)
        ->and($items[0]['active'])->toBe('mailing.*');
});

test('a user who may only manage lists is led to the lists and sees only the lists in the submenu', function (): void {
    $this->actingWithPermissions([MailingPermission::Lists->value]);

    expect(Gate::allows(MailingNavigation::ABILITY))->toBeTrue();

    $this->get(route('mailing.home'))->assertRedirect(route('mailing.lists.index'));

    $navigation = $this->get(route('mailing.lists.index'), [
        'X-Inertia' => 'true',
    ])
        ->assertOk()
        ->json('props.mailingNavigation');

    expect($navigation)->toBe([
        [
            'key' => 'lists',
            'title' => __('mailing::navigation.lists'),
            'href' => route('mailing.lists.index', absolute: false),
            'active' => true,
        ],
    ]);
});

test('the submenu holds every permitted section and marks the current one on its detail pages', function (): void {
    $this->actingWithPermissions([
        MailingPermission::CampaignsEdit->value,
        MailingPermission::Subscribers->value,
    ]);
    $subscription = makeSubscription(makeList());

    $this->get(route('mailing.home'))->assertRedirect(route('mailing.campaigns.index'));

    $navigation = $this->get(route('mailing.subscribers.show', $subscription->subscriber_id), [
        'X-Inertia' => 'true',
    ])
        ->assertOk()
        ->json('props.mailingNavigation');

    expect(collect($navigation)->pluck('key')->all())->toBe([
        'campaigns',
        'subscribers',
        'suppressions',
        'imports',
    ])
        ->and(collect($navigation)->where('active', true)->pluck('key')->all())->toBe([
            'subscribers',
        ]);
});

test('a user without any mailing permission gets no mailing menu item', function (): void {
    $this->actingWithPermissions([]);

    expect(Gate::allows(MailingNavigation::ABILITY))->toBeFalse();

    $this->get(route('mailing.home'))->assertForbidden();
});

test('a permission that opens no page on its own does not show the menu item', function (MailingPermission $permission): void {
    $this->actingWithPermissions([$permission->value]);

    expect(Gate::allows(MailingNavigation::ABILITY))->toBeFalse();

    $this->get(route('mailing.home'))->assertForbidden();
})->with([
    'settings' => [MailingPermission::Settings],
    'send' => [MailingPermission::CampaignsSend],
]);

test('the lists section stays marked on the transport page of a list', function (): void {
    $this->actingWithPermissions([
        MailingPermission::Lists->value,
        MailingPermission::Settings->value,
    ]);
    $list = makeList();

    $navigation = $this->get(route('mailing.lists.transport.edit', $list), [
        'X-Inertia' => 'true',
    ])
        ->assertOk()
        ->json('props.mailingNavigation');

    expect(collect($navigation)->where('active', true)->pluck('key')->all())->toBe([
        'lists',
    ]);
});

test('a host can hide the submenu on every page', function (): void {
    config()->set('mailing.section_navigation', false);

    $this->actingWithPermissions([
        MailingPermission::CampaignsEdit->value,
        MailingPermission::Lists->value,
    ]);

    $this->get(route('mailing.campaigns.index'), [
        'X-Inertia' => 'true',
    ])
        ->assertOk()
        ->assertJsonPath('props.mailingNavigation', []);

    $this->get(route('mailing.home'))->assertRedirect(route('mailing.campaigns.index'));
});

test('sections the host places in its own menu leave the submenu and hide it on their pages', function (): void {
    config()->set('mailing.host_sections', [
        'lists',
        'suppressions',
    ]);

    $this->actingWithPermissions([
        MailingPermission::CampaignsEdit->value,
        MailingPermission::Lists->value,
        MailingPermission::Subscribers->value,
    ]);
    $list = makeList();

    $navigation = $this->get(route('mailing.campaigns.index'), [
        'X-Inertia' => 'true',
    ])
        ->assertOk()
        ->json('props.mailingNavigation');

    expect(collect($navigation)->pluck('key')->all())->toBe([
        'campaigns',
        'subscribers',
        'imports',
    ]);

    foreach ([route('mailing.lists.index'), route('mailing.lists.edit', $list), route('mailing.suppressions.index')] as $url) {
        $this->get($url, [
            'X-Inertia' => 'true',
        ])
            ->assertOk()
            ->assertJsonPath('props.mailingNavigation', []);
    }
});

test('a user who may only open host sections gets no module menu item but still opens those pages', function (): void {
    config()->set('mailing.host_sections', [
        'lists',
        'suppressions',
    ]);

    $this->actingWithPermissions([
        MailingPermission::Lists->value,
    ]);

    expect(Gate::allows(MailingNavigation::ABILITY))->toBeFalse();

    $this->get(route('mailing.home'))->assertForbidden();
    $this->get(route('mailing.lists.index'), [
        'X-Inertia' => 'true',
    ])->assertOk();
});

test('the module menu item is not marked on the pages of host sections', function (): void {
    config()->set('mailing.host_sections', [
        'lists',
        'suppressions',
    ]);

    expect(app(MailingNavigation::class)->menuItem()['active'])->toBe([
        'mailing.home',
        'mailing.campaigns.*',
        'mailing.subscribers.*',
        'mailing.subscriptions.*',
        'mailing.imports.*',
    ]);
});

test('the host sections expose their name, route and permission for a host menu', function (): void {
    config()->set('mailing.host_sections', [
        'suppressions',
        'lists',
        'unknown',
    ]);

    $sections = collect(app(MailingNavigation::class)->hostSections())
        ->map(fn (array $section): array => [
            $section['key'],
            $section['label'],
            $section['route'],
            $section['permission']->value,
        ])
        ->all();

    expect($sections)->toBe([
        [
            'lists',
            'mailing::navigation.lists',
            'mailing.lists.index',
            MailingPermission::Lists->value,
        ],
        [
            'suppressions',
            'mailing::navigation.suppressions',
            'mailing.suppressions.index',
            MailingPermission::Subscribers->value,
        ],
    ]);
});
