# webx-ui/module-settings

Site settings as a section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel. The
screen is a JSON tree the package ships; the project lays its own tabs and fields over it with a
patch, and saving goes by the same description — a key the tree does not name is not written.

## Requirements

- PHP 8.3+, Laravel 13
- `webx-ui/module-admin`, `webx-ui/module-auth` (the panel and the sign-in), `webx-ui/mcp`

## Install

```bash
composer require webx-ui/module-settings
php artisan migrate
```

The section appears once the front end lists it too: `settings()` from `@webx-ui/module-settings`
in `createAdmin({ modules: [...] })`. Permissions: `settings.view`, `settings.manage`.

## The screen

`settings.index`, two tabs out of the box:

| id              | type       | name                   |
| --------------- | ---------- | ---------------------- |
| `tabs`          | `wx-tabs`  |                        |
| `general`       | `wx-tab`   |                        |
| `general-card`  | `wx-card`  |                        |
| `project-name`  | `wx-input` | `general.project-name` |
| `branding`      | `wx-tab`   |                        |
| `branding-card` | `wx-card`  |                        |
| `logo`          | `wx-media` | `branding.logo`        |
| `mark`          | `wx-media` | `branding.mark`        |

Those three are what the panel wears. `PanelBranding` answers
`WebxUi\Admin\Contracts\BrandingSource` with them, so they reach the frame in the manifest:
the name in the corner (and the logo's `alt`), the logo for the open sidebar, the square mark
for the 56 px rail. A name left empty falls back to `WEBX_ADMIN_TITLE`; a picture this package
cannot turn into an address — `module-media` is what resolves one, and it is not required — is
no picture, and the corner keeps its name.

Those ids are the public contract: a project addresses them from its patch. Add what the site
needs from a service provider that boots after this one — the application's own does:

```php
use WebxUi\Admin\Facades\Screens;

Screens::extend('settings.index', resource_path('screens/settings.json'));
```

```json
[
  {
    "op": "add",
    "target": "tabs",
    "node": {
      "id": "seo",
      "type": "wx-tab",
      "label": "SEO",
      "children": [
        {
          "id": "seo-card",
          "type": "wx-card",
          "children": [
            {
              "id": "robots",
              "type": "wx-textarea",
              "name": "seo.robots-txt",
              "label": "robots.txt"
            }
          ]
        }
      ]
    }
  }
]
```

A field added by a patch is a field the server accepts: validated with the rules its type
declares, per language when `localized`, stored under its `name`.

## Reading a setting

```php
settings('general.project-name');          // the current language, with the site's fallbacks
settings('seo.robots-txt', 'User-agent: *');
settings()->all();                          // everything the screen describes, resolved
```

Values are cached whole and the cache is dropped on save; `WebxUi\Settings\Events\SettingsSaved`
carries the keys that changed.

## MCP

`settings_list`, `settings_get`, `settings_set` (mutating, honours `dry_run`) — the keys and the
rules come from the screen, so an agent can change exactly what an administrator can.

## Languages

en, ru, uk, de, pl, fr, es, it, pt, tr. Only English, Russian and Ukrainian have been read by a
speaker.

## Licence

MIT.
