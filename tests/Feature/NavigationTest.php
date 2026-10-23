<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
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

test('sections the host places in its own menu leave the submenu', function (): void {
    config()->set('mailing.host_sections', [
        'lists',
        'suppressions',
    ]);

    $this->actingWithPermissions([
        MailingPermission::CampaignsEdit->value,
        MailingPermission::Lists->value,
        MailingPermission::Subscribers->value,
    ]);

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
});

test('the pages of host sections show no submenu', function (string $route): void {
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

    $this->get(route($route, $route === 'mailing.lists.edit' ? $list : []), [
        'X-Inertia' => 'true',
    ])
        ->assertOk()
        ->assertJsonPath('props.mailingNavigation', []);
})->with([
    'mailing.lists.index',
    'mailing.lists.edit',
    'mailing.suppressions.index',
]);

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

test('the module menu item is marked on the module pages and not on the pages of host sections', function (string $route, bool $marked): void {
    config()->set('mailing.host_sections', [
        'lists',
        'suppressions',
    ]);

    $request = Request::create(route($route));
    $request->setRouteResolver(fn (): RoutingRoute => Route::getRoutes()->match($request));

    expect($request->routeIs(...(array) app(MailingNavigation::class)->menuItem()['active']))->toBe($marked);
})->with([
    'campaigns' => [
        'mailing.campaigns.index',
        true,
    ],
    'subscribers' => [
        'mailing.subscribers.index',
        true,
    ],
    'imports' => [
        'mailing.imports.index',
        true,
    ],
    'lists' => [
        'mailing.lists.index',
        false,
    ],
    'new list' => [
        'mailing.lists.create',
        false,
    ],
    'suppressions' => [
        'mailing.suppressions.index',
        false,
    ],
]);

test('a host menu item built from a host section opens with its permission and is refused without it', function (string $key): void {
    config()->set('mailing.host_sections', [
        $key,
    ]);

    $section = app(MailingNavigation::class)->hostSections()[0];

    $this->actingWithPermissions([
        MailingPermission::Settings->value,
    ]);

    $this->get(route($section['route']), [
        'X-Inertia' => 'true',
    ])->assertForbidden();

    $this->actingWithPermissions([
        $section['permission']->value,
    ]);

    $request = Request::create(route($section['route']));
    $request->setRouteResolver(fn (): RoutingRoute => Route::getRoutes()->match($request));

    expect($section['key'])->toBe($key)
        ->and($request->routeIs(...$section['active']))->toBeTrue();

    $this->get(route($section['route']), [
        'X-Inertia' => 'true',
    ])->assertOk();
})->with([
    'lists',
    'subscribers',
    'suppressions',
    'imports',
    'campaigns',
]);

test('an unknown host section is refused instead of silently ignored', function (): void {
    config()->set('mailing.host_sections', [
        'lists',
        'suppresions',
    ]);

    expect(fn (): array => app(MailingNavigation::class)->hostSections())
        ->toThrow(InvalidArgumentException::class, 'Unknown mailing.host_sections: suppresions.');
});
