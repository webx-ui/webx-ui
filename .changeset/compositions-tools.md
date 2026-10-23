---
'@webx-ui/php': minor
---

`php artisan webx:composition:show` — the compositions with their handles and the files of every layer; with a name, the final tree of a page type (`--handle=blog.article`), and with `--origin` the layer that put each node there and every file that moved, replaced, removed or changed it since. `--json` gives the same with the origin always in it, `--blade` the view the compiler writes.

`webx:theme:check` checks the compositions over the chain: a file that is not JSON or breaks the schema, a tree that does not build, an id that is not there, a duplicate, a protected node, a reserved attribute, a file for a page type no module names (`unknown handle`), a composition no layer has a base for, a theme's node with a module's prefix (`seo.*`), `<x-webx-composition::place>` on a container that is not there, a `@webxThemeColor` token without a colour — each a finding with its file. A `components/layout.blade.php` of the site or the theme that stands in for the composition is a note. The modules with public pages declare their page types (`Compositions::handles()`) for it.

`webx:doctor` (and `--json`) says whether the trees build, whether a patch names what is there, and whether the compiled set is the one the sources give — a warning when it is older and the mtime check is off, the way production runs after `view:cache` — and notes a module page that calls the layout without `handle`.

The guide [Compositions](https://webx-ui.github.io/webx-ui/guide/compositions) — the format, the operations, the handles, debugging; smooth scrolling, the dark scheme's early script and rule 21 of `RULES.md` are a patch now, not a copy of the layout.
