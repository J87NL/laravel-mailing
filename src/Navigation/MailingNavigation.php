<?php

declare(strict_types=1);

namespace J87NL\Mailing\Navigation;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\Request;
use InvalidArgumentException;
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

    /**
     * @return list<array{key: string, label: string, route: string, active: list<string>, permission: MailingPermission}>
     */
    public function hostSections(): array
    {
        $keys = $this->hostSectionKeys();

        return array_values(array_filter(
            $this->sections(),
            fn (array $section): bool => in_array($section['key'], $keys, true),
        ));
    }

    /**
     * @return list<array{key: string, label: string, route: string, active: list<string>, permission: MailingPermission}>
     */
    public function moduleSections(): array
    {
        $keys = $this->hostSectionKeys();

        return array_values(array_filter(
            $this->sections(),
            fn (array $section): bool => ! in_array($section['key'], $keys, true),
        ));
    }

    public function allows(Authorizable $user): bool
    {
        return ! is_null($this->firstUrlFor($user));
    }

    public function firstUrlFor(Authorizable $user): ?string
    {
        foreach ($this->moduleSections() as $section) {
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
        if (! $this->showsSectionNavigation($request)) {
            return [];
        }

        $items = [];

        foreach ($this->moduleSections() as $section) {
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

    /**
     * @return array{key: string, label: string, route: string, icon: string, permission: string, active: string|list<string>, order: int}
     */
    public function menuItem(): array
    {
        return [
            'key' => 'mailing',
            'label' => 'mailing::navigation.mailing',
            'route' => 'mailing.home',
            'icon' => 'Mail',
            'permission' => self::ABILITY,
            'active' => $this->menuItemActivePatterns(),
            'order' => 200,
        ];
    }

    /**
     * @return string|list<string>
     */
    private function menuItemActivePatterns(): string|array
    {
        if ($this->hostSectionKeys() === []) {
            return 'mailing.*';
        }

        $patterns = [
            'mailing.home',
        ];

        foreach ($this->moduleSections() as $section) {
            array_push($patterns, ...$section['active']);
        }

        return $patterns;
    }

    private function showsSectionNavigation(Request $request): bool
    {
        if (! config('mailing.section_navigation', true)) {
            return false;
        }

        foreach ($this->hostSections() as $section) {
            if ($request->routeIs(...$section['active'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function hostSectionKeys(): array
    {
        $configured = config('mailing.host_sections', []);

        if (! is_array($configured)) {
            return [];
        }

        $known = array_column($this->sections(), 'key');
        $unknown = array_values(array_diff($configured, $known));

        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf('Unknown mailing.host_sections: %s. Known sections: %s.', implode(', ', $unknown), implode(', ', $known)));
        }

        return array_values(array_intersect($known, $configured));
    }
}
