# webx-ui/module-settings

The site's settings: one key–value table, the section «Settings» under System in the panel, the
`settings()` helper the site reads them with, and the MCP tools `settings_*`. Out of the box it
holds what the panel is called and wears, and the house rules for content agents; every other setting is a field the site (or
another module) adds to its screen with a patch. Screens and patches are `webx-ui/module-admin`,
picture fields `webx-ui/module-media`, the languages of a localized value
`webx-ui/localization` — read their guides for those.

## What it owns

- **Table** `cms_settings` (`WebxUi\Settings\Models\Setting`): `key` (unique), `value` (JSON).
  Read whole and cached under `webx-settings.cache.key`; dropped on every save, and by a reset of the `settings` cache group («System → Cache», `webx:cache:reset --group=settings`).
- **Service** `WebxUi\Settings\Settings` — `get()`, `all()`, `raw()`, `keys()`, `save()`,
  `forget()`; helper `settings('key', $default)`, `settings()` for the service. Event
  `SettingsSaved` carries the keys that changed.
- **Panel screen** `settings.index`, nodes `tabs`, `general`, `general-card`, `project-name`
  (`general.project-name`), `branding`, `branding-card`, `logo` (`branding.logo`), `mark`
  (`branding.mark`). API `GET` / `PUT` under `/api/cms/settings`.
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
  A list (`wx-repeater`) carries `item` in `settings_list`/`settings_get`: the keys of one row and
  what each takes; a row key the screen does not name is refused, not dropped.
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
  `contacts-addresses-card`, `contacts-hours-card`, `contacts-channels-card`): keys `contacts.phones`,
  `emails`, `addresses`, `hours` (closing earlier than opening runs past midnight),
  `hours-exceptions`, `timezone` (empty — `app.timezone`), `messengers`, `socials`. Read through
  `contacts()` (`WebxUi\Settings\Contacts\Contacts`): `phones()`, `primaryPhone()` (`number`,
  `e164`, `href`), `emails()`, `addresses()`, `primaryAddress()`, `hours()` (`openNow()`,
  `status()`, `rows()`, `upcoming()`), `messengers()`, `chats()`, `socials()`. A number without its country code, a link that is not one, an unknown time zone
  are refused on save — panel and MCP alike.
  `php artisan webx:settings:contacts --from=<key> [--as=phones|emails|addresses] [--dry-run]`
  moves contacts kept under a key of the site's own; `webx-settings.contacts.legacy` names such
  keys so they are read until then.
  Which sections the tab draws (`phones`, `emails`, `addresses`, `hours` with special dates and the
  time zone, `messengers`, `socials`) is the theme's list joined with the packages' and the
  project's (below); `webx-settings.contacts.sections` — null: `phones, emails, addresses` only
  where the theme listed none; a list is added like a package's. One left out is hidden
  (`visible: false`), not cut out: `contacts()` still reads it and a patch aimed at its card still
  applies, but neither the form nor `settings_*` offer its keys. A misspelt section stops the boot.
- **Tabs only where they are used** (`WebxUi\Settings\Sections\SettingsSections`): `branding` and
  `contacts` are offered only when something on the site uses them and their field types exist.
  Contacts are declared by whoever prints them — a theme under `settings` of its manifest
  (`"settings": {"contacts": ["phones", "hours"]}`, `true` for the whole tab; the nearest layer
  that names it decides, so a local theme's list replaces `theme-default`'s phones, emails,
  addresses, hours, socials; doctor names who brought each section), a package under
  `extra.webx.settings` of its composer.json (same shape), the project in `webx-settings.uses`
  (`['contacts']` or `['contacts' => ['phones', 'hours']]`). Branding is used by the panel itself
  and needs `wx-media` — without `webx-ui/module-media` it is hidden. Widgets and SEO read
  contacts when they are there and declare nothing. A tab not offered is hidden (`visible: false`)
  by a closure patch laid before the project's: no tab in the panel, no keys in `settings_list`,
  `settings_get`/`settings_set` answer «No setting is named…», no demo for it; stored values stay,
  `contacts()` and the panel's logo still read them, and the tab comes back with them once a user
  declares it. **Transition** (removed in the next minor): a site whose theme chain has no
  `settings` key at all, or a site with no theme but contacts already stored, keeps Contacts.
  `webx:doctor` — «Settings: branding / contacts» — says who uses each tab, warns when a declared
  tab lacks its field type and when contacts are kept only because they are stored.
- Demo content (`resources/demo`), the content rules and the contacts included. A theme with
  `demo/settings/settings.json` seeds that file instead. Phones in demos and examples are
  `+800 …` (`+800 1234 5678`): a number of no country that validates — `+00…` does not.

### Permissions

| Permission        | Opens                                                                                            |
| ----------------- | ------------------------------------------------------------------------------------------------ |
| `settings.view`   | both screens read-only (`GET settings`, `GET settings/content`), `settings_list`, `settings_get` |
| `settings.manage` | the same, plus saving them (`PUT settings`, `PUT settings/content`) and `settings_set`           |

A route or screen node the site adds next to the module — a field patched onto `settings.index`
is saved by the same `PUT` — asks the same: `cms.can:settings.view,settings.manage` to read,
`cms.can:settings.manage` to write.

## Change it without forking

| You want                                    | Do this                                                                                                                                         |
| ------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| A setting of the site's own                 | a patch: `Screens::extend('settings.index', resource_path('screens/settings.json'))` in `AppServiceProvider::boot()`, adding a tab under `tabs` |
| Read it on the site                         | `settings('<tab>.<field>', 'fallback')` (e.g. `settings('branding.logo')`) — current language, media resolved to its address                    |
| A setting per language                      | `"localized": true` on the field in the patch                                                                                                   |
| A content rule of the site's own            | a patch on `settings.content` adding a field under `content-card` named `content.<name>`; it comes out in `more`                                |
| A phone, an e-mail typed once               | «Settings» → «Contacts»; in a template `contacts()->primaryPhone()`, in content a shortcode row holding the value — `[phone]`                   |
| Hours, messengers or socials on the tab     | list them in `webx-settings.contacts.sections` (publish `webx-settings-config`), not a screen patch                                             |
| Fewer sections than the parent theme prints | list the site's own in `settings.contacts` of `theme/theme.json` — it replaces the parent's list                                                |
| The site's own views print contacts         | `'uses' => ['contacts']` in `config/webx-settings.php`; a theme declares `"settings": {"contacts": [...]}` in its manifest instead              |
| A package that prints contacts              | `"extra": {"webx": {"settings": {"contacts": ["phones"]}}}` in its composer.json                                                                |
| React when settings change                  | listen to `WebxUi\Settings\Events\SettingsSaved`                                                                                                |
| The panel's brand from somewhere else       | bind your own `WebxUi\Admin\Contracts\BrandingSource`                                                                                           |
| No cache while debugging                    | `WEBX_SETTINGS_CACHE=false`                                                                                                                     |
| Other words in the panel                    | `php artisan vendor:publish --tag=webx-settings-lang`                                                                                           |
| Publish the config                          | `php artisan vendor:publish --tag=webx-settings-config`                                                                                         |

A patch addresses nodes by the `id`s above; a patch whose target is gone throws when the screen is first built. A
server-side patch both draws a field and opens its key for writing; a client-side patch in
`createAdmin({ screens })` only changes what is drawn.

## Do not

- Do not edit anything in `vendor/webx-ui/module-settings` or copy it into the site. Every row
  above is the supported way; if none fits, the package is missing a seam — say so.
- Do not insert rows into `cms_settings` for a key the screen does not describe: the panel and
  `settings_set` will neither show nor write it. Add the field with a screen patch first; the
  key exists from then on.
- Do not write values with SQL: the cache is not dropped and `SettingsSaved` never fires. Save
  through the panel, `settings_set` or `Settings::save()` with validated values.
- Do not write the content rules into the root `AGENTS.md` or a project file: they are for the
  agent that writes content through MCP, and the editors keep them in the panel. Do not list
  the site's languages in them either — the resource takes them from `webx-ui/localization`.
- Do not bring a hidden tab back with a screen patch (`set visible: true`): declare that the site
  uses it — the patch would not survive the next theme or package that changes what is used.
- Do not keep secrets (API keys, passwords) here: every value with `settings.view` is readable in
  the panel and through `settings_get`. Use `.env` and config.
- Do not put the site's own settings in a config file the editors cannot reach when they should
  change them: a screen patch gives them a field, a validation rule and a language per value.

## Check your work

- With MCP: `settings_list` shows the new key; `settings_set` with `dry_run: true` answers
  `would_change` without writing; then `settings_get` gives both the stored and the resolved value.
- Read the resource `settings://content-rules`: the languages and the rules as the agent sees them.
- Open the section in the panel: the patched tab is there, a bad value is refused under its field.
- `php artisan webx:doctor` — what is misconfigured on the site; «Settings: contacts» and
  «Settings: branding» say whether each tab is shown and who uses it.

## Read more

- [README.md](README.md) in this directory — the PHP API.
- Guide: https://webx-ui.github.io/webx-ui/guide/settings
- Screens and patches: https://webx-ui.github.io/webx-ui/guide/screens
