---
'@webx-ui/php': minor
---

`WebxUi\Admin\Collections\RecordQuery` in `module-admin`: the steps and rules shared by the site
helpers (`only()`, `except()`, `take()`, `locale()`, categories by id or slug, `relatedTo()`, the
limit counted after the language), for a module to extend with its model, visibility and cards.
`services()`, `reviews()`, `recipes()` and `events()` now extend it and answer exactly as before.
`Selection::apply()` accepts a model without categories when none are chosen.
