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

The tab «Shortcodes» is the list of data shortcodes: a name content writes in brackets —
`[phone]` — reading a setting or holding its own value. See [Shortcodes](/guide/shortcodes).

### The Contacts tab, and which parts of it a site draws

The tab «Contacts» holds what the site's widgets, map and SEO markup print — read on the site
through `contacts()`. Most sites print only some of it, and a field the site never shows is a
question an editor asks every time. So the server draws only the sections named in
`config/webx-settings.php`:

```php
'contacts' => [
    // the default: what nearly every site prints
    'sections' => ['phones', 'emails', 'addresses'],
    // everything: 'phones', 'emails', 'addresses', 'hours', 'messengers', 'socials'
],
```

| Section      | What it draws                                     | Card                      |
| ------------ | ------------------------------------------------- | ------------------------- |
| `phones`     | `contacts.phones`                                 | `contacts-phones-card`    |
| `emails`     | `contacts.emails`                                 | `contacts-phones-card`    |
| `addresses`  | `contacts.addresses`                              | `contacts-addresses-card` |
| `hours`      | `contacts.hours`, its special dates, the timezone | `contacts-hours-card`     |
| `messengers` | `contacts.messengers`                             | `contacts-channels-card`  |
| `socials`    | `contacts.socials`                                | `contacts-channels-card`  |

A section left out is hidden (`visible: false`), not cut out of the screen: a card with none of
its sections drawn is hidden whole, an empty list hides the tab. `contacts()` returns whatever is
stored, but the form and the MCP tools no longer offer a hidden key. A site that prints the hours
(`<x-webx-hours>`, the header's socials) lists `hours` or `socials` here — or the theme that prints
them declares them (below). Because the nodes stay, a patch from before this setting that removed
`contacts-hours-card` or `contacts-channels-card` still applies; it is just no longer needed. A
misspelt section stops the boot with the known names.

### Tabs only where something uses them

Branding and Contacts are offered only on a site that uses them. A task tracker with no public
theme prints no contacts, and without the file library the logo fields cannot even be drawn —
so it gets neither tab. Whoever prints contacts says so, in the place it already describes
itself:

| Who         | Where                                                         | Example                                         |
| ----------- | ------------------------------------------------------------- | ----------------------------------------------- |
| a theme     | `settings` of its manifest (`extra.webx.theme`, `theme.json`) | `"settings": {"contacts": ["phones", "hours"]}` |
| a package   | `extra.webx.settings` of its composer.json                    | `"settings": {"contacts": true}`                |
| the project | `uses` in `config/webx-settings.php`                          | `'uses' => ['contacts']`                        |

A tab is `true` — the whole tab, sections as configured — or the list of its sections the user
prints. A theme's list comes from the layer of its chain nearest to the site that names the tab:
a local theme that lists its own sections replaces its parent's, one that says nothing inherits
them. The lists of packages and of the project are added to the theme's — they print what they
print whatever the theme does. `contacts.sections` is `null` by default: phones, emails and
addresses where the theme has not listed its sections, nothing more where it has; a list there is
added like a package's. `php artisan webx:doctor` names who brought each section.
`theme-default` declares phones, emails, addresses, hours and socials, so a site on it keeps the
tab as it was. Widgets and SEO markup print contacts only when there are some and declare
nothing — otherwise they would bring the tab to every site.

Branding is used by the panel itself — its corner and the consent screen — so nobody declares
it; it is hidden only when `wx-media` is not registered (no `webx-ui/module-media`). Declaring it
still makes sense: then `webx:doctor` warns that the type is missing instead of staying quiet.

A tab nobody uses is hidden the same way as a section: the panel has no such tab, `settings_list`
has no such keys, `settings_get` / `settings_set` answer «No setting is named…», and the demo
leaves it empty. What was stored stays — `contacts()` and the panel's logo read it, saving the
form does not touch it — and the tab comes back with its values when a user declares it.

**Transition, until the next minor.** A site whose theme chain says nothing about `settings` —
no key at all, as every theme written before this — keeps Contacts; `{}` says the theme prints
none. A site with no theme but with contacts already stored keeps them too, and `webx:doctor`
asks it to add `'uses' => ['contacts']`. `php artisan webx:doctor` lists «Settings: branding» and
«Settings: contacts» with who uses each.

In demos and examples a phone is an `+800 …` number (`+800 1234 5678`) — international, of no
country, and valid; `+00 000 000-00-00` is refused like any number without a country code.

## The screen, and what to patch

Out of the box the screen is four tabs — what the panel is called, what it wears, the contacts
(above) and the shortcodes. That is deliberately little: the rest of a site's settings are the
site's own, and a module cannot guess them.

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
| `contacts`      | `wx-tab`   |                        | "Contacts" — its cards above     |
| `shortcodes`    | `wx-tab`   |                        | "Shortcodes"                     |

The content rules are a screen of their own, `settings.content`, shown on the page where agents
are connected (`module-auth`'s «Connect an agent») rather than among the settings — agents are
who reads them. Stored, cached and read like any other setting; `GET` / `PUT`
`/api/cms/settings/content`.

| Node           | Type          | Key             | What it is                         |
| -------------- | ------------- | --------------- | ---------------------------------- |
| `content-card` | `wx-col`      |                 | The fields; the card is the page's |
| `tone`         | `wx-textarea` | `content.tone`  | The tone of voice                  |
| `donts`        | `wx-textarea` | `content.donts` | What never to say, one per line    |
| `notes`        | `wx-textarea` | `content.notes` | Anything else an agent should know |

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

### The way to the site

The name in the head of the sidebar is a link to the site, with a small ↗ after it; on the rail
the ↗ stands alone under the button that opens the sidebar, with "Open the site" as its tooltip,
and the account menu has an "Open the site" entry as well. All three open a new tab, and all
three go to `manifest.siteUrl`: the home page in the site's **main** language, whatever language
the administrator reads the panel in — with `webx-ui/routing` installed it carries the same
language prefix every other link on the site does, without it it is the application's root.

A project that wants it elsewhere, or not at all, says so in `createAdmin()`:

```ts
createAdmin({
  siteLink: 'https://www.example.test/', // instead of the home page the server names
  // siteLink: false,                    // no link in the head, on the rail or in the menu
})
```

A brand component of the project's own (`createAdmin({ brand })`) is not made into a link — it
may hold links of its own — and the rail and the account menu still lead to the site.

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
      "id": "office",
      "type": "wx-tab",
      "label": "Office",
      "children": [
        {
          "id": "office-card",
          "type": "wx-card",
          "children": [
            {
              "id": "office-photo",
              "type": "wx-media",
              "name": "office.photo",
              "label": "Photograph of the office",
              "slot": "sidebar",
              "props": { "aspect": "16/9", "accept": "image" }
            },
            {
              "id": "office-address",
              "type": "wx-textarea",
              "name": "office.address",
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
settings('office.address', 'Nowhere');
settings('office.photo')['url'] ?? null;     // a media field resolves to its address
settings()->all();
```

Values are cached whole and the cache is dropped on save; `WebxUi\Settings\Events\SettingsSaved`
carries the keys that changed, for a site that builds something out of them.

## MCP

`settings_list`, `settings_get` and `settings_set` (mutating, honours `dry_run`) come with the
module. The keys and the rules are read from the screen, so an agent can change exactly what an
administrator can — patched fields included — and nothing else.

### Content rules

The rules are for an AI agent that writes the site's content through MCP rather than a person
reading the panel, and are edited where agents are connected. The module serves it as the resource `settings://content-rules`:

```json
{
  "languages": [{ "code": "en", "name": "English", "native_name": "English", "primary": true }],
  "primary": "en",
  "tone": "Plain and warm, addressed to the reader as \"you\".",
  "donts": ["No prices or discounts unless the page is about them"],
  "notes": null,
  "more": [],
  "empty": false
}
```

The languages are not typed into the rules: they come from the site's language settings, so the
rules never disagree with the site about them. `donts` is the field split into lines; a field a
project patches into `content-card` of `settings.content` under `content.<name>` comes out in
`more` with its label.
The MCP server's instructions tell every agent to read the resource before writing anything a
visitor will read — said whenever the module is installed, since the languages are always
there. Editing the rules takes `settings.manage`, like any setting; an agent changes them with
`settings_set`.

## Talking to it directly

```ts
import { createSettingsApi } from '@webx-ui/module-settings'

const api = createSettingsApi(useAdmin())
const values = await api.load()
await api.save({ ...values, 'general.project-name': { en: 'Acme' } })
```

`GET /api/cms/settings` answers `{ data: { values } }`; `PUT` takes `{ values }` and answers
with what it kept.
