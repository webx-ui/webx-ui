---
'@webx-ui/php': minor
---

New library `webx-ui/compositions`: a compound view as a tree of nodes with ids, which modules add their pieces to and a theme or the site patches by id — `attrs` (`true` bare, `false`/`null` off, `class` appended), `add`, `move`, `replace`, `props`, `remove` — for every page (`default`) or for one page type (`blog.article`), without a copy of the view. The trees are compiled into ordinary Blade views on `webx:boot` (`webx:composition:compile`, before `view:cache`) and again on a request whose JSON is newer, the way Blade checks its own views; a request merges nothing. A patch that names a node that is not there is skipped and logged, the page renders.

The layout of `webx-ui/theme-default` is the first composition: `compositions/layout/default.json`, and `<x-layout>` prints its compiled variant. The HTML is the one it printed before, plus `<meta name="theme-color">` from the token `color-bg` (two tags with `media` when the dark scheme gives another value; `@webxThemeColor` in `webx-ui/themes`, another token as its argument). The pieces the layout used to know come from their packages now: `module-snippets` (`snippets.head`, `snippets.body-start`, `snippets.body-end`), `module-seo` (`seo.faq`, `seo.links` in `main` after the page), `widgets` (`widgets.contact-button` after the footer). Two more shells: `<x-layout.minimal>` (the brand for a header, no footer, for a checkout) and `<x-layout.blank>` (an empty document for a promo). The public views of the modules name their page type: `<x-layout handle="blog.article">`, `pages.page`, `services.show`, `catalog.product` and the rest.

Turn on smooth scrolling without a copy of the layout — `theme/compositions/layout/default.json`:

```json
{ "attrs": { "html": { "data-webx-smooth-scroll": true } } }
```

**A site with its own `components/layout.blade.php`** (in `theme/views/` or `resources/views/`): nothing changes on its pages — the file stands above the theme's and prints instead of the composition, as before. What it no longer gets is what the packages now add to the tree: the `theme-color` tag, and any piece a module adds in a later release. To move over: compare the copy with the theme's layout, write each difference as a patch in `theme/compositions/layout/default.json` (or `<handle>.json` for one page type), delete the copy and compare the HTML of a page before and after. To keep markup of your own and still get the modules' pieces, print them where they go: `<x-webx-composition::place id="main" only="added" />`, `<x-webx-composition::place id="body" only="added" />`. A copy that rendered the modules' pieces itself through `view()->exists('webx-seo::faq')` or `webx-snippets::place` keeps working.
