<?php

declare(strict_types=1);

namespace J87NL\Mailing\Navigation;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\Request;
use J87NL\Mailing\MailingPermission;

class MailingNavigation
{
    public const string ABILITY = 'mailing.access';

    /**
     * @return list<array{key: string, label: string, route: string, active: list<string>, permission: MailingPermission}>
     */
    public function sections(): array
    {
        return [
            [
                'key' => 'campaigns',
                'label' => 'mailing::navigation.campaigns',
                'route' => 'mailing.campaigns.index',
                'active' => [
                    'mailing.campaigns.*',
                ],
                'permission' => MailingPermission::CampaignsEdit,
            ],
            [
                'key' => 'lists',
                'label' => 'mailing::navigation.lists',
                'route' => 'mailing.lists.index',
                'active' => [
                    'mailing.lists.*',
                ],
                'permission' => MailingPermission::Lists,
            ],
            [
                'key' => 'subscribers',
                'label' => 'mailing::navigation.subscribers',
                'route' => 'mailing.subscribers.index',
                'active' => [
                    'mailing.subscribers.*',
                    'mailing.subscriptions.*',
                ],
                'permission' => MailingPermission::Subscribers,
            ],
            [
                'key' => 'suppressions',
                'label' => 'mailing::navigation.suppressions',
                'route' => 'mailing.suppressions.index',
                'active' => [
                    'mailing.suppressions.*',
                ],
                'permission' => MailingPermission::Subscribers,
            ],
            [
                'key' => 'imports',
                'label' => 'mailing::navigation.imports',
                'route' => 'mailing.imports.index',
                'active' => [
                    'mailing.imports.*',
                ],
                'permission' => MailingPermission::Subscribers,
            ],
        ];
    }

    public function allows(Authorizable $user): bool
    {
        return ! is_null($this->firstUrlFor($user));
    }

    public function firstUrlFor(Authorizable $user): ?string
    {
        foreach ($this->sections() as $section) {
            if ($user->can($section['permission']->value)) {
                return route($section['route']);
            }
        }

        return null;
    }

    /**
     * @return list<array{key: string, title: string, href: string, active: bool}>
     */
    public function itemsFor(Authorizable $user, Request $request): array
    {
        $items = [];

        foreach ($this->sections() as $section) {
            if (! $user->can($section['permission']->value)) {
                continue;
            }

            $items[] = [
                'key' => $section['key'],
                'title' => (string) __($section['label']),
                'href' => route($section['route'], absolute: false),
                'active' => $request->routeIs(...$section['active']),
            ];
        }

        return $items;
    }
}
