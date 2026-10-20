# Admin front-end

The admin is a set of Inertia pages in `resources/js/pages` of this package. The package ships raw
`.vue` and `.ts` files that your application compiles. The public pages are Blade and need none of
this.

## Pages

| Component name                 | File                              | Rendered by                          |
| ------------------------------ | --------------------------------- | ------------------------------------ |
| `mailing/Form`                 | `pages/Form.vue`                  | Every create and edit form           |
| `mailing/lists/Index`          | `pages/lists/Index.vue`           | `mailing.lists.index`                |
| `mailing/subscribers/Index`    | `pages/subscribers/Index.vue`     | `mailing.subscribers.index`          |
| `mailing/subscribers/Show`     | `pages/subscribers/Show.vue`      | `mailing.subscribers.show`           |
| `mailing/suppressions/Index`   | `pages/suppressions/Index.vue`    | `mailing.suppressions.index`         |
| `mailing/imports/Index`        | `pages/imports/Index.vue`         | `mailing.imports.index`              |
| `mailing/campaigns/Index`      | `pages/campaigns/Index.vue`       | `mailing.campaigns.index`            |
| `mailing/campaigns/Show`       | `pages/campaigns/Show.vue`        | `mailing.campaigns.show`             |

The component names come from `mailing.pages`. Point a key at a page of your own to replace it.

## 1. Install the form and table builders

The pages import `@form-builder/...` and `@table-builder/...`. Set up both packages as their own
documentation describes, including their Vite aliases.

## 2. Add the Vite alias

The pages import their own helpers through `@mailing`. Add the alias to `vite.config.ts`:

```ts
import path from 'path';
import { defineConfig } from 'vite';

export default defineConfig({
    resolve: {
        alias: {
            '@mailing': path.resolve(__dirname, 'vendor/j87nl/laravel-mailing/resources/js'),
        },
    },
});
```

For type checking, map the same path in `tsconfig.json`:

```json
{
    "compilerOptions": {
        "paths": {
            "@mailing/*": ["./vendor/j87nl/laravel-mailing/resources/js/*"]
        }
    }
}
```

## 3. Resolve the pages and give them a layout

Add the package pages to your Inertia resolver under the `mailing/` prefix. The pages set no layout
of their own and render no breadcrumbs or toasts. Give them a persistent layout in the resolver;
that layout shows the breadcrumbs and the toasts described
[below](#what-the-pages-expect-from-your-application). For example, with your own `AdminLayout.vue`:

```ts
import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import AdminLayout from './layouts/AdminLayout.vue';

type PageModule = { default: DefineComponent };

const pages = import.meta.glob<PageModule>('./pages/**/*.vue');
const mailingPages = import.meta.glob<PageModule>(
    '../../vendor/j87nl/laravel-mailing/resources/js/pages/**/*.vue',
);

createInertiaApp({
    resolve: async (name) => {
        if (name.startsWith('mailing/')) {
            const page = await mailingPages[
                `../../vendor/j87nl/laravel-mailing/resources/js/pages/${name.slice('mailing/'.length)}.vue`
            ]();

            page.default.layout = page.default.layout || AdminLayout;

            return page;
        }

        return pages[`./pages/${name}.vue`]();
    },
    // ...
});
```

## 4. Add the Tailwind source

The pages use Tailwind classes, partly in `ui.ts`. Add both file types to `resources/css/app.css`:

```css
@source '../../vendor/j87nl/laravel-mailing/resources/js/**/*.{vue,ts}';
```

## What the pages expect from your application

### A `$t()` translation helper

The templates call a global `$t()` with the package's translation keys, for example
`$t('mailing::campaigns.index.title')`. Some calls pass replacements in Laravel's `:placeholder`
style, for example `$t('mailing::campaigns.show.recipients', { count: '120' })` for the line
`:count recipients`. Your application must provide `$t`, make the `mailing::` keys available to it
and apply those replacements. The translations live in `lang/en` and `lang/nl` of this package.

### A `confirm` provider

Destructive actions (delete, cancel, unsubscribe, retry) ask for confirmation through a function
injected under the key `confirm`:

```ts
type MailingConfirm = (message: string) => Promise<boolean>;
```

Provide it once in your app, for example with your own dialog:

```ts
app.provide('confirm', (message: string): Promise<boolean> => Promise.resolve(window.confirm(message)));
```

Without a provider every confirmation is declined, so those actions never run.

### Breadcrumbs in the layout

Every page sets a `breadcrumbs` layout prop (`{ title: string; href: string }[]`) with Inertia's
`setLayoutProps()`. The pages do not render them; your layout must read the prop and show them.

### Toasts in the layout

Success messages are sent as Inertia flash data under `mailing.toast_flash_key` (default `toast`):

```php
['type' => 'success', 'message' => 'Saved.']
```

The pages do not render them; your layout must read that flash key and show the message.

Errors come back as regular validation errors. The pages show those for their own keys (`list`,
`campaign`, `recipients`, `import`) above the content.

## Campaign page polling

While a campaign has the status `sending`, its page reloads `campaign`, `counts`, `actions` and
`sends` every 15 seconds.

## TypeScript types

`@mailing/types` exports the prop types of the pages:

| Type                    | Describes                                         |
| ----------------------- | ------------------------------------------------- |
| `MailingBreadcrumb`     | `{ title, href }`                                 |
| `MailingSubscriber`     | The subscriber on the subscriber page             |
| `MailingHistoryEntry`   | One consent record in the subscriber history      |
| `MailingAction`         | A campaign action button                          |
| `MailingPreflightItem`  | One pre-flight check result                       |
| `MailingCampaign`       | The campaign summary on the campaign page         |
