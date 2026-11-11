# webx-ui/localization

The languages of a site: the list it is published in, translated Eloquent attributes stored as a
language map in one JSON column, the middleware that picks a request's language, and the
dictionary the panel is drawn from. It has no section of its own; the `/api/cms/locales` and
translation routes belong to `webx-ui/module-admin`, and a translated address is
`webx-ui/routing`'s business — read their guides for those.

## What it owns

- **Table** `locales` (`WebxUi\Localization\Models\Locale`): `code`, `name`, `native_name`,
  `direction`, `is_default`, `is_active`, `sort`. Exactly one row is the default; the model
  switches the others off when one is saved as default.
- **Service** `WebxUi\Localization\Locales` — `codes()`, `defaultCode()`, `current()`,
  `use()`, `chain()`, `panel()`, `seed()`, `forget()`. Until the table exists it answers from
  `config('webx-localization.locales')`.
- **Trait** `HasTranslations` with `translatable()`: `getTranslations()`, `setTranslations()`,
  `forLocale()`, `translationsToArray()`, scopes `whereTranslation`, `whereTranslationLike`,
  `orderByTranslation`. Stored as `{"en": "...", "uk": "..."}`.
- **Trait** `HasLatinSlug` — a slug being written is transliterated on the model, one that leaves
  nothing becomes `<type>-<id>`. A model with a slug and no address uses it by name and registers in
  `SlugModels`; `LatinSlugCoverageTest` holds every model to it.
- **Trait** `HasCodes` with `static codes()` (`column => [max, separator, lead]`) — a machine name somebody types
  (a key, a code) is brought to its shape on `saving` by `Slug::code()`; `Model::codeFor($column, $value)` is the
  same for a request that checks the code first. `CodeCoverageTest` holds every model with a code, every
  code-shaped `regex:` rule and every code field of a screen to it.
- **Generator** `WebxUi\Localization\Slug` — `make($text, $locale, $max, $prefix, $separator)` and
  `first($texts, $default, …)`: every code and address part a module makes of a name. Transliterates
  by the name's language (`Хіт` is `khit` in uk, `hit` in ru — never `xit`); `$prefix` names a
  result with no letter (`−20%` → `label-20`). The panel's `slugify(title, separator, locale)`
  (`@webx-ui/module-admin`) uses the same table, written by `scripts/transliteration.php`.
- **Blueprint macros** `$table->translatable(...)` and `$table->dropTranslatable(...)` —
  nullable JSON columns.
- **Middleware** aliases `webx.locale` (`SetLocale`, the public site) and `webx.panel-locale`
  (`SetPanelLocale`, the panel), plus `OneSpellingPerAddress` for a module's prefixed list route
  (route parameter `webxLocale`): an unknown language is a 404, the default's prefix a 301.
- **The language of the first visit** (`FirstVisit`, inside `SetLocale`, off by default): a
  person's first visit to another language's page is a 302 to the same page in it plus the cookie
  `webx_lang` ("sent once"; with it, never again); robots never; the switcher untouched. The
  page's address comes from `Contracts\PageInLanguage` — `NoPageInLanguage` (always `null`)
  unless `webx-ui/widgets` binds the switcher's. `offer()` — who gets the later "Switch to …?"
  (`first_visit.offer`, cookie `webx_lang_offer` once declined).
- **Commands** `webx:locales:seed`, `webx:locales:clear`.
- **Test trait** `Testing\AssertsTranslations` — `assertTranslationsMatch($lang, $languages)` for a module
  outside the monorepo (a site module): `en` and the listed languages are there, every present language has the keys of `en`.
- Validation lines in ten languages under the application's own `lang/`: `Translations\ValidationLines`
  wraps `translation.loader` (as `themes` does), so a site's published line still wins.

## Change it without forking

| You want                                     | Do this                                                                              |
| -------------------------------------------- | ------------------------------------------------------------------------------------ |
| The languages a fresh install starts with    | `'locales'` in `config/webx-localization.php`, then `php artisan webx:locales:seed`  |
| Add or switch off a language on a live site  | a `Locale` row (`Locale::fromCode('de')->save()`) — not the config, the table wins   |
| Language from `Accept-Language` / not at all | `'strategy' => 'header'` / `'none'` (default `prefix`, `/uk/about`)                  |
| A prefix on the default language too         | `'prefix_default' => true`                                                           |
| Send a first visit to another language       | `WEBX_LOCALE_FIRST_VISIT=uk` (a code only); robots, paths — `'first_visit'`          |
| Offer it on later visits                     | `WEBX_LOCALE_FIRST_VISIT_OFFER=true` (`'first_visit.offer'`)                         |
| Which language fills a missing translation   | `'fallback'` — keep it on a language that is complete                                |
| The panel offered in another language        | translate the published `lang` files, add the code to `'panel'`                      |
| Other words in the panel                     | `php artisan vendor:publish --tag=webx-admin-lang` (or the module's own `-lang` tag) |
| See `lang` edits at once while translating   | `WEBX_LOCALIZATION_CACHE=false`, or `php artisan webx:locales:clear` after each edit |
| Publish the config                           | `php artisan vendor:publish --tag=webx-localization-config`                          |
| A translatable column in your own model      | `use HasTranslations`, list it in `translatable()`, `$table->translatable('col')`    |

## Do not

- Do not call `Str::slug()` without a language for a code or an address: it reads Cyrillic the
  English way. Use `Slug::make()`. `SlugCoverageTest` fails on such a call in any package's `src`.
- Do not refuse a code someone typed (a status code, a key) for its shape: bring it to the shape
  with `HasCodes` (`Slug::code($text, $locale, $max, $separator, $lead)`; `Slug::BOTH` for a key that takes
  `-` and `_`), bring it with `codeFor()` before a request's own check, and give the field `wx-code` /
  `<wx-code-field>`. Refuse only a code that is taken or that leaves nothing with no fallback.

- Do not add the validation lines with `FileLoader::addPath()` or replace `translation.loader`:
  another package wraps it (`themes`), and an added path is read after the site's `lang/`.
- Do not edit anything in `vendor/webx-ui/localization`. Every row above is the supported way;
  if none fits, the package is missing a seam — say so instead of working around it.
- Do not add a language by editing the config on a site that has migrated: the `locales` table
  is what is read, the config is only the seed. Add the row, or add it to the config and run
  `webx:locales:seed`, which creates the missing ones and leaves existing ones alone.
- Do not delete a language to hide it: content written in it stays in every JSON map. Set
  `is_active` to false instead; the text comes back when it is switched on.
- Do not set `is_default` with a raw SQL update: the model is what keeps exactly one default and
  normalises the code. Save through `Locale` — the cached list and the `locales` cache group
  (`webx-ui/cache`, `CacheGroups::LOCALES`) follow the model; after SQL, `webx:locales:clear`.
- Do not write `$model->title = [...]` to set several languages: a plain assignment writes the
  current language only. Use `setTranslations('title', [...])`.
- Do not make a translated slug unique with a database index: a JSON column cannot do it per
  language. Address uniqueness belongs to `webx-ui/routing`.
- Do not widen `'namespaces'` to the application's own strings: everything matched is shipped to
  the browser in the panel's dictionary.

## Check your work

- `php artisan webx:locales:seed` prints the table of languages the site actually has.
- After a `lang` edit: `php artisan webx:locales:clear`, then reload the panel.
- Open `/<code>/...` for each language; an unknown prefix must be a 404, the default's prefix a
  301 to the address without it (unless `prefix_default` is on).
- `php artisan webx:doctor` — what is misconfigured on the site.
- First visit on: `curl -sI -H 'Accept: text/html' -A 'Mozilla/5.0' <page>` is a 302 with
  `Set-Cookie: webx_lang`; with `-A Googlebot/2.1` or `-b webx_lang=uk` — a 200.

## Read more

- [README.md](README.md) in this directory — the PHP API: languages, translated attributes,
  where the panel's phrases come from.
- Guide: https://webx-ui.github.io/webx-ui/guide/languages
- Extending views and services: https://webx-ui.github.io/webx-ui/guide/extending
