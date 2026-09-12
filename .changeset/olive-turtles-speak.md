---
'@webx-ui/php': minor
---

`webx-ui/localization` — the languages a site is published in, translated Eloquent attributes,
and the dictionary the admin panel is drawn from.

It keeps two things apart that are easy to run together. Interface phrases are written by
whoever wrote the module, change at deploy, and live in the package's `lang` files; content is
written by whoever runs the site, changes all day, and lives in the database. One store for
each, and neither knows about the other.

A model names its translatable columns and goes on being a model — the value is a JSON language
map, readable by anything that understands `spatie/laravel-translatable`. The panel's own words
come from the same `lang` files the server reads, so a module is translated once rather than
once per half.
