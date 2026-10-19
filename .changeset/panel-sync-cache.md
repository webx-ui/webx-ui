---
'@webx-ui/php': patch
---

`webx-ui/module-admin`: «System → Cache» reaches the panel on every site. The frame declares its
client half (`cache()` from `@webx-ui/module-admin`) under `extra.webx.panel`, so a fresh entry
file has it and `php artisan webx:panel --sync` adds it to an older one; a call already in the
file counts wherever it stands, comments aside, so a `cache()` written in by hand below
`// /webx:modules` is not registered twice. `webx:doctor` «Both halves» fails while it is missing.
`--sync` now raises a `package.json` range that can never reach the version the installed
Composer half asks for (`^0.30.0` against `^0.31.0`), in the section it stands in, and says so;
a range that reaches it, a newer one and a `file:` link are left as they are. The doctor's
«npm halves» points at `--sync` for such a range. `webx:modules` does not list the frame.
