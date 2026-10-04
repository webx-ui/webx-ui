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
- **Blueprint macros** `$table->translatable(...)` and `$table->dropTranslatable(...)` —
  nullable JSON columns.
- **Middleware** aliases `webx.locale` (`SetLocale`, the public site) and `webx.panel-locale`
  (`SetPanelLocale`, the panel), plus `OneSpellingPerAddress` for a module's prefixed list route
  (route parameter `webxLocale`): an unknown language is a 404, the default's prefix a 301.
- **Commands** `webx:locales:seed`, `webx:locales:clear`.
- Validation lines in ten languages, appended behind the application's own `lang/`.

## Change it without forking

| You want                                     | Do this                                                                              |
| -------------------------------------------- | ------------------------------------------------------------------------------------ |
| The languages a fresh install starts with    | `'locales'` in `config/webx-localization.php`, then `php artisan webx:locales:seed`  |
| Add or switch off a language on a live site  | a `Locale` row (`Locale::fromCode('de')->save()`) — not the config, the table wins   |
| Language from `Accept-Language` / not at all | `'strategy' => 'header'` / `'none'` (default `prefix`, `/uk/about`)                  |
| A prefix on the default language too         | `'prefix_default' => true`                                                           |
| Which language fills a missing translation   | `'fallback'` — keep it on a language that is complete                                |
| The panel offered in another language        | translate the published `lang` files, add the code to `'panel'`                      |
| Other words in the panel                     | `php artisan vendor:publish --tag=webx-admin-lang` (or the module's own `-lang` tag) |
| See `lang` edits at once while translating   | `WEBX_LOCALIZATION_CACHE=false`, or `php artisan webx:locales:clear` after each edit |
| Publish the config                           | `php artisan vendor:publish --tag=webx-localization-config`                          |
| A translatable column in your own model      | `use HasTranslations`, list it in `translatable()`, `$table->translatable('col')`    |

## Do not

- Do not edit anything in `vendor/webx-ui/localization`. Every row above is the supported way;
  if none fits, the package is missing a seam — say so instead of working around it.
- Do not add a language by editing the config on a site that has migrated: the `locales` table
  is what is read, the config is only the seed. Add the row, or add it to the config and run
  `webx:locales:seed`, which creates the missing ones and leaves existing ones alone.
- Do not delete a language to hide it: content written in it stays in every JSON map. Set
  `is_active` to false instead; the text comes back when it is switched on.
- Do not set `is_default` with a raw SQL update: the model is what keeps exactly one default and
  normalises the code. Save through `Locale`, then `webx:locales:clear` — the list is cached.
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

## Read more

- [README.md](README.md) in this directory — the PHP API: languages, translated attributes,
  where the panel's phrases come from.
- Guide: https://webx-ui.github.io/webx-ui/guide/languages
- Extending views and services: https://webx-ui.github.io/webx-ui/guide/extending
