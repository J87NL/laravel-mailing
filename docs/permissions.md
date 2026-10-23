# Permissions and navigation

## Permissions

The admin routes are guarded by Laravel's `can` middleware with five abilities. They are the cases
of the `J87NL\Mailing\MailingPermission` enum. `mailing.home` uses a sixth ability that the package
defines itself, see [below](#the-access-ability).

| Ability                  | Enum case       | Grants                                                              |
| ------------------------ | --------------- | ------------------------------------------------------------------- |
| `mailing.lists`          | `Lists`         | Lists: index, create, edit, delete                                  |
| `mailing.subscribers`    | `Subscribers`   | Subscribers, suppressions and imports                               |
| `mailing.settings`       | `Settings`      | The transport of a list, and lifting a suppression                  |
| `mailing.campaigns.edit` | `CampaignsEdit` | Campaigns: index, show, create, edit, delete                        |
| `mailing.campaigns.send` | `CampaignsSend` | Schedule, unschedule, pause, resume, cancel, retry and test sends   |

The abilities nest:

- The transport routes need both `mailing.lists` and `mailing.settings`.
- Lifting a suppression needs both `mailing.subscribers` and `mailing.settings`.
- The sending routes need both `mailing.campaigns.edit` and `mailing.campaigns.send`.

The package does not define these abilities. Define them yourself, with gates or with the
permission system of your application:

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use J87NL\Mailing\MailingPermission;

foreach (MailingPermission::names() as $permission) {
    Gate::define($permission, fn (User $user): bool => $user->hasPermission($permission));
}
```

`MailingPermission::names()` returns the five ability names. `label()` returns the translated
description of a case, for example "Send mailings" for `CampaignsSend`.

### Registered permission names

During `register()` the package pushes the five names into the config key named by
`mailing.permissions_config_key` (default `j87nl.permissions`), skipping names that are already
there. An application that builds its role editor from that key picks them up without extra code.

### The access ability

The package defines one ability itself, `mailing.access` (`MailingNavigation::ABILITY`). It passes
when the user may open at least one section of the module: `mailing.campaigns.edit`,
`mailing.lists` or `mailing.subscribers`. `mailing.settings` and `mailing.campaigns.send` alone open
no page, so they do not pass it. It guards `mailing.home` and the menu item below. It is not one of
the five names above and is not pushed into the permissions config key.

### What a user without a permission sees

The admin pages show buttons the user cannot use as disabled, with the reason:

- The "Sending" action of a list without `mailing.settings`.
- The delete action of a suppression without `mailing.settings`.
- Campaign actions without `mailing.campaigns.send`. The schedule and test forms are not rendered.

The menu item only shows when the user may open a section, and the submenu only shows those
sections.

## Navigation

### The host menu item

The package pushes one menu item into the config key named by `mailing.navigation_config_key`
(default `j87nl.navigation`), unless an item with the key `mailing` is already there. Without
[host sections](#sections-in-the-host-menu) it is:

```php
[
    'key' => 'mailing',
    'label' => 'mailing::navigation.mailing',
    'route' => 'mailing.home',
    'icon' => 'Mail',
    'permission' => 'mailing.access',
    'active' => 'mailing.*',
    'order' => 200,
]
```

`label` is a translation key and `icon` a Lucide icon name. `active` is a route pattern: a sidebar
can pass it to `request()->routeIs()` to keep the item marked on every mailing page. Rendering the
menu is up to your application.

`mailing.home` (`GET /admin/mailing`) redirects to the first section the user may open, in the
order of the submenu below. It answers `403` when there is none.

### The submenu

Inside the module every page shows a submenu with the sections the user may open:

| `key`          | Label                                | Route                        | Permission               |
| -------------- | ------------------------------------ | ---------------------------- | ------------------------ |
| `campaigns`    | `mailing::navigation.campaigns`      | `mailing.campaigns.index`    | `mailing.campaigns.edit` |
| `lists`        | `mailing::navigation.lists`          | `mailing.lists.index`        | `mailing.lists`          |
| `subscribers`  | `mailing::navigation.subscribers`    | `mailing.subscribers.index`  | `mailing.subscribers`    |
| `suppressions` | `mailing::navigation.suppressions`   | `mailing.suppressions.index` | `mailing.subscribers`    |
| `imports`      | `mailing::navigation.imports`        | `mailing.imports.index`      | `mailing.subscribers`    |

The sections are defined in `J87NL\Mailing\Navigation\MailingNavigation`. The admin routes share
them with the `ShareMailingNavigation` middleware as the Inertia prop `mailingNavigation`. See
[Admin front-end](admin-frontend.md#the-module-submenu).

### Sections in the host menu

An application with its own settings menu can take sections out of the module and place them there.
List their keys in `mailing.host_sections`:

```php
'host_sections' => [
    'lists',
    'suppressions',
],
```

For those sections:

- They leave the submenu, and their pages show no submenu at all, so they sit in your menu alone.
- `mailing.home`, the `mailing.access` ability and so the `mailing` menu item only count the other
  sections. A user who may only open host sections gets no `mailing` menu item.
- The `mailing` menu item gets a list of route patterns as `active` instead of `mailing.*`:
  `mailing.home` and the patterns of the other sections, so it is not marked on the pages of the
  host sections.
- Their routes, URLs and permissions do not change.

`MailingNavigation::hostSections()` returns those sections with the same shape as `sections()`, so
your menu can use their name, route, permission and route patterns:

```php
[
    'key' => 'lists',
    'label' => 'mailing::navigation.lists',
    'route' => 'mailing.lists.index',
    'active' => ['mailing.lists.*'],
    'permission' => MailingPermission::Lists,
]
```

Gate each item on `permission->value`, the ability its routes enforce. `moduleSections()` returns the
sections that stay in the module. `section_navigation => false` hides the submenu on every page,
for an application that places all sections in its own menu.
