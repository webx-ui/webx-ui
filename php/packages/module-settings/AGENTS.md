# webx-ui/module-settings

The site's settings: one key–value table, the section «Settings» under System in the panel, the
`settings()` helper the site reads them with, and the MCP tools `settings_*`. Out of the box it
holds what the panel is called and wears, and the house rules for content agents; every other setting is a field the site (or
another module) adds to its screen with a patch. Screens and patches are `webx-ui/module-admin`,
picture fields `webx-ui/module-media`, the languages of a localized value
`webx-ui/localization` — read their guides for those.

## What it owns

- **Table** `cms_settings` (`WebxUi\Settings\Models\Setting`): `key` (unique), `value` (JSON).
  Read whole and cached under `webx-settings.cache.key`; dropped on every save.
- **Service** `WebxUi\Settings\Settings` — `get()`, `all()`, `raw()`, `keys()`, `save()`,
  `forget()`; helper `settings('key', $default)`, `settings()` for the service. Event
  `SettingsSaved` carries the keys that changed.
- **Panel screen** `settings.index`, nodes `tabs`, `general`, `general-card`, `project-name`
  (`general.project-name`), `branding`, `branding-card`, `logo` (`branding.logo`), `mark`
  (`branding.mark`). API `GET` / `PUT` under `/api/cms/settings`; permissions `settings.view`,
  `settings.manage`.
- **Content screen** `settings.content`, nodes `content-card`, `tone` (`content.tone`), `donts`
  (`content.donts`), `notes` (`content.notes`); API `GET` / `PUT` `/api/cms/settings/content`.
  The panel shows it on `module-auth`'s «Connect an agent» page, found by the manifest's
  `content_screen`.
- **Branding**: binds `WebxUi\Admin\Contracts\BrandingSource` to `PanelBranding`, so the three
  values above become the panel's title, logo and rail mark.
- **MCP** tools `settings_list`, `settings_get`, `settings_set`; scopes `settings:read`,
  `settings:write`. Only keys the screen names can be written, checked with the screen's rules.
  `value: null` (or the string `"null"`) clears a key of any type — every language of a
  localized one; `{"de": null}` empties one language — and the site falls back to its default.
- **Content rules** `WebxUi\Settings\ContentRules`: the `content.*` keys plus the site's
  languages (from `webx-ui/localization`), served as the MCP resource `settings://content-rules`
  (`languages`, `primary`, `tone`, `donts`, `notes`, `more`, `empty`); the MCP server's
  instructions point every agent at it.
- **Data shortcodes** `WebxUi\Settings\DataShortcodes`: the tab `shortcodes` of `settings.index`
  (repeater `shortcodes-data`, key `shortcodes.data`, rows of `name`, `source` — `setting` or
  `value` — `key`, `value`; a row saved before `source` existed reads a filled-in `key` as
  `setting`, and a `key` the settings do not have is refused on save) feeds the
  shortcode registry of `webx-ui/module-admin` — `[phone]` reads a setting or its own value, a
  phone prints as a `tel:` link, an e-mail as `mailto:`. Listed in `settings://content-rules`
  under `shortcodes`.
- **Contacts** — the tab `contacts` of `settings.index` (cards `contacts-phones-card`,
  `contacts-addresses-card`, `contacts-hours-card`, `contacts-channels-card`): `contacts.phones`
  (rows `number`, `label` per language, `messengers` — whatsapp, telegram, viber, signal —
  `primary`), `contacts.emails` (`email`, `label`), `contacts.addresses` (`address` per language,
  `latitude`, `longitude`, `map`, `primary`), `contacts.hours` (`days` mon…sun, `opens`, `closes`;
  closing earlier than opening runs past midnight), `contacts.hours-exceptions` (`date`, `label`,
  `closed`, `opens`, `closes`), `contacts.timezone` (Region/City; empty — `app.timezone`),
  `contacts.messengers` (`channel`, `url`, `label`), `contacts.socials` (`network`, `url`,
  `label` for `other`). Read through `contacts()` (`WebxUi\Settings\Contacts\Contacts`):
  `phones()`, `primaryPhone()` (`number` as typed, `e164`, `href` — the `tel:` link), `emails()`,
  `addresses()`, `primaryAddress()` (`mapUrl()`), `hours()` (`openNow()`, `status()`, `rows()`,
  `upcoming()`), `messengers()`, `chats()`, `socials()`. A number without its country code, a
  link that is not one, an unknown time zone are refused on save — panel and MCP alike.
  `php artisan webx:settings:contacts --from=<key> [--as=phones|emails|addresses] [--dry-run]`
  moves contacts kept under a key of the site's own; `webx-settings.contacts.legacy` names such
  keys so they are read until then.
- Demo content (`resources/demo`), the content rules and the contacts included.

## Change it without forking

| You want                              | Do this                                                                                                                                         |
| ------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| A setting of the site's own           | a patch: `Screens::extend('settings.index', resource_path('screens/settings.json'))` in `AppServiceProvider::boot()`, adding a tab under `tabs` |
| Read it on the site                   | `settings('<tab>.<field>', 'fallback')` (e.g. `settings('branding.logo')`) — current language, media resolved to its address                    |
| A setting per language                | `"localized": true` on the field in the patch                                                                                                   |
| A content rule of the site's own      | a patch on `settings.content` adding a field under `content-card` named `content.<name>`; it comes out in `more`                                |
| A phone, an e-mail typed once         | «Settings» → «Contacts»; in a template `contacts()->primaryPhone()`, in content a shortcode row holding the value — `[phone]`                   |
| React when settings change            | listen to `WebxUi\Settings\Events\SettingsSaved`                                                                                                |
| The panel's brand from somewhere else | bind your own `WebxUi\Admin\Contracts\BrandingSource`                                                                                           |
| No cache while debugging              | `WEBX_SETTINGS_CACHE=false`                                                                                                                     |
| Other words in the panel              | `php artisan vendor:publish --tag=webx-settings-lang`                                                                                           |
| Publish the config                    | `php artisan vendor:publish --tag=webx-settings-config`                                                                                         |

A patch addresses nodes by the `id`s above; a patch whose target is gone throws when the screen is first built. A
server-side patch both draws a field and opens its key for writing; a client-side patch in
`createAdmin({ screens })` only changes what is drawn.

## Do not

- Do not edit anything in `vendor/webx-ui/module-settings`, and do not copy the package into the
  site. Every row above is the supported way; if none fits, the package is missing a seam — say
  so instead of working around it.
- Do not insert rows into `cms_settings` for a key the screen does not describe: the panel and
  `settings_set` will neither show nor write it. Add the field with a screen patch first; the
  key exists from then on.
- Do not write values with SQL: the cache is not dropped and `SettingsSaved` never fires. Save
  through the panel, `settings_set` or `Settings::save()` with validated values.
- Do not write the content rules into the root `AGENTS.md` or a project file: they are for the
  agent that writes content through MCP, and the editors keep them in the panel. Do not list
  the site's languages in them either — the resource takes them from `webx-ui/localization`.
- Do not keep secrets (API keys, passwords) here: every value with `settings.view` is readable in
  the panel and through `settings_get`. Use `.env` and config.
- Do not put the site's own settings in a config file the editors cannot reach when they should
  change them: a screen patch gives them a field, a validation rule and a language per value.

## Check your work

- With MCP: `settings_list` shows the new key; `settings_set` with `dry_run: true` answers
  `would_change` without writing; then `settings_get` gives both the stored and the resolved value.
- Read the resource `settings://content-rules`: the languages and the rules as the agent sees them.
- Open the section in the panel: the patched tab is there, a bad value is refused under its field.
- `php artisan webx:doctor` — what is misconfigured on the site.

## Read more

- [README.md](README.md) in this directory — the PHP API.
- Guide: https://webx-ui.github.io/webx-ui/guide/settings
- Screens and patches: https://webx-ui.github.io/webx-ui/guide/screens
