# Settings

`@webx-ui/module-settings` is the section where a site keeps the values that are nobody's
record: the project's name, what goes in `robots.txt`, the default picture for a share preview.
The screen is not written in this package — it is described, as a JSON tree the Composer half
ships, and the project lays its own tabs over it. This page is the front half; everything it
saves lives in `webx-ui/module-settings` on the server, and nothing here works without it.

## The section

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { settings } from '@webx-ui/module-settings'
import '@webx-ui/module-settings/style.css'

createAdmin({
  modules: [media(), settings(), admins()],
})
```

That is the whole of it. The section appears under **System** in the navigation — the group the
server declares for what keeps the panel running, next to the administrators — once
`webx-ui/module-settings` is installed and migrated on the server. Permissions:
`settings.view` to open it, `settings.manage` to save.

The page loads the values, draws the screen `settings.index` through `WxScreen`, and has one
button of its own: Save. A 422 from the server lands under the field it names.

## The screen, and what to patch

Out of the box the screen is two tabs — what the panel is called and what it wears. That is
deliberately little: the rest of a site's settings are the site's own, and a module cannot
guess them.

| id              | type       | name                   | what it is                       |
| --------------- | ---------- | ---------------------- | -------------------------------- |
| `tabs`          | `wx-tabs`  |                        | The tab strip                    |
| `general`       | `wx-tab`   |                        | "General"                        |
| `general-card`  | `wx-card`  |                        | The card inside it               |
| `project-name`  | `wx-input` | `general.project-name` | The project's name, per language |
| `branding`      | `wx-tab`   |                        | "Branding"                       |
| `branding-card` | `wx-card`  |                        | The card inside it               |
| `logo`          | `wx-media` | `branding.logo`        | The logo for the open sidebar    |
| `mark`          | `wx-media` | `branding.mark`        | The square mark for the rail     |

## Whose panel this is

The three branding values are the only settings the panel reads about itself. They travel in
the manifest, so the frame wears them from the first paint:

- `general.project-name` becomes `manifest.title` — the text in the corner when there is no
  logo, and the logo's `alt` when there is. Left empty it falls back to `WEBX_ADMIN_TITLE`.
- `branding.logo` is shown in the corner of the open sidebar, at 28 px tall and whatever width
  that leaves it.
- `branding.mark` is shown on the 56 px rail, above the button that opens the sidebar again.
  Left empty, the rail looks the way it always has: two pictures rather than one and a
  cropping rule, because a wordmark cut to a square is its first two letters.

On the server this is one binding, `WebxUi\Admin\Contracts\BrandingSource`, which
`module-settings` answers. The frame asks whoever is bound to it and wears its configured
title when nobody is — `module-admin` neither knows nor requires the section that holds a
logo. A project that keeps its brand somewhere else binds its own implementation.

The two picture fields are `wx-media`, which means `webx-ui/module-media` resolves them into
addresses. Without that package installed the values stay library paths the panel cannot read,
and the corner keeps its name — the same tolerance `module-seo` has for its `og:image`.

Those ids are the public contract. A project addresses them from a patch — registered on the
server, in a provider that boots after the module's, which the application's own does:

```php
// app/Providers/AppServiceProvider.php
use WebxUi\Admin\Facades\Screens;

public function boot(): void
{
    Screens::extend('settings.index', resource_path('screens/settings.json'));
}
```

```json
[
  {
    "op": "add",
    "target": "tabs",
    "node": {
      "id": "contacts",
      "type": "wx-tab",
      "label": "Contacts",
      "children": [
        {
          "id": "contacts-card",
          "type": "wx-card",
          "children": [
            {
              "id": "office-photo",
              "type": "wx-media",
              "name": "contacts.photo",
              "label": "Photograph of the office",
              "slot": "sidebar",
              "props": { "aspect": "16/9", "accept": "image" }
            },
            {
              "id": "address",
              "type": "wx-textarea",
              "name": "contacts.address",
              "label": "Postal address",
              "localized": true,
              "props": { "rows": 4 }
            }
          ]
        }
      ]
    }
  }
]
```

The picture sits in the card's `sidebar` slot — `slot` names any named slot the parent has.

A server-side patch does two things at once: it draws the fields, and it opens their keys for
writing. Saving goes by the same tree — a key the tree does not name is dropped, and every value
is checked with the rules its type declares, per language when the field is `localized`. That
is why the tab above is a patch and not part of the module: a project that has no office does not
get a field for its photograph, and a project that has a map gets a `map` field the module has
never heard of.

A module may patch this screen too, and one does: installing
[`@webx-ui/module-seo`](/guide/seo) adds the SEO tab, with the same mechanism and the same
rules. Whoever boots last has the last word, and the application always boots last.

A client-side patch — `createAdmin({ screens: { 'settings.index': [...] } })` — changes only what
is drawn: a placeholder, a row count, a project component under a type the server stores as it
is. It cannot open a key for writing. What is saved is the server's decision.

## Reading a value on the site

```php
settings('general.project-name');            // the current language, with the site's fallbacks
settings('contacts.address', 'Nowhere');
settings('contacts.photo')['url'] ?? null;   // a media field resolves to its address
settings()->all();
```

Values are cached whole and the cache is dropped on save; `WebxUi\Settings\Events\SettingsSaved`
carries the keys that changed, for a site that builds something out of them.

## MCP

`settings_list`, `settings_get` and `settings_set` (mutating, honours `dry_run`) come with the
module. The keys and the rules are read from the screen, so an agent can change exactly what an
administrator can — patched fields included — and nothing else.

## Talking to it directly

```ts
import { createSettingsApi } from '@webx-ui/module-settings'

const api = createSettingsApi(useAdmin())
const values = await api.load()
await api.save({ ...values, 'general.project-name': { en: 'Acme' } })
```

`GET /api/cms/settings` answers `{ data: { values } }`; `PUT` takes `{ values }` and answers
with what it kept.
