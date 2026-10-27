# webx-ui/compositions

Compound views as trees of nodes with ids. A package gives the tree of a composition (`layout`,
`layout.minimal`, later `blog.article`), modules add their pieces to it, a theme and the site
patch it by id — and the result is compiled into plain Blade views, one per composition × handle,
nothing merged on a request. Blade stays for the leaves. It knows no module and no theme names:
themes hand their chain over from `webx-ui/themes`, modules load their directories themselves.
The tree of `layout` belongs to `webx-ui/theme-default` — read its guide for the ids.

## What it owns

- **Files** `<layer>/compositions/<composition>/<handle>.json`: a module's under
  `resources/compositions/`, a theme's under `compositions/`, the site's theme under
  `theme/compositions/`. A file is a **base** (`root`, or `extends` another composition) or a
  **patch** (`add`, `move`, `replace`, `attrs`, `props`, `remove`). Schema:
  `resources/schema/composition.json`.
- **Contract of a base** — `vars` (the variables every leaf gets, without `$`; an `extends` variant
  inherits them) and `dispatcher` (the view that includes the composition — where a copy is looked
  for). Never in a patch. `Builder::contract()`, `Tree::$vars`, `Tree::$dispatcher`.
- **Nodes** — exactly one kind key each: `tag`, a group (`children` only), `view`, `component`,
  `region`, `slot`, `stack`, `directive`, `html`. Values are literals and `trans::<key>`.
- **Handles** — `default` and the page type a module's view names: `<x-layout handle="blog.article">`.
  A handle without a file of its own gets `default`. A module's page composition (`blog.article`)
  is included with `default`: its `blog.article/blog.article.json` is never read.
- **Layers, in order** — the base (and its own handle file) → modules by id → themes above the
  base, bottom up → the local theme → `config/webx-compositions.php` `patches`. Each layer whole:
  `default`, then the handle.
- **`Compositions`** (scoped) — `view($composition, $handle)`, `place(...)`, `findings()`,
  `compile()`; statics `Compositions::load($dir)` (a module's provider), `Compositions::handles()`,
  `Compositions::themes()` (the engine of themes).
- **Helper** `composition_view('layout', $handle)` — what a dispatcher view includes.
- **Component** `<x-webx-composition::place id="body" only="added" />` — the children of one
  container in a hand-written view: `all` (but the slots) or `added` above the base.
- **Compiled set** `storage/framework/compositions/` (`compiled`): a `build-*` directory of views
  and `manifest.php` with the sources and their mtimes; the compiler writes `.gitignore` (`*`) there
  if there is none, so the set stays out of git on any site. A request compiles again what is older
  than its sources while `view.check_cache_timestamps` is on (`check` overrides).
- **Commands** `webx:composition:compile` (a step of `webx:boot`, before `view:cache`),
  `webx:composition:clear` and `webx:composition:show [<composition>] [--handle=] [--origin]
[--json] [--blade]` — the final tree with who added, moved, replaced, removed or changed a node.
- **`Inspector`** — every composition × handle built from the sources apart from the compiled set:
  what `show`, `webx:theme:check` (`CompositionCheck` of `webx-ui/themes`) and `webx:doctor`
  (`Doctor\CompositionsCheck`, where `module-admin` is installed) read.
- **Handles** a module names — `Compositions::handles('blog', [...])`; a theme's file for another
  one is `unknown handle` in `webx:theme:check`.
- **Config** `webx-compositions.php`: `patches`, `layouts` (a handle to another composition —
  `['catalog.checkout' => 'layout.minimal']`), `compiled`, `check`.

## Change it without forking

| You want                                    | Do this                                                                                        |
| ------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| An attribute on a node (`<html>`, `<body>`) | `theme/compositions/layout/default.json`: `{"attrs": {"html": {"data-x": true}}}`              |
| A class on one page type only               | `theme/compositions/layout/blog.article.json`: `{"attrs": {"body": {"class": "is-article"}}}`  |
| A piece of a module gone                    | `{"remove": ["widgets.contact-button"]}`                                                       |
| A node of your own                          | `{"add": [{"id": "promo", "to": "body", "after": "header", "view": "components.promo"}]}`      |
| A node elsewhere                            | `{"move": [{"id": "notice-bar", "after": "header"}]}`                                          |
| Another view or component in a node's place | `{"replace": {"header": {"view": "components.header-compact"}}}`                               |
| A prop of a component                       | `{"props": {"seo.faq": {"limit": 5}}}`                                                         |
| A page type in another layout               | `'layouts' => ['catalog.checkout' => 'layout.minimal']` in `config/webx-compositions.php`      |
| Patches without a theme, or by environment  | `'patches' => ['layout' => ['default' => [...]]]` in `config/webx-compositions.php`            |
| A module's pieces in a composition          | `resources/compositions/<composition>/default.json` and `Compositions::load()` in `boot()`     |
| Markup of your own with the modules' pieces | your `components/layout.blade.php` with `<x-webx-composition::place id="body" only="added" />` |
| Publish the config                          | `php artisan vendor:publish --tag=webx-compositions-config`                                    |

## Do not

- Do not copy a compiled view or a package's dispatcher (`components/layout.blade.php`) into a
  theme to change one attribute: a copy stops getting the fixes of releases. Patch by id.
- Do not edit anything in `storage/framework/compositions/`: it is written again on the next
  compile. Do not edit `vendor/webx-ui/compositions` either.
- Do not give a theme's node a module's prefix (`seo.*`, `snippets.*`): the next node of the
  module collides with it.
- Do not put expressions in `attrs` or `props`: literals and `trans::` only. Data and conditions
  belong to a leaf view.
- Do not `remove`, `replace` or move out of its place a protected node (`html`, `head`, `body`,
  `main`, `content`, `theme.assets`, `head.slot`, `head.stack`, `scripts.stack`): the operation
  is skipped and said.
- Do not expect an exception for a patch that names a node that is not there: it is a finding
  in the log and the page renders. A file that is not JSON or breaks the schema is an exception,
  and the last compiled set keeps serving.

## Check your work

- `php artisan webx:composition:show layout --handle=blog.article --origin` — the tree a page gets,
  and the file behind every change; `--json` for a script.
- `php artisan webx:theme:check` — every finding a line, exit code 1: an id that is not there, a
  duplicate, a protected node, a reserved attribute, `unknown handle`, a composition without a
  base, a theme's id with a module's prefix, a `place` on no container, a theme-color token
  without a colour, a file that is not JSON. Notes: a copy of the `dispatcher` (or of
  `components/layout`), a file for the handle of the composition's own name, a theme's view that
  reads a variable not in `vars`.
- `php artisan webx:doctor` — the trees build, and the compiled set is as its sources give it.
- `php artisan webx:composition:compile` — compiles everything and prints the findings; exit
  code 1 when a tree cannot be built.
- Open the page and read the HTML: the generated view says which layers it is built from in its
  first line (`storage/framework/compositions/build-*/`).
- In a test of a package that adds to a composition: `use IsolatedCompositions` and assert no
  findings — a package's own files must apply cleanly.
- A view turned into a composition: `use ComparesHtml` and `assertSameHtml($before, $after)` — the
  same HTML up to the whitespace between tags. A Blade comment at the top of a leaf keeps its
  newline: write `{{-- Why. --}}@if`.

## Read more

- [README.md](README.md) in this directory — the PHP API.
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_COMPOSITIONS.md
