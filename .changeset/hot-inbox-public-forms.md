---
'@webx-ui/php': patch
---

`module-inbox`, public forms: the same form twice on one page (footer and popup) no longer repeats an `id` — the first copy keeps `wx-form-<slug>`, further ones get `-2`, `-3`, and the honeypot's `id` is the form's (`wx-form-<slug>-hp`), not the field name. A POST without the hidden timestamp counts as too fast. An address whose domain has no dot (`a@b`) is refused. `throttle` now counts only accepted submissions, so a person correcting a typo is no longer locked out with 429; every request, refused ones included, counts towards the new `antispam.attempts` (20 a minute by default) on the route. A site that published `form.blade.php` or `honeypot.blade.php` republishes them or copies the `formId`/`id` changes.
