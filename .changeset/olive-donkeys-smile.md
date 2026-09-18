---
'@webx-ui/php': minor
---

A picture's captions reach a template in one language.

`alt` and `title` are written per language — the field gives each of them a language
switcher — and they were handed to a template as the whole map, so `{{ $picture['alt'] }}`
was Blade being given an array and refusing. All four fields that hold a file are read
through the same code, so all four had it: `wx-media`, `wx-file`, `wx-gallery`, `wx-files`.

They are picked apart now, down the chain every localized value is read through: the
language asked for, the site's default, its fallback. A caption that was never a map — one
written before the site had a second language, or sent by an agent — is left as it is.

Only the read side changed. The panel edits the whole map, and it never went this way.
