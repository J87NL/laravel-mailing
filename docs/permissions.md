# Permissions and navigation

## Permissions

The admin routes are guarded by Laravel's `can` middleware with five abilities. They are the cases
of the `J87NL\Mailing\MailingPermission` enum:

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

### What a user without a permission sees

The admin pages show buttons the user cannot use as disabled, with the reason:

- The "Sending" action of a list without `mailing.settings`.
- The delete action of a suppression without `mailing.settings`.
- Campaign actions without `mailing.campaigns.send`. The schedule and test forms are not rendered.

## Navigation

The package pushes these menu items into the config key named by `mailing.navigation_config_key`
(default `j87nl.navigation`), unless an item with the same `key` is already there:

| `key`                  | `label`                           | `route`                      | `icon`    | `permission`             | `order` |
| ---------------------- | --------------------------------- | ---------------------------- | --------- | ------------------------ | ------- |
| `mailing.campaigns`    | `mailing::navigation.campaigns`    | `mailing.campaigns.index`    | `Send`    | `mailing.campaigns.edit` | 195     |
| `mailing.lists`        | `mailing::navigation.lists`        | `mailing.lists.index`        | `Mail`    | `mailing.lists`          | 200     |
| `mailing.subscribers`  | `mailing::navigation.subscribers`  | `mailing.subscribers.index`  | `Contact` | `mailing.subscribers`    | 210     |
| `mailing.suppressions` | `mailing::navigation.suppressions` | `mailing.suppressions.index` | `Ban`     | `mailing.subscribers`    | 220     |
| `mailing.imports`      | `mailing::navigation.imports`      | `mailing.imports.index`      | `Upload`  | `mailing.subscribers`    | 230     |

`label` is a translation key and `icon` a Lucide icon name. Rendering the menu is up to your
application.
