# Compositions

The layout every public page is printed in is not one Blade file a theme has to copy to change. It
is a **composition**: a tree of nodes with ids in JSON. `theme-default` gives the tree, the modules
add their pieces to it, and a theme or the site patches it **by id** — an attribute on `<html>`, a
piece gone, a node moved, another view in a node's place, something added on one page type only.
The result is compiled into plain Blade views, one per composition and page type; nothing is merged
on a request, and the HTML is what a hand-written layout would print.

A copy of a compound view stops getting the fixes of releases. A patch of five lines keeps them.

Specification: [`WEBX_UI_COMPOSITIONS.md`](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_COMPOSITIONS.md).

## Where the files are

```
<layer>/compositions/<composition>/<handle>.json
```

| Layer                  | Directory                                     | Applied        |
| ---------------------- | --------------------------------------------- | -------------- |
| the base               | `theme-default/compositions/layout/`          | first          |
| a module               | `vendor/…/module-seo/resources/compositions/` | by module id   |
| a packaged theme       | `<package>/compositions/`                     | bottom up      |
| the site's local theme | `theme/compositions/`                         | after packages |
| the site's config      | `config/webx-compositions.php` → `patches`    | last           |

Each layer is applied whole — its `default.json`, then the file of the page's handle — before the
next one: a higher layer always wins, the way the theme chain does. A layer without a file adds
nothing.

**Compositions today:** `layout` (the full document), `layout.minimal` (a header of just the
brand, no footer — for a checkout or a wizard; it `extends` `layout`, so it gets every module's
piece and every patch of the theme) and `layout.blank` (an empty shell for a promo or an embedded
page; a base of its own). A page chooses one by `<x-layout>`, `<x-layout.minimal>`,
`<x-layout.blank>`, by its module's `webx-<module>.layout` key, or by the site's config:

```php
// config/webx-compositions.php
return [
    'layouts' => ['catalog.checkout' => 'layout.minimal'],
];
```

There is no choice of a layout in the panel: it would be a question on every site where nobody
needs it.

## The tree of `layout`

```
html                       <html lang="…">                          protected
  head                                                              protected
    meta.charset
    meta.viewport
    snippets.head          the tag manager's place                  module-snippets
    theme.assets           @webxTheme — tokens and stylesheets      protected
    meta.theme-color       @webxThemeColor — the colour of the browser bar
    head.slot              the page's <head>, or @webxSeo @webxBlocks  protected
    head.stack             @stack('head')                           protected
  body                     <body class="site">                      protected
    snippets.body-start                                             module-snippets
    skip-link              Skip to content
    notice-bar             the announcement bar of widgets (its stack: notice.stack)
    header                 region header, fallback components.header
    main                   <main id="content" class="site-main">    protected
      content              the page                                 protected
      seo.faq              the page's FAQ                           module-seo
      seo.links            the interlinking block                   module-seo
    footer                 region footer, fallback components.footer
    widgets.contact-button quick contact                            widgets
    scripts.stack          @stack('scripts')                        protected
    snippets.body-end                                               module-snippets
```

`php artisan webx:composition:show layout` prints the tree of your site, with your modules and your
theme in it. The ids of the base and of the modules are a contract: a release does not rename one
without saying so in its changelog.

A **protected** node is a seam the head, the blocks or the modules stand on: it cannot be removed
or replaced, `html`, `head` and `body` cannot move, and `content` moves only inside `main`.

## Patch it

A patch is a JSON object with up to six operations, applied in this order whatever the order of
the keys: `add` → `move` → `replace` → `attrs` → `props` → `remove`.

### An attribute — smooth scrolling

```jsonc
// theme/compositions/layout/default.json
{
  "attrs": {
    "html": { "data-webx-smooth-scroll": true, "data-webx-smooth-scroll-duration": "1.4s" },
  },
}
```

`true` is an attribute without a value, `false` or `null` takes one off. `class` is added to, not
replaced: `{"body": {"class": "is-dark"}}` gives `class="site is-dark"`; to take a class off,
`{"class": {"remove": "site"}}`. `lang` on `<html>` is the page's language and `id="content"` on
`main` is where Skip to content leads: neither is a patch's to change. Values are literals and
`trans::<key>`; data and conditions belong in a leaf view.

### A piece gone

```json
{ "remove": ["widgets.contact-button", "seo.links"] }
```

### A node elsewhere

```json
{ "move": [{ "id": "notice-bar", "to": "body", "after": "header" }] }
```

`to` is a container (`tag`, a group, a component); without it the node moves among its siblings.
The place is `before`, `after`, `first` or `last`; a neighbour that is not there puts it last, with
a finding. A region is not a container — the header's blocks are content of the panel — so a node
goes before or after `header`, never inside it.

### Another view or component in a node's place

```json
{ "replace": { "header": { "view": "components.header-compact" } } }
```

The node keeps its id; what the old one held that another layer had added is said as a note.

### A node of your own — on one page type only

```jsonc
// theme/compositions/layout/blog.article.json
{
  "attrs": { "body": { "class": "is-article" } },
  "add": [
    {
      "id": "reading-progress",
      "to": "body",
      "after": "header",
      "view": "components.reading-progress",
    },
  ],
  "remove": ["seo.links"],
}
```

The file name is the **handle** — the page type the module's view names when it calls the layout
(`<x-layout handle="blog.article">`). Every page type gets `default.json` first, then its own file.
The handles: `pages.page`, `blog.article`, `blog.feed`, `blog.rubric`, `blog.tag`, `blog.author`,
`services.index`, `services.category`, `services.show`, `catalog.root`, `catalog.category`,
`catalog.product`, `catalog.search`, `catalog.brands`, `catalog.brand`, `events.*`, `recipes.*`,
`vacancies.*`, `press.outlet` — `webx:composition:show` lists the ones your modules name. There is
no handle for one page (`page.42`): one record is changed by its content, not by a file of the
theme.

Give a theme's node an id of its own — not `seo.*`, `snippets.*`, `widgets.*`: the module's next
node may take it.

### A prop of a component

```json
{ "props": { "promo": { "variant": "wide" } } }
```

On a `component` node, the theme's own or a module's; `null` takes a prop off.

### The kinds of node

Exactly one key says what a node is:

| Key         | Prints                                                     |
| ----------- | ---------------------------------------------------------- |
| `tag`       | an element with `attrs` and `children`                     |
| (none)      | a group: just its `children`                               |
| `view`      | `@include` of a view, with literal `with`                  |
| `component` | `<x-…>` with `attrs`, `props`; its `children` are the slot |
| `region`    | a region of blocks, with a `fallback` view                 |
| `slot`      | what the caller hands (`default`, `head`), with `fallback` |
| `stack`     | `@stack`                                                   |
| `directive` | a registered directive, with `args`                        |
| `html`      | raw HTML; Blade in it is escaped, not run                  |

The JSON schema is `vendor/webx-ui/compositions/resources/schema/composition.json`; name it in
`"$schema"` and an editor completes the keys.

### Without a theme, or by environment

```php
// config/webx-compositions.php
return [
    'patches' => [
        'layout' => ['default' => ['attrs' => ['html' => ['data-webx-smooth-scroll' => true]]]],
    ],
];
```

The same shape as the files, as PHP arrays, above the local theme. The usual place of a site's
patches is `theme/compositions/`.

## A module's pieces

A module adds to a composition from `resources/compositions/<composition>/default.json` and loads
the directory in its provider; the layout names no module:

```php
use WebxUi\Compositions\Compositions;

public function boot(): void
{
    Compositions::load(__DIR__.'/../resources/compositions');
    Compositions::handles('blog', ['blog.article', 'blog.feed']); // the page types its views name
}
```

Its nodes take the module's prefix (`seo.faq`) and are listed in its `AGENTS.md`. Its test holds
its files to no findings (`use IsolatedCompositions` and assert `findings()` is empty).

## Markup of your own

A file of the view always wins: `theme/views/components/layout.blade.php` is read before the
composition's dispatcher, and the composition is not printed. `webx:theme:check` says so as a note,
not a refusal — it is a door you may take on purpose. To keep your markup and still get what the
modules add with releases, place the containers' pieces:

```blade
<main id="content" class="my-main">
    {{ $slot }}
    <x-webx-composition::place id="main" :handle="$attributes->get('handle')" only="added" />
</main>
<x-webx-composition::place id="body" only="added" />
```

`only="added"` prints what the modules and the themes added above the base; `only="all"` every
child but the slots. To go back from a copy to a patch: `php artisan webx:composition:show layout
--blade` prints what the composition gives, the difference with your copy becomes a patch, the copy
is deleted, and the HTML of a page before and after is compared.

## When it is compiled

Every composition × handle becomes a view in `storage/framework/compositions/` on
`php artisan webx:boot` (`webx:composition:compile` alone), and again on a request whenever a
source is newer than the set — while `view.check_cache_timestamps` is on, the way Blade looks at
its own views; `webx-compositions.check` says otherwise. A patch that names a node that is not
there skips that operation and says so in the log: the page renders. A file that is not JSON or
breaks the schema stops the compile, and the last set keeps serving.

## Debugging

| Command                                                       | What it says                                                                                |
| ------------------------------------------------------------- | ------------------------------------------------------------------------------------------- |
| `webx:composition:show`                                       | the compositions, their handles, every file of every layer, the findings                    |
| `webx:composition:show layout --handle=blog.article --origin` | the final tree; who put each node there and every file that moved, replaced or changed it   |
| `webx:composition:show layout --json`                         | the same for a script or an agent, the origin always in it                                  |
| `webx:composition:show layout --blade`                        | the Blade the compiler writes                                                               |
| `webx:theme:check`                                            | every finding is a line `file  operation — why`, non-zero exit; a copy of the layout a note |
| `webx:doctor`                                                 | the trees build, the compiled set is as the sources give it, a page names its handle        |

```
$ php artisan webx:composition:show layout --handle=blog.article --origin
layout × blog.article — from webx-ui/theme-default, module-seo, module-snippets, widgets, theme

html                       <html data-webx-smooth-scroll>       webx-ui/theme-default; attrs.data-webx-smooth-scroll ← theme/compositions/layout/default.json
  …
  body                     <body class="site is-article">       webx-ui/theme-default; attrs.class ← theme/compositions/layout/blog.article.json
    …
    header                 region header (fallback components.header)  webx-ui/theme-default
    reading-progress       view components.reading-progress     theme
    notice-bar             <x-webx-notice-bar>                  webx-ui/theme-default; move ← theme/compositions/layout/default.json
    main                   <main id="content" class="site-main">  webx-ui/theme-default
      content              slot default                         webx-ui/theme-default
      seo.faq              <x-webx-seo::faq>                    module-seo

removed: seo.links ← theme/compositions/layout/blog.article.json; widgets.contact-button ← theme/compositions/layout/default.json
```

A piece that does not show: `show --origin` names the file that removed or moved it. A patch that
does nothing: `webx:theme:check` names the id it could not find. A file for a page type that never
applies: its name is not a handle a module names — `unknown handle`.

## Not compositions

The leaves stay Blade — a card, a row, a field. Blocks and regions are the editor's content, not a
theme's file. Letters, PDFs, API answers and error pages are not printed in a composition, and a
section of the site that is all Vue takes `layout.blank` or its own Blade.
