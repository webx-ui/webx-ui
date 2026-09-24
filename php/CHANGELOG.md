# @webx-ui/php

## 0.41.0

### Minor Changes

- da138e0: `webx-ui/module-recipes`, new: recipes as a section of the panel. A recipe is a page of fixed structure — a gallery whose first photo is the cover, ingredients and method as translated documents, nutrition as five translated lines, time and servings — printed by the module's view in parts a site can publish and rewrite one at a time. Flat categories with pages of their own and a "rich in" list (iron, fibre) without, both filed in the draft and published with the text, like the related services and the similar recipes (`webx_relations`). Addresses `{prefix}/{category}` and `{prefix}/{recipe}` on one level under a prefix that is never empty; the index at the prefix can be switched off, and a page of `module-pages` takes the address and the first step of the trail. The catalogue is one fragment for the index, a category page and the offered block "Recipes" (a showcase, or the whole catalogue with pages and a nutrient filter, which is `noindex`). Similar recipes are the ones chosen by hand, or picked by shared categories, services and nutrients in one query. SEO card, sitemap, trail and schema.org `Recipe` with ingredients and steps read out of the lists. `recipes()` for templates, `recipes` as a `wx-collection` source related to services, `recipe` as a relation target.

  `webx-ui/module-seo`: the SEO card on the recipe and recipe category forms.

  `webx-ui/module-admin`: `webx:setup` offers recipes, and `webx:doctor` checks whose `recipes()` a template calls.

- da138e0: `webx-ui/module-recipes`: recipes for an agent — `recipes_list`, `recipes_get`, `recipes_create`, `recipes_update`, `recipes_publish`, `recipes_unpublish`, `recipes_delete`, `recipes_reorder` through the panel's own doors, the shared category tools as `recipe_categories_*` and `recipe_nutrients_*`, and `recipes://catalog` to read first. A recipe, a service and a similar recipe are named by id or by address, a category by slug, a nutrient by title; creating is the row and its values in one transaction; the tools that write say that the ingredients and the method are HTML lists, because the `Recipe` markup reads `<li>`. Demo content: three categories, five nutrients, six recipes, links to the demo services when they are there, and a page `/recipes-showcase` with both views of the block when blocks and pages are.

  `@webx-ui/module-admin`: "The record of the page it stands on" in the relation filter of `wx-collection` (`related.current`), and a chosen record in the bin marked "In the bin" rather than only "Not on the site".

  `@webx-ui/module-blocks`: the block template's autocomplete knows the card of the `recipes` source.

- da138e0: `webx-ui/module-admin`: relations between records of different modules, or of one — a recipe to its services, a recipe to the recipes like it. One table, `webx_relations`, with no foreign keys, so a module pointed at may be installed later or removed and put back; a module registers what can be pointed at in `RelationTargets`, and a record that points uses `HasRelations` (`related()`, `Relations::load()` for a list, `Relations::owners()` and `relatedTo()` from the other end). Deleting for good takes the rows along on both ends, the bin does not. The `wx-relations` field edits one relation, goes into the draft of a drafted record and takes effect on publishing, and leaves the screen when its target is not installed — the value stays. `GET /api/cms/relations/{target}` finds candidates and names the chosen behind the target's permission. `wx-collection` gets a relation filter (`related`), including "related to the record whose page the block is on", and `CollectionSource` a `relations()` method. `HasCategories` writes and filters a second kind of category by name. `webx:doctor` counts relations pointing at a module that is not installed.

  `webx-ui/module-services`: services can be pointed at (`service`), and the editor's save now puts block values through their field types, as an agent's edit already did.

  `webx-ui/module-blocks`: the renderer hands the entity whose page it prints to a field type that reads differently on it (`ResolvesForEntity`).

## 0.40.1

### Patch Changes

- 0c8a311: The media field takes a `width` that caps how wide its frame gets, and the review form uses it:
  the author's photo is a 160 px square instead of a frame the full width of the form.

## 0.40.0

### Minor Changes

- 8e61847: `webx-ui/module-reviews`: reviews — a photo, a name, a job title, a rating and a text — in flat categories with two orders, on any page as the offered Reviews block (one, a grid, a slider or a marquee, with a filter by category) and in site templates through `reviews()`. No page, no address and no markup of its own. `webx:setup` offers the module, and `webx:doctor` says whether `reviews()` is the package's own. For an agent, `reviews_*` and `review_categories_*` through the panel's own form and order code, and `reviews://catalog` to read first; `webx:demo` seeds two categories and eight reviews, a page `/reviews` with a grid and a slider in a demo service.

## 0.39.0

### Minor Changes

- d31fe5b: `webx:demo --module=<id>` seeds and removes demo content one module at a time, so that a
  module installed after the first seed can be filled beside the journal instead of emptying the
  site. A module the journal already holds is refused unless `--force`; a requirement whose demo is
  not seeded yet comes along first; `--remove --module` takes out only that module's entries and is
  refused while another seeded module still requires it. Without `--module` nothing changes.

## 0.38.0

### Minor Changes

- f246a1d: Pages, blog and services each get a `breadcrumbs` switch (`WEBX_PAGES_BREADCRUMBS`, `WEBX_BLOG_BREADCRUMBS`, `WEBX_SERVICES_BREADCRUMBS`, on by default): off, the package views stop printing the visible trail, while the `BreadcrumbList` in the head stays. Services also get `index` (`WEBX_SERVICES_INDEX`, on by default): off, the package no longer answers its prefix, so a page of blocks can take `/services`, and the services' breadcrumbs start with whatever stands there.
- f246a1d: The panel has its own tab icon, home-screen icon and web manifest, served by `module-admin` under the panel's prefix; `WEBX_ADMIN_ICONS` replaces them file by file.
- 421fab0: `services()` gives a block template the services as cards (`in()`, `only()`, `except()`, `take()`, `locale()`, `categories()`), never showing what a reader may not see; the same cards come from a new `services` source for `wx-collection` fields, and the module offers a Services block of them. `webx:doctor` says whether `menu()` and `services()` are the packages' own.

### Patch Changes

- 1771153: A media value saved together with its address — a block's sample is — no longer hands the template that old address: `resolve()` keeps only the key and the captions and works the address out again, so a site moved to https stops asking for its pictures over http.

## 0.37.0

### Minor Changes

- c7b0084: A block can show another section's records. A module registers a `CollectionSource` in
  `WebxUi\Admin\Collections\CollectionSources`, and a block schema names it in a field of the new
  type `wx-collection` (`"props": { "source": "faq" }`). The page keeps only the choice: which
  categories, a limit, whether to draw a filter, and whether to print schema.org markup (`null`
  means on when no category is chosen). The site reads the records: `items`, `groups` for the filter
  and `filter`. One chosen category shows its own order; none or several show the order of the whole
  list, each record once. A source that is gone reads as an empty list, so the page stays up.
  `GET /api/cms/collections` lists the sources this administrator may place.

  A module can also offer block types: `BlockOffers` in `webx-ui/module-blocks` holds the documents
  the module ships in `resources/blocks`, and `webx:blocks:offered --install` puts the missing ones on
  the site and publishes them. A type with the same slug is never touched. `webx:setup` runs this for
  the modules it installs.

  `Seo::put($key, $block)` in `webx-ui/module-seo` keeps one JSON-LD block per key for the request.
  The last one put wins, and `@webxSeo` prints it after the pushed ones. Two FAQ blocks on one page
  now give one `FAQPage`, not two.

- c7b0084: The FAQ for agents and for a new site. `faq_list`, `faq_get`, `faq_create`, `faq_update`,
  `faq_delete` and `faq_reorder` go through the same list, form and order code as the panel; a
  question is named by its id or its anchor, and every row says the languages a reader sees it in.
  `faq_create` writes the question, its categories and the project's fields in one transaction, so a
  refusal leaves nothing behind, and a category that does not exist is refused rather than dropped.
  `faq://catalog` lists every category with its questions in its own order, unpublished ones marked,
  and the questions in no category at the end. The categories get the shared `faq_categories_*`
  tools, which for categories without addresses (`prefix: null`) no longer take or answer with a
  slug and find a category by its title. Every module's `*_categories_create` now answers with the
  category as stored: a new visible category was reported as hidden.

  `webx:demo` seeds three categories and ten questions, installs the offered FAQ block type when the
  site lacks it, puts a page `/faq` with every category and the filter on a site with
  `module-pages`, and adds "Questions about payment" to a demo service on a site with
  `module-services`.

  The agent's catalogue of field types now describes `wx-collection`.

- c7b0084: New package `webx-ui/module-faq`: questions and answers with flat categories. A question has no
  page of its own; it reaches the site in the FAQ block the module offers
  (`webx:blocks:offered --install --module=faq`), on any page. The block shows every category with a
  filter, or the categories the editor picks, as an accordion that works without JavaScript, and
  opens the question a `#anchor` link points at. A question is shown in a language only when both
  the question and the answer are written in it. The anchor is made once from the question and never
  changes. One `FAQPage` per page through `module-seo`, on by default only when the block shows all
  categories. In the panel: `faq.form` and `faq.category-form`, two orders, the bin, and fields of
  the project in `extra`. `webx:setup` offers the module.

## 0.36.0

### Minor Changes

- 928828b: A password over a site while it is being tested. `WEBX_SITE_GATE=true` and
  `WEBX_SITE_GATE_USERS="client:secret"` put HTTP Basic in front of every address the site answers,
  including addresses that do not exist. Global middleware in `webx-ui/module-admin` does this; a
  member of the `web` group would let every 404 through. The panel and its JSON stay open. So do
  `/.well-known`, the MCP server and its OAuth endpoints (`webx-ui/mcp`), and a block preview under
  a valid token, the block editor's stage and the block bundles (`webx-ui/module-blocks`). A site
  opens more with `webx-admin.gate.except`, and a package opens its own through `Gate\Openings`.
  Switched on with no pairs, the gate lets nobody in, and `webx:doctor` fails on that. It also
  fails when `WEBX_SITE_GATE` is set under a published config that has no `gate` block and so
  closes nothing.

## 0.35.1

### Patch Changes

- b7caa68: A round of panel fixes.

  - `useModal` finds its host when the modal was opened inside `app.runWithContext()` — which is where vue-router runs every guard. "Leave without saving?" from `onBeforeRouteLeave` answered nothing: its buttons were the stand-in's, the dialog stayed open and the navigation hung.
  - The shared category screens are a component per module, so going from the blog's rubrics to the services' categories mounts the list anew instead of keeping the rubrics on a page titled "Categories".
  - The panel's toasts stack at the bottom centre instead of the bottom-right corner, where they covered the action bar's buttons.
  - A category's cover is a card under its name rather than a tab of its own; the cover of an article and of a service sits right under the name too.
  - A number field on a described screen stops at 240px instead of stretching to the width of a title.
  - A new icon, `briefcase`, and the services section wears it instead of `star`.

## 0.35.0

### Minor Changes

- 298c894: `webx-ui/module-services` for an agent: `services_list`, `services_get`, `services_create`,
  `services_update`, `services_publish`, `services_unpublish`, `services_delete` and
  `services_reorder` go through the same list, screen and order code as the panel — a field a
  project patched onto `services.form` is written and refused by an agent exactly as by an editor,
  and a reorder with `category` moves only that category. The categories get the shared
  `service_categories_*` tools, and `services://catalog` gives an agent every category with its
  services in its order, drafts included, before it writes. `webx:demo` seeds three categories and
  eight services with covers and blocks, one of them in two categories at a different place in each.
- 298c894: `webx-ui/module-services`: a catalogue of services — services made of blocks with a draft and a
  history, flat categories that are pages of the site, both on one level under one prefix, an index
  route, two orders (the whole list and each category), breadcrumbs through the main category, a
  schema.org `Service` naming the site's `Organization` as provider, and fields of the project in
  `extra`. The panel's API: the whole list without pages, narrowed by category (in that category's
  order), state or words; the editor's record with a revision (409 on a stale one), draft, discard,
  publish, bin and history; the order of the list and of each category; two sections in a Services
  group.

  Alongside it: a refused address now names whoever holds it (`routing`); the site's `Organization`
  block carries an `@id` other blocks can point at (`module-seo`), which also patches its SEO card
  onto the two new screens; `wx-slug` is a shared slug field type in `module-admin`, and `services`
  is in the catalogue `webx:setup` offers; `OneSpellingPerAddress` moves from the blog to
  `localization`, since the second module with a list page needs it too.

## 0.34.0

### Minor Changes

- 42d427e: Shared categories and fields of the project, with the blog's rubrics moved onto them.

  - `webx-ui/module-admin`: `WebxUi\Admin\Categories` — the code every module's categories share.
    `$table->category()` and `$table->categoryLinks()` lay down the tables; `IsCategory` (with the
    `Category` contract and a `CategoryKind` that names the screen, the permissions and the module's
    words) refuses to go into the bin while it holds items; `HasCategories` keeps two orders in the
    link table — the categories of a record, the first being the main one, and the record's place
    inside a category, kept when it is saved again and given by the order of the whole list when it
    is new; `Ordering::move()` writes either. `CategoryRoutes::register()` gives a module the whole
    API from its own route file (list, create, show with the values of the screen, update by
    `values`, delete, restore, reorder), `CategoryRoutes::items()` the reorder of its records;
    `CategoryLinkSource` and `CategoryTools` (list, create, update, delete, reorder) do the same for
    the link picker and for agents. New field types `wx-category-slug` and `wx-categories` — the second checks the chosen ids against the model its `source` names, which a module registers in `CategorySources`.
  - `webx-ui/module-admin`: fields of the project. `ScreenRecord` sorts what a described screen
    saved into the record's own fields, fields stored elsewhere and the rest — which now goes into
    `extra` instead of being dropped, merged rather than replaced, language by language for a
    localized field. `HasExtra` reads one of them on the site through its field type:
    `$service->extra('price-from')`. A module's screen keeps a `project-fields` card for a patch to
    add to.
  - `webx-ui/module-blog`: rubrics are the blog's categories. The API keeps its address
    (`blog/rubrics`) and gains `GET blog/rubrics/{id}` and `restore`; `PUT` now takes `{ values }`
    of the new screen `blog.category-form` (content, image, and the SEO card patched in by
    `module-seo`). Agents get `rubrics_create`, `rubrics_update`, `rubrics_delete` and
    `rubrics_reorder` behind `rubrics:write`. A field a project patches onto an article or a rubric
    is saved in `extra` — through the draft for an article. A new migration adds `extra` to both
    tables and `item_position` to `article_rubric`, filled by date. The rubrics of an article are the shared `wx-categories`; `wx-article-rubrics` is gone.

## 0.33.0

### Minor Changes

- e7bc9ba: hreflang, breadcrumbs and schema.org for entities, and the sitemap in the panel.

  - `webx-ui/module-seo`: the `<head>` of a page now prints `<link rel="alternate" hreflang>` for
    every language the entity is visible and open to the index in, plus `x-default` (only with the
    language in the path), a `BreadcrumbList`, the entity's own schema.org blocks and a
    `twitter:card`. Each line of the sitemap carries the same `hreflang` set. New contracts
    `HasBreadcrumbs` (with `Crumb`) and `HasStructuredData`; `Seo::push()` for JSON-LD that belongs
    to the response rather than the entity. The trail starts at the site's home, named by the new
    `seo.home-crumb` setting (per language, "Home" from the dictionary until written).
    `<x-webx-seo::breadcrumbs :for="$entity" />` prints the visible crumbs from the same list.
    `webx-seo.print` gains `hreflang`, `breadcrumbs`, `structured_data` and `twitter`.
    `GET`/`POST /api/cms/seo/sitemap` and the MCP tool `seo_sitemap_status` report the files, the
    counts, when the map was built and how many visible addresses were left out and why;
    `test-url` says whether an address is in the map and why not.
  - `webx-ui/module-pages`: `Page` implements `HasBreadcrumbs` — the pages above it, an unpublished
    one left out. The fallback view prints the crumbs.
  - `webx-ui/module-blog`: `Article` (feed → main rubric → article, a `BlogPosting`), `Rubric` and
    `Tag` (feed → it) implement the contracts; a rubric page pushes an `ItemList` of its articles.
    The fallback views print the crumbs; the article's rubric link above the title is now its trail.
  - `webx-ui/site`: the skeleton no longer ships Laravel's static `public/robots.txt`. The web
    server hands that file over before the application is asked, so on a site made from the
    skeleton the `seo.robots-txt` setting and the `Sitemap:` line never reached a visitor.

- e7bc9ba: The sitemap, and a canonical on every page.

  - `webx-ui/routing`: the `Visible` contract — `isVisible()`, `scopeVisible()` and
    `visibleUpdatedAt()` — so that a handler and the sitemap ask an entity the same question.
  - `webx-ui/module-seo`: `/sitemap.xml` as an index with a file per registry type
    (`/sitemap-{type}.xml`, numbered past `webx-seo.sitemap.per_file`), built from canonical rows
    of every type whose model is `Visible` and filtered by the same resolver that prints the
    `<head>`: `noindex` or a canonical pointing elsewhere keeps an address out. Named routes with no
    entity join through `SitemapRoutes::register()`. Built on the first request and cached under a
    generation that moves on every save of a registry row, a card, a rule, a visible entity or an
    `seo.*` setting, with a day's TTL for what changes without a save; `webx:seo:sitemap` builds it
    ahead. `robots.txt` gains a `Sitemap:` line unless one is written. A page with no canonical of
    its own now names itself, keeping only `?page=` of the query (`webx-seo.canonical`).
  - `webx-ui/module-pages`, `webx-ui/module-blog`: `Page`, `Article`, `Rubric` and `Tag` implement
    `Visible` and their handlers answer 404 by it; the blog feed is in the sitemap.
    `Rubric::scopeVisible()` takes an optional locale now.

## 0.32.1

### Patch Changes

- 35d229c: Block thumbnails are drawn in the site's own clothes: the panel loads the stage page once, keeps its stylesheets, fonts and the wrappers around the block's place, and drops the header, the footer and every script. The manifest names the stage (`meta.stage`) once `webx-blocks.layout` is set; without it the thumbnails stay bare.

## 0.32.0

### Minor Changes

- 7bdeb63: Screens and block schemas can use every form control of the core. The registry now knows
  `wx-checkbox-group`, `wx-segmented`, `wx-slider`, `wx-rate`, `wx-time-picker`,
  `wx-date-time-picker`, `wx-date-range-picker`, `wx-tags-input`, `wx-autocomplete`,
  `wx-icon-picker`, `wx-code-editor`, `wx-cascader`, `wx-tree-select` and `wx-transfer`, plus
  `wx-heading` for display. `module-admin` registers a field type for each on the server — rules
  that check options, bounds, date formats and lists, and `store()` that casts and keeps an emptied
  list or date as `null`. `wx-date-time-picker` always writes the offset and is kept as ISO 8601 in
  the application's timezone. The agent catalogue and the block help list them too.

## 0.31.0

### Minor Changes

- fde8622: The block editor draws a block on a page of the site, not on the browser's defaults.

  `webx-ui/module-blocks` gets `webx-blocks.layout` — the component the editor's stage stands in, the
  same `<x-layout>` the pages use — and `/_preview/block-stage`, which prints that layout with an
  empty place for the block. `webx:panel --sync` sets the key and `webx:doctor` warns while it is
  empty, as they do for pages and the blog; empty prints `webx-blocks::standalone`, a bare document.
  `render` names the stage in its answer.

  `@webx-ui/module-blocks`: `BlockStage` loads the stage once and swaps the block and its styles in
  on every change, so the header and footer do not redraw under typing; a new script reloads the
  page. The site's links are inert there, and the stage scrolls to the block. A server without the
  stage still gets the bare document. `frame.ts` gains `freezeFrame`, `fillStage` and `mountScript`.

  `@webx-ui/module-menu`: a long address under a menu item ends in `…` instead of running out of
  the card.

### Patch Changes

- 114a949: `webx-ui/module-blocks`: what a block keeps is what its field type keeps.

  A described screen has always written through its types — `ScreenValues::validate()` looks a node's
  type up, checks the value against `rules()` and casts it with `store()` — and a block's values are
  screen nodes too, but nothing on the way in ever asked them. `Rendering\Values` had done the mirror
  of it on the read side since the module shipped; `ContentValues` is the missing half, and both write
  paths now go through it: the editor's save (`PageForm`, by way of `HasBlocks::storeBlocks()`) and the
  agent's `blocks_set_content` / `blocks_edit_content`. A type that lowercases a colour, casts a number
  out of the string a form sent, or runs pasted markup through an allowlist does that work in a block
  from now on, and not on screens only.

  What passed through untouched still does, and for the same reasons: a value whose key the block's
  schema does not name, a value of a type nobody registered, and a nested tree of blocks, which is
  walked as blocks rather than handed to a field type. The node itself is merged rather than rebuilt,
  so `key`, `hidden` and whatever structural key comes next survive a save. The schema walk both
  directions share is now one class, `Blocks\Schema`.

- a0556f1: A list of records reads as a list: folded rows named `#1 · …`.

  `@webx-ui/core`: `WxRepeater` names a row by its position and then its `itemLabel` — `#2 · Lviv`,
  `#2` without one — and cuts a long header with an ellipsis. A key that holds a translated field
  shows the language being edited, else whichever is filled in; before, such a map made the header
  fall back to a bare number. `dragLabel` joins `addLabel` and `removeLabel`, so the grip's name can
  be translated too.

  `@webx-ui/schema`: `wx-repeater` on a screen starts folded unless the node sets
  `collapsed: false`, and takes its words — add, remove, reorder, the empty text — from the panel's
  dictionary; a node's own `addLabel` and the rest still win.

  `webx-ui/module-admin`: `screens.repeater.*` in all ten languages. `webx-ui/module-blocks`: the
  guide for agents names the repeater's props.

  A row lines its grip, header and actions up on one centre, and its fields run under the actions —
  and, in a repeater narrower than 560px, under the grip as well, so a phone gives the fields the
  whole width.

  `webx-ui/module-blocks`: `wx-row` and `wx-col` inside a `wx-repeater` of a block's schema no longer
  hide the fields in them. The bridge that names a block's nodes by their `id` named the layout too,
  and a named node is a field to the walk — so an image in a column was never resolved on the site.
  Layout keeps its id and gets no name; `Schema::LAYOUT` is the one list of those types.

## 0.30.0

### Minor Changes

- 8e0d587: A link is chosen rather than typed: the contract for what a panel can point at

  The address registry answers "what is this entity's address". Nothing answered "what can I link to
  at all" — a `RouteType` has a model, a formatter and a handler, and nowhere in it a title to show or
  a way to search — so every field that wanted a link had to be told by hand. This is that second
  question, and it lives in the frame rather than in any one section, because the menu is only the
  first of the fields that will ask it.

  On the server: `LinkSource`, `LinkCandidate` and the `LinkSources` register that content modules fill
  on boot, the `Link` value every place keeps a link as, and four addresses under `/api/cms/links` —
  the sections of the picker filtered by the reader's permissions, a search inside one, a resolve of
  several types in one query per type, and the site's own named addresses for the field where a path
  is typed. `module-pages` registers pages, `module-blog` registers articles, rubrics and tags.

  `available` is deliberately apart from having an address: the registry holds one for a draft too, so
  a picker that trusted it would offer a link to a page the site answers 404 for. A draft is offered,
  drawn dimmed, and left out by whoever renders.

  The anchor is a field of the link rather than part of the address. A typed address can carry one
  inline; a chosen page has nowhere to write one, because its address is looked up rather than
  written. So `hash` sits beside the target, is kept without its `#`, and is appended on every read —
  and an address typed as `/about#team` is taken apart on the way in, so that a link cannot end
  `#team#top`.

  In the browser: `WxLinkPicker`, `wx-link` on described screens, and `createLinksApi`.

  In `webx-ui/routing`: `SiteUrl`, with the language prefix that used to be private to `HasUrl` — a
  hand-written `/account` needs the same prefix an entity's address gets, and a second reading of the
  strategy is a second reading that drifts.

- 8e0d587: The menus by their other doors: six tools for an agent, the catalogue to read first, and demo
  content

  `webx-ui/module-menu` now offers `menu_list_menus`, `menu_get_tree`, `menu_add_link`,
  `menu_update_link`, `menu_move_link` and `menu_remove_link`, under `menu:read` and `menu:write`.
  The names are longer than the module prefix needs and deliberately so: a real client shows a tool
  by the part of its name after that prefix, and `menu_list` beside `menu_get` would stand in a
  connector's settings as "List" and "Get" next to everybody else's.

  They go through the doors the panel goes through. Where an item points is normalised by the same
  `Link` a block field keeps and checked by the same rules the dialog is checked by — now
  `Menu\Panel\ItemInput`, so there is one list rather than two that look alike — and what a position
  among siblings means is `Menu\Tree\Placement`, which the drag and the tool now share. An item an
  agent wrote is an item the panel would have accepted, and the cache is forgotten by the model
  events either of them raises rather than by a line in a handler.

  Every writing tool takes `dry_run: true`. Menus themselves are not made here at all: a declared
  menu is a template asking for that spelling, and one of somebody's own is made for a template a
  person is writing.

  `menu://menus` is what an agent reads first — the menus with the looks each one offers, the kinds
  of thing that can be linked to, and the house rules that are easy to get wrong silently: hang an
  item on an entity rather than on a path, because an entity carries its address and a typed path
  goes stale without saying so; write a path without its language prefix; leave the label out where
  the entity's own name will do.

  `webx:demo` now fills a header and a footer out of the pages it has just created — all three kinds
  of target on one site, since the difference between them is the thing a screenshot cannot show.
  The pages come out of the run's journal rather than out of a query, which is what `requires:
['pages']` buys. The `menus` rows are deliberately not written down: the only ones this creates
  are declared, a declared menu refuses to be deleted because a template names it, and an entry
  `--remove` could not undo would be worse than an empty menu left behind.

- 8e0d587: The **Menus** section: the menus of a site on the left, the tree of one of them on the right

  `@webx-ui/module-menu` is the panel half of `webx-ui/module-menu`. One screen and no editor under
  it — a menu is arranged in place and an item is a dialog over the tree it belongs to — with which
  menu is open kept in the address, so that "the footer" is a link somebody can send.

  Dragging changes both the order and the parent. Every level is its own list and they share a group,
  so where a row ends up is where it is, rather than a guess about how far sideways it was dropped.
  Each level reports its own new order and the screen works out which item moved; one drag is one
  `move`, and a refusal puts the tree back rather than leaving the screen disagreeing with the
  database.

  An item points at one of three things and says which: an entity chosen from `WxLinkPicker` — the
  same picker every link field in the panel opens — an address of your own, or nothing at all, which
  is what a heading is. A draft target is drawn dimmed and marked **Not on the site** rather than
  hidden, because a menu is built before the pages in it are published.

  The cache is marked under every menu — "built today at 08:10", "not built", "off" — with a reset
  beside it and one for every menu in the head of the section. It is not "rebuild": the records are
  forgotten and the next visitor builds them again. It exists because the list of places a menu can
  change from ends where bulk operations begin, and it is what somebody presses to test the guess
  that they are looking at something stale, instead of finding out where artisan lives. The mark is
  read again after the reset, since a button that leaves it saying "built today at 08:10" is a button
  nobody believes twice.

  On the server: nine addresses under `/api/cms/menus`, including both cache resets, a menu resource
  carrying `cache: { enabled, built_at }` and an item resource carrying the resolved target, so the
  screen never goes looking for a name.

  In `@webx-ui/core`, `WxListDetail` now also says whether an open record still stands beside the
  list (`detail-inline`), the way it already said it about the chooser's column. It is what lets a
  screen open its first record where there is room for one without raising a panel over a list
  nobody has touched on a phone — and it is only said once the pane has been measured, since an
  unmeasured pane answers "inline" to every threshold.

  In `@webx-ui/module-admin`, `LinkUrls` gains `candidates()` and `hrefWith()`: a screen that draws
  forty links resolves them in one query per kind instead of forty.

- 8e0d587: A new site can be built with menus, and the skeleton's header hands over to one

  `webx:setup` offers the modules `Setup\Catalogue` knows by name, and a name it does not know is
  a refusal rather than a `composer require` of whatever turns up — so a section missing from that
  list is one a new site cannot install at all. `webx-ui/module-menu` joins it, and joins it among
  the defaults: the header of a site is not an optional part of it.

  The skeleton's own header was the example that module was written to end. It asks `menu('header')`
  first now and keeps the page tree underneath it, for the day between creating a site and filling
  its menu in: a header that is empty on the day a site is created reads as broken rather than as
  waiting.

- 8e0d587: `webx-ui/module-menu`: the menus of a site, and the helper that prints them

  One tree per menu, scoped by `menu_id`, with items that point at an entity, at a path, or
  deliberately nowhere — said out loud in a `target` column rather than guessed from which other
  column happens to be empty. A group heading is a flag of its own beside it, because how to draw an
  item and where it goes are two questions, and a heading with children and a page of its own is an
  ordinary thing.

  Which menus exist is configuration: `webx-menu.menus` names the ones the templates ask for, and the
  row in `menus` appears the first time one is saved — no write on boot, no synchronise command. A
  declared menu can be emptied but not deleted and not renamed, because a template refers to its
  spelling; an administrator makes and removes their own.

  Outwards it hands over data rather than markup:

  ```blade
  @foreach (menu('header') as $item)
      <a href="{{ $item->url }}" @class(['is-active' => $item->isActive()])>{{ $item->label }}</a>
  @endforeach
  ```

  A `MenuLink` carries the label, the address, the children, the flags and `attrs()`. There is a
  `<x-webx-menu::menu>` too, and it is second on purpose: a component that has to be overridden is
  worse than a collection somebody writes ten lines against, and `vendor:publish
--tag=webx-menu-views` is how it stops being used.

  One tree for every language, with the labels translated — an item points at an entity, and that
  entity already has a row per language in the registry, so the same item resolves to the right
  address in each. An item nobody has translated is left out of that language rather than printed
  empty, and `locales` covers the case a second tree would have: something in the English footer that
  is not in the Russian one.

  Highlighting is worked out after the cache, on every request, and the front page is the exception it
  has to be: its path is empty, which is a prefix of every address on the site.

  The cache is per menu and per language, forgotten by name. Everything that changes what a menu looks
  like forgets it, including the two things that are easy to miss: **moving** an item, which rewrites
  bounds in bulk and never raises `updated`, and publishing an entity, which changes no address at all
  and would otherwise keep a page out of the menu until something unrelated was edited. Beyond that a
  TTL of an hour and a switch, because "my edit has not arrived" looks like a broken save rather than
  like a cache.

  Two or three queries per menu whatever its size: the items in one ordered walk, then one `resolve()`
  per kind of entity in it.

### Patch Changes

- 1dd017c: Setup: the database server is a question now, but only when it needs to be

  `webx:setup` asked for the database _name_ and took the address it would be created at without
  ever asking: the `--db-*` options, then `.env`, then `127.0.0.1:3306` as `root`. On a machine
  where the local MariaDB listens somewhere else — OSPanel gives each of its database modules a
  loopback address of its own — that meant six answered questions and then a stop at the step
  that had already rewritten `.env`, on a host nobody had been offered the chance to name.

  The address is now reached for before anything about the database is asked. A machine that
  answers is never asked about it and the run reads exactly as it did. A machine that does not is
  told what refused it, and asked for the host, the port, the user and the password with what was
  just tried as the defaults — an empty answer to the hidden password field keeps the one in
  `.env` — and then it tries again. Three answers that still reach nothing end the run with the
  message that names `--db-host`, `--db-port` and `--db-connection=sqlite`; so does the very first
  failure on a run with nobody in front of it, so `--no-interaction` behaves exactly as before.

## 0.29.1

### Patch Changes

- 0166176: Pages: the branch a page carries is counted without the bin

  `descendants_count` on a page row, and `descendants` on the agent's summary of one, were
  arithmetic on the nested-set bounds — and a trashed page keeps its bounds on purpose, so both
  numbers went on counting pages that were already in the bin and going nowhere. The panel says
  this number out loud before a delete and before a restore: the home page of a site with one
  deleted page under it offered to take six pages off the site and would have taken five.

  Both now answer the branch that actually moves — the live descendants of a live page, and for a
  row in the bin the branch that went down with it, which is what a restore brings back. Counted
  once per list by a subquery rather than once per row.

- 6d3b09e: The backup command no longer names a path that Laravel has moved. `storage/app/backups` has
  not been where the dumps go since Laravel 11 rooted the `local` disk at `storage/app/private`,
  and the command's description, the `Backups` docblock and the published config all still said
  it. The wording now points at `path` under the root of `disk`, which is what the code has
  always read and what stays true the next time the root moves.

## 0.29.0

### Minor Changes

- 240ea2e: `webx:boot` is what a container does between starting and serving — waiting for the database, migrating, keys, languages, block types, the first administrator, caches — worked out from the modules installed rather than written into a script; the skeleton ships the Dockerfile and the two compose stacks that call it

## 0.28.0

### Minor Changes

- 483c692: Each Composer package names its npm half, and `webx:panel --sync` wires in what is installed
- 3aa2f5d: Every module brings its own demo content, and `webx:demo --remove` takes all of it back out
- b24f7d1: The public views of `module-pages` and `module-blog` stand in the site's layout, and the two Blade tags are namespaced: `<x-webx-inbox::form>` and `<x-webx-seo::head>`
- 0396cc0: `webx:doctor` checks a site the way a deploy needs it checked: both halves, the bundle, npm ranges, migrations, storage, the layout seam, languages, caches and limiters
- 9ef9a3d: A new site is one command: the `webx-ui/site` skeleton and `php artisan webx:setup`

  `composer create-project webx-ui/site example.local` now leaves a Laravel application with the
  panel on it, the modules that were asked for, a database that did not exist a minute ago, an
  administrator and something to look at. The skeleton lives in `php/site` and mirrors to
  `webx-ui/site` the way the packages mirror to theirs.

  `webx:setup` asks the questions with defaults read off the directory, the `.env` and `git
config`, writes the `.env` by replacing rather than appending, creates the database over PDO,
  installs the chosen modules, wires the panel in through `webx:panel --sync`, migrates, seeds the
  languages, creates the first administrator, builds the front end and seeds the demo content. It
  is the same command on a site that has been running for months: run it again after installing a
  module and it adds what is missing and changes nothing else.

## 0.27.2

### Patch Changes

- 3926a30: Both connector vendors answer at two domains, and the list of return addresses now says so

## 0.27.1

### Patch Changes

- d5b26d6: The dumping tool takes its extra flags from the environment, because only the machine knows it needs them
- 1d3a8a6: The consent screen points at the tab that actually holds the connections

## 0.27.0

### Minor Changes

- dce896c: Every call an agent makes is written down, and the panel shows who did what

  An agent acts in an administrator's name, and until now nothing said afterwards what it had
  done. Now every tool call lands in `mcp_calls`, the way every sign-in lands in
  `cms_login_records`, and the administrators section shows the trail:

  - **One row per call, whichever way it went.** `webx-ui/mcp` writes it in one place, around the
    whole of the call — so a refusal at the door for a scope, a read-only connection or a missing
    permission is a row with its reason, and so is what the handler threw. A handler that answers
    `ok: false` is written down as refused too. Each row carries who the agent acted as, on which
    connection, the tool, its arguments, whether it was a dry run, and how long it took. No secret
    reaches it: the token and the headers are never looked at, and an argument named like one is
    blanked. There is deliberately no link to what the call was about — tools are about
    different things.
  - **Kept by days.** `webx-mcp.calls.days` (90) is the retention; `webx:mcp:prune-calls` runs
    nightly on the scheduler. `calls.enabled` switches the log off, `calls.arguments_length`
    cuts long arguments.
  - **A view next to the administrators.** `@webx-ui/module-auth` draws **Agent calls** as a
    second view of the section, at `/admins/calls`, for whoever holds `admins.audit` — the
    permission the sign-in trail is behind. It narrows by administrator, by tool and by outcome,
    and the choices on offer are the ones that actually appear in the log. Arguments and the
    refusal's words open under a row. `GET /api/cms/auth/mcp-calls` answers it.
  - The playground panel now has the administrators section, so the view can be looked at on
    `localhost:5174/panel/admins/calls`.

- 5309e37: An address is all a person needs to connect their own agent, and a list is all they need to end it

  The dance, the consent screen, the permissions and the log were done; what was missing was the
  part a person actually looks at. Two screens and a guide.

  - **Connect an agent** — a new section in the system group, behind no permission at all:
    whoever got into the panel may connect an agent, and the agent cannot do anything they
    cannot. It has the address of this panel for agents, large, with a button that copies it;
    three steps for Claude and ChatGPT; a line for a terminal for Claude Code and two lines of
    TOML for Codex; and one-click install links for Cursor and VS Code. The address carries no
    secret — that is the whole point of the OAuth path — so it can be printed, read aloud, or
    left on a page. The server prints it absolute, because it is pasted into a program on
    another machine, and the name the server takes in the client's own list comes from its host,
    so somebody with three sites connected can tell them apart. The section is registered only
    where there is a door to connect to: Passport installed and `webx-mcp.path` not `false`.
  - **Connections** — the agents that have been let in, with what each may do, when it was
    connected and when it was last heard from. Everybody's, as a third view of the
    administrators section, for whoever holds `admins.manage`; their own, at the foot of the
    connect page, for anybody signed in. **Disconnect** revokes the refresh token as well as the
    access token — without the second, a connection that the panel says has ended goes on
    refreshing itself for the month it was given. The row is kept, greyed: the call log points
    at it, and a line saying the connection ended on the 21st is worth more than a gap.
  - `GET /api/cms/auth/connections` (`?all=1` for everybody's) and
    `DELETE /api/cms/auth/connections/{id}` answer both, and `WebxUi\Mcp\Grants\Grants::revoke()`
    is where a connection ends.
  - A guide, `apps/docs/guide/agents.md`: how to connect, what an agent may do and why that is
    exactly what you may do, why not to connect a super administrator, and the two things —
    nightly dumps and the list of return addresses — to have in place before switching it on.
  - The playground panel has both screens, on `localhost:5174/panel/connect` and
    `localhost:5174/panel/admins/connections`.

- 87a538c: The consent screen is the panel's own, and "read only" is a box on it

  When an agent asks to be let in, the person now sees a page of the panel rather than the plain
  one: the site's logo, who is asking and where the answer will be sent, and what the agent will be
  able to do — in the words of the panel's modules ("Pages — view and edit", "Files — view"), not in
  scopes. Under it, the warning that the agent acts in their name and that they are responsible for
  what it does. In the panel's language, all ten.

  - **Read only.** One box instead of a matrix of scopes: tick it and the agent may look and may
    not change anything, whatever the person's own permissions say. A read-only connection is not
    shown the tools that write, and is refused if it calls one it remembers from before.
  - **The consent is written down.** No "I understand" box — the fact of pressing Allow goes into
    `mcp_grants` in `webx-ui/mcp`: who, which client, the address the code went to, whether they
    said read only, which version of the text they were shown, and when. The same row is updated
    when the same person lets the same client in again. `last_used_at` is kept to the minute, so
    a list of connections can say when each was last seen.
  - **A guest is sent to the panel to sign in and brought back.** Passport sends a stranger to a
    route named `login`, which no site with this panel has; now they are sent to the panel's own
    sign-in screen with the consent page as `next`, and `@webx-ui/module-auth` follows a whole
    address on the same site as a page rather than as a route. "Sign in as somebody else" on the
    consent screen ends the session and goes the same way. The sign-in path is
    `webx-auth.login_path`, `login` under the panel's path.
  - The consent screen posts to `{oauth prefix}/consent` rather than to Passport's approve route;
    `scripts/php-smoke.sh` walks the dance both ways, read-only and not, in a real application.

- f623fac: A person connects their own agent with an address and three clicks

  The MCP server used to open only for a token printed from the console, which is fine for whoever
  can already run artisan on the server and no use at all for a designer or a client. Now the
  address alone is enough — `https://example.com/api/cms/mcp`, nothing secret in it — and the
  client finds its own way from there: it reads the 401, discovers the authorization server,
  registers itself, sends the person to the panel to sign in and agree, and leaves with a token of
  theirs. The agent acts as that administrator, so authorship, roles and `is_active` already mean
  what they should.

  - **Passport replaces Sanctum.** Two `HasApiTokens` traits cannot share a model, and Passport is
    the one that can register a client it has never met. `webx-ui/module-auth` carries it, because
    `CmsUser` is what an agent acts as and Passport's user provider accepts only a model that
    implements its `OAuthenticatable`. A site switches it on once, with
    `vendor:publish --tag=passport-migrations`, `migrate` and `passport:keys`; without the keys the
    guard cannot be built and a call with no token answers 500 instead of 401.
  - **The `api` guard** — Passport's driver over the panel's own people — is registered for you
    unless the application has defined one under that name, and `webx.mcp-auth` asks it.
  - **Two doors that ship open are closed.** `config('mcp.redirect_domains')` is `['*']` by default,
    which lets anybody register a client called "Site panel" that takes the code to their own
    server; the list is now Claude, ChatGPT and localhost, and the consent page always shows the
    address a person is about to be sent back to, not only the name the client chose for itself.
    Client registration is rate limited, because nobody has signed in when it happens.
  - **A token granted this way carries one scope for the whole server**, `mcp:use`, because that is
    the only one a client is ever offered. Read module scope by module scope it would be refused
    everything, so it passes the scope gate whole; what limits it is the administrator's own
    permissions. A key that names module scopes is still read scope by scope.
  - **The panel fetches its CSRF cookie from its own route**, `{api_path}/auth/csrf-cookie`, rather
    than Sanctum's — which left with the package. `createHttp` defaults to it.
  - `webx:mcp:token` is gone with Sanctum. Keys for machines, which have no browser to send anybody
    to, come back later as their own thing.

- f5b4d44: An agent can do what the administrator who connected it can do, and is shown exactly that

  Until now the MCP door checked the token's scope and the terms of the connection, and never the
  administrator's own permissions — and a token granted through consent carries one scope for the
  whole server, so an editor's agent could do anything any module offered. Now every tool is behind
  a panel permission, checked in the same place as the scope, before any handler runs:

  - **A permission per tool, derived the way the scope is.** A tool that writes needs
    `<module>.manage`; one that reads needs `<module>.view` — or `<module>.manage`, because the
    panel's own routes let an editor at the list without a separate `view`. A module whose
    permissions are not named after it says so on the tool: `Tool::read(..., permission: …)`, one
    name or several that mean "any of these". `webx-ui/module-blog` (`blog.articles.*` and
    `blog.taxonomy.manage` for three module ids), `webx-ui/module-inbox` (`inbox.update` for
    moving a submission along, `inbox.manage` for the forms) and `webx-ui/module-media`
    (`media.upload` for `upload_from_url`) say so; the sign-in audit tools of
    `webx-ui/module-auth` are behind `admins.audit`.
  - **`tools/list` is what the caller may use.** A narrower role sees a shorter list, and a tool
    that is not listed is not there to call by name either. On the stdio server there is nobody to
    ask, so everything is listed. A call that gets past the list — a client remembering a tool
    from before a role was taken away — is refused with the permission it lacks.
  - `webx:mcp-tools` shows the permission next to the scope.

- cd95a2e: A gzipped dump of the database every night, and one line in the panel saying so

  Insurance, not a restore system. The file lands on the same disk as the database it came from,
  so it survives a mistake and not a dead server, and there is no restore button anywhere — what
  it is for is getting yesterday's version of one row, one table or one article back by hand. It
  exists because backups are an extra on a good many hosts and absent on the rest, and having
  something is better than having nothing.

  - `webx:db:backup` writes `storage/app/private/backups/<database>-2026-09-21-0310.sql.gz`,
    gzipped as the dump comes out, so no uncompressed copy of the database ever touches the disk.
    `mysqldump` for MySQL and MariaDB, `pg_dump` for PostgreSQL, a copy of the file for SQLite.
  - Rotation runs **after** a dump has succeeded and never touches the newest file. Clearing out
    last week without having written tonight is the one thing a backup command must not do, and
    it is exactly what happens if the two steps are written the other way round. A failure exits
    non-zero, logs why, deletes its own half-written file and leaves everything else alone.
  - Structure for every table, rows for the ones worth keeping: `cache`, `sessions`, `jobs` and
    the rest of `skip_data` are dumped with `--no-data`, which on most sites is most of the file.
    The tables that keep their rows are dumped structure-and-data together, so pulling one table
    out of the finished file is a single contiguous range — the guide has the one-liner.
  - The password never appears in an argument, where `ps` would show it to anybody with a shell:
    MySQL gets a 0600 defaults file and PostgreSQL a 0600 `.pgpass`, both removed in a `finally`.
    `--single-transaction --quick` so the nightly dump does not lock the site, `--no-tablespaces`
    so it runs as a shared-hosting user, `utf8mb4` so the translated JSON columns survive.
  - `module-admin` puts the task on the scheduler itself, at `webx-admin.backup.at`. What it
    cannot do is run the scheduler: the site still needs a system cron on `schedule:run`, and the
    line in the panel is what notices when there is not one.
  - That line is at the foot of the settings screen, for whoever has `settings.view`: "Last
    database snapshot: today at 03:10 · 4.2 MB", and the same line as a warning when the newest
    file is more than two days old or there is none. Nothing is recorded in the database — the
    line is the newest file in the directory, and a task that failed is the file that is not
    there. `WxBackupNote`, fed from a new `backup` key in the manifest.

- b1aeb52: Light, dark or the machine's — chosen in the account menu, stored against the person

  The tokens have carried both themes since the beginning, and nothing in the panel ever wrote
  `data-theme`: the only way to see the dark one was to set the whole machine to it. Now there is a
  control, and the choice belongs to the person rather than to the browser — somebody who works
  dark at night on a laptop finds the panel dark in the morning at a desk.

  Three states rather than two. A toggle can say light and dark; it cannot say _I have not
  decided_, which is the state almost everybody is in, because their machine has already decided
  for them. `system` is a real answer and the one the switch starts on, and it goes on following
  the machine afterwards — the panel darkens at sunset along with everything else on the desk.

  - `WxThemeSwitch` — the control, in the core: three cells, a thumb that slides between them and a
    picture that arrives rather than appears. It is a radio group, the arrow keys move within it,
    and both animations stop under `prefers-reduced-motion`. Like everything in the core it ships
    English and knows nothing about a dictionary, so its three words are props.
  - `applyTheme()` now takes `system`, which removes the attribute rather than writing a third
    value — the stylesheet already follows `prefers-color-scheme` for anything not pinned to light.
    `systemTheme()` and `watchSystemTheme()` are there for whatever has to _know_ rather than be
    painted. New `--wx-easing-emphasized`, a curve with a little overshoot in it.
  - The theme contract now works both ways round. The tokens have always had a `data-theme="dark"`
    block and never a light one, so a light island inside a dark page — a preview, a printed
    sheet — inherited the dark values and quietly stayed dark, while the guide claimed a page could
    mix the two. There is a `[data-theme='light']` block now, and it can.
  - `createAdmin()` builds the theme before it mounts, so the sign-in screen is already the colour
    this browser was left in, and `useTheme()` hands it to anybody who asks. The administrator's own
    record replaces the browser's guess the moment the session says who they are.
  - `PUT /api/cms/auth/theme` and a `theme` column on `cms_users`, beside the language and for the
    same reasons. `null` means follow the machine — a choice, and one that has to travel between
    machines like any other.
  - The Blade shell paints before its bundle runs: three lines that read the browser's copy, so a
    dark panel never starts white.

## 0.26.1

### Patch Changes

- 0a506df: A block that stands on one page is refused in words that fit one page

  `page.delete-used` has `:count` in it, and Russian — like English — gets that wrong at exactly
  one, which is when a type is most likely to be looked at: it has just been put somewhere for
  the first time. The dictionary gains `page.delete-used-one` and `page.on-page` in all ten
  languages, and the controller picks the line rather than the number.

  The same file gains the words the editor screen grew this round — the captions of the three script
  examples and the three of the icon picker — and loses `page.sample-help` and `page.usage-empty`,
  whose places on the screen are gone.

## 0.26.0

### Minor Changes

- cca572f: The address of an article is one row, not two

  The settings tab used to hold a field labelled "Address" and, directly under it, a row also
  labelled "Address" printing the whole thing. A full row of the form, and a second label, spent
  on one constant segment — `/blog/` — which taught the reader to skim both. The spec had asked
  for the other thing all along: "the address, with the prefix pasted on the left".

  So the prefix moves inside the control. `wx-article-slug` replaces the pair of `wx-input` and
  `wx-article-address`: a localized text field whose `#prefix` is the prefix of the blog, set in
  the same monospace face the address is read in, with the language chip still on the right. The
  whole address is now read and written in one place, and the card is a row shorter.

  What is kept is the part that is not a duplicate: the line that says an article on the site is
  about to answer at a different address and that the old one will keep working. It appears only
  when there is something to lose, and it still appears before the save rather than in a toast
  after it.

  Gone with the row: `WxArticleAddress` and the node type `wx-article-address`, and the words
  `article.address` and `article.no-address` on both halves. A project that patched the `address`
  node of `blog.article-form` has no node to patch any more — the id is not in the screen.

  The server registers `wx-article-slug` as the text type `wx-input` already was, so what a save
  is checked against does not change.

- cca572f: An article can be taken off the site from its editor, and its tags stand on one line

  **Off the site, from the publication card.** Taking an article off the site was a line in the
  `···` of a row of the list and nowhere else — so an editor looking at the article, on the tab
  where its day and its author are decided, had to go back to the list to pull it. Now
  `wx-article-unpublish` sits under the date, where the rest of the publication is settled. It is
  offered only while there is something to take off — a draft was never there, and one already
  off has nowhere further to go; the way back is "Publish", which stays in the bar. It asks
  first, because this is the one thing on that tab visitors see happen, and the question names
  what survives: the draft, the history and the rubrics all stay, and publishing puts the article
  back exactly where it was. A scheduled article gets its own sentence — it never went out, and
  the day it was set for will pass without it.

  **Tags.** A chip carried a `WxAction` in its slot, and an icon button of the panel is thirty
  pixels tall inside a badge whose words are fifteen: the chip grew to fit the button, the word
  sat three pixels below the cross it stood beside, and the air to the left of the word was half
  the air to its right. `WxBadge` has had `closable` all along, sized to the words — measured, the
  chip is 22.6 px instead of 37.6 and the drift is zero.

  **Rubrics.** The "main" badge stood against the name of the first rubric with nothing between
  them, because the cell a row's content goes into is a block and the `gap` meant for it was
  never applied — and neither was the clipping on the name, which had been written for a flex
  parent that was not there. The slot now makes a line of its own contents: eight pixels between
  the name and the badge, and a rubric with a long name is cut with an ellipsis rather than
  pushing the badge to the far end of the row.

- cca572f: The inbox opens on the submissions, and the forms are the chooser

  Somebody opens "Inbox" to see what has come in. What the section showed them was a column of
  three form names, and on a phone that column was the whole screen: the submissions were a
  record opened beside it, so they lived in the drawer and the reader had to pick a form before
  seeing anything at all.

  The two swap places. The forms are the `filters` column of `WxListDetail` — the thing that
  narrows the list — and the submissions are the list. Nothing moves on a wide screen: the
  forms are still 270px down the left. On a narrow one it is the forms that fold, into a panel
  raised by a **Forms** button in the head of the submissions, and the list is the screen. One
  form is always open — the first, unless the address names another — which also covers an
  address naming a form that has since been deleted.

  `WxListDetail` grew the case that makes this possible: with no `detail` slot, the list is the
  main pane rather than a fixed column with an empty pane beside it, and the only threshold left
  is the chooser's, `filtersWidth + detailMin`. It is the shape for a list whose records open on
  a route of their own — which is what a submission does, and what a file in a library does.

  Gone from the head of the submissions: **Settings**. It is an action on the form, and the
  form's own `···` in the list of forms already offers it beside Duplicate and Delete — a second
  door on the same strip, one word away from the list it was not about. `panel.choose-form` goes
  with it on both halves: there is no longer a moment with no form chosen.

  The button in the head is now **New submission**. The section is opened to read what came in
  dozens of times for every once a form is added, and what stood there in blue was the form: a
  new form is the `+` over the list of forms, beside the things it makes one more of, and in the
  drawer — where an icon alone under the drawer's heading reads as a stray mark — it is a button
  with the word on it. The dialog stays with the list of submissions and is exposed to the head,
  because what is written has to land in that list, in the filter that is on, and be counted in
  its tabs. On a narrow screen **Forms** joins it up there, so the two ways out of the list stand
  together instead of one being in the head of the section and the other in the head of the pane.
  `panel.new-submission` reads "New submission" rather than "Add by hand" on all ten dictionaries;
  the dialog it opens still says which case it is for.

  `WxListDetail` says `filters-inline` whenever the chooser's column appears or folds, and once at
  the start. The `list` slot has always been handed that as a slot prop, but a head that stands
  outside the pane — above the card, where a screen's actions live — cannot read one.

  One inset, kept by the pane. The name of the form, the tabs, the search box and the rows now
  all begin on the same line down the left: the table added a step of its own inside the pane's,
  which is exactly what `flush` says it should not, and on a phone that put the head at 17 and
  the list at 33 — two panels stacked rather than one screen. The change is a rule removed from
  this screen, so no other list in the panel moves.

  What scrolls is now the page. The section used to be as tall as the window with the rows
  scrolling inside a box of their own: a bar down the middle of the screen, and a wheel that
  meant one thing over the rows and another an inch to the left. Every other list in the panel
  scrolls as a page, and this one does too — the card is as tall as what is in it.

  A switched-off form is said by its name, struck through and grey, instead of by a badge
  beside it. The badge did not shrink, so in a 270px column already holding a name, a count and
  a `···` it ran under the menu — measured at 396px against a row ending at 346 — and it said in
  a word what the type says at a glance. The strike is on the name only: the count beside it is
  still true.

  Fixed on the way: between 640 and about 672 pixels the pane and the table measured the same
  threshold a step apart — the pane's own padding stood between them — and the table drew cards
  out of the full set of columns, five lines of "Label: value" for one enquiry. The pane decides
  now and the table is told, so a tablet holds fourteen rows where it held three cards.

- cca572f: Rubrics are one list and a dialog over it

  The section used to be a list beside a form, and the form took two thirds of a screen whose
  whole job is the drag: the order of this list is the order of the menu on the site. Now the list
  is the screen — grip, name, address, the number of articles, and a `···` with `Edit`, `Show its
articles` and `Delete` — and a rubric is edited in a dialog with three tabs: `Content` (the
  name, the address, the switch and the introduction), `Image` and `SEO`. One `Save` for all
  three, and a `422` opens the tab the failing field is on.

  The introduction is a rich text document now (`wx-rich-text`) rather than a line of plain text:
  cleaned by its own field type on the way in, printed with its library pictures resolved on the
  way out. Nothing migrates — the column is the same one, and a line of text is a document with no
  markup in it.

  Two things this fixes on the way: the SEO card used to open at zero width inside the old form,
  and the footer of that form broke apart onto three rows on a one-pixel overflow.

## 0.25.1

### Patch Changes

- 0aea1bb: Fewer words in the article editor's bar

  `webx-blog::article.save` is "Save" rather than "Save draft" in all ten languages: the button
  beside it is the publication, so there is nothing left to tell apart. The three lines the bar
  used to build its sentence from — `live-never`, `live-edited`, `live-off` — go with the sentence;
  what is left of it is the day, and the day is said by `live-since` and `live-scheduled` under the
  article's name.

- e98734c: One word for the block preview's width switcher

  `webx-blocks::page.width` in all ten languages: the name of the group of three device icons that
  replaced the three words in the Blocks section's preview. The icons carry the old lines as their
  own accessible names, so nothing else moves.

## 0.25.0

### Minor Changes

- 537df98: Page and article screens stop asking the constructor to fill the screen

  `"props": { "fill": true }` is gone from the `wx-blocks` node of `pages.form` and
  `blog.article-form`. It told the field to be exactly one window tall and scroll each of its panels
  inside itself; the field does not do that any more, because its preview is now as tall as the page
  it shows and the panel scrolls it. Nothing replaces the prop — the screens simply stop passing it.

  `webx-blocks` also gains three lines in all ten languages: the name of the width switcher, and the
  steps to the previous and the next block, which the form's head carries now that the tree is not on
  screen beside it.

  `webx-admin` gains two — "Saving…" and "Saved" — which are what a screen reader hears from the mark
  that replaced the word in the editors' bars. The lines those bars used to print (`state-saving`,
  `state-saved`, `state-unsaved` under `webx-pages` and `webx-blog`) go, because nothing prints them.

## 0.24.1

### Patch Changes

- 48dfd9e: One head for every screen of the panel

  Eight screens each answered "what goes at the top" on their own, and gave eight answers: the
  heading at three sizes, the way out as an arrow on four of them and as a line of breadcrumbs on the
  rest, the buttons folding into a `···` on two editors and wrapping onto a third line everywhere
  else. Writing a new screen meant writing that line again and getting it slightly different again.

  `WxScreenHead` is that line, once: the way out, the name with the state said beside it, the line
  under it that says which record this is, and what can be done here. `WxListScreen` is built on it,
  so a list and the editor a row opens are the same object rather than two similar ones — and it
  takes `back` now, which is what the statuses screen used to draw above its own heading for want of
  anywhere to put it.

  **The actions are declared rather than drawn.** The same action has to be a button on a desktop and
  a line of a menu on a phone, and one vnode cannot be mounted in two places — as markup it had to be
  written twice, which is exactly what the page and article editors did. As `ScreenAction[]` it is
  written once: `primary` is the one thing the screen exists for and the one that keeps a button when
  the head runs out of room, `danger` is never a button at all, `menu` is in the `···` at every
  width, and `loading`, `disabled` and `href` mean what they say. Below 720px — 480 on a list, which
  carries one word and no trail — everything but the primary folds behind the `···` and that primary
  takes the line under the name, full width.

  The name’s line is the head: the way out at the start of it and the actions at the end, both
  centred on it however many badges stand beside the name. The trail is the line above, and it
  scrolls sideways with no scrollbar showing rather than wrapping — on a phone a path four levels
  deep was two lines of the smallest type on the screen, standing between the reader and the name of
  what they had opened.

  Two things that were quietly wrong come out with it. Nineteen buttons across the panel passed
  `icon="plus"` to `WxButton`, which has no such prop: the attribute landed on the `<button>` and
  drew nothing, so the panel’s main actions had no icons at all. And the `···` said `More` in English
  in every language, because the core carries English defaults and knows no dictionary — the panel
  gives it the word now, in all ten.

## 0.24.0

### Minor Changes

- 937f4e2: The blog gets a picture of its own, and so can every other navigation group

  Two separate things made the sidebar say the wrong thing about the blog.

  **A group could not carry an icon at all.** `AdminNav` drew `icon="gear"` on every branch, so
  "Blog" and "System" looked like the same kind of thing — one is what the site is about, the other
  is what keeps the panel running. A group now names its own picture: `'icon' => 'newspaper'` beside
  the title in `webx-admin.groups`, through the manifest, into `NavGroup`. The key is optional and
  falls back to the gear, so a site that published `webx-admin.php` before this — or a group written
  by a module that has not been updated — looks exactly as it looked.

  **`ArticlesModule` named `file-text`, which was not an icon.** The set has `file-txt`, `file-md`
  and the rest of the file family, but nothing under that name, so `resolveIcon` came back empty and
  `WxIcon` rendered no `<svg>` at all: no warning, no placeholder, just a menu line whose label had
  slid left into the room the picture was meant to occupy. Both halves type-check a name neither of
  them can check, so the seam is now tested — every `icon()` and every `'icon' =>` in the PHP
  packages is looked up in the set.

  New in `@webx-ui/core`: `file-text`, the page with three lines of prose that the file family
  already drew, under the name a section full of writing asks for; and `newspaper`, a folded sheet
  with the one behind it curling out at the bottom left — the fold is the only thing that tells a
  paper from a document at 16 px.

- 852883d: The panel's lists take their filters behind the funnel and draw their narrow rows as entities.

  `WxFilterChips` and `AppliedFilter` in `module-admin` give every section the same chip, and the
  panel's own two words — the name of the funnel and "reset all" — live with it in all ten
  languages. Articles, the SEO rules and the administrators put their dropdowns in `#filters` and
  what they are set to in `#applied`; submissions, administrators and articles draw a card below
  their breakpoint as `WxEntityCard` rather than as a stack of labelled lines, with the `···` in
  the card's own top strip beside the checkbox.

  `WxEntityCard` gained `titleLines`, because an article's headline is a sentence: one line of it
  on a phone is half a thought, and the list it replaced already clamped at two.

### Patch Changes

- 852883d: Tags: the order is on the headings, and renaming is a form.

  The two buttons over the list are gone — the name and the count sort from their own headings, in
  either direction, and the address carries the order so a link lands on the list somebody meant.
  The server takes a leading minus for it and keeps the bare names it had: alphabetical, and most
  used first.

  Renaming opens a dialog with one field. In the cell it was a name that turned into an `<input>`,
  which reads as a name — nothing said it could be typed in — and it saved itself on `blur`, an
  event that does not bubble, so the listener on the field's wrapper heard nothing and clicking away
  lost what had been typed.

  `WxActionBar` wraps its buttons. They were `flex: 0 0 auto` and stayed on one line whatever the
  width: measured on a 375px screen, five of them were 815px inside a bar 359 wide, and they took
  the whole page sideways with them.

## 0.23.0

### Minor Changes

- f87e4ec: The article editor: tabs, blocks, autosave, the day it goes out

  `blog.article-form` is a described screen, like the page editor and for the same reason: the SEO
  card arrives as a patch from `module-seo` rather than being named in the blog's own description,
  and a project adds a tab the same way. Four tabs — the block constructor, the settings, SEO and
  the history — with a head above them that never moves and an action bar below.

  The settings are §10 of the spec: the address printed whole under the field that edits its last
  segment, the lead with a counter, the rubrics as a list that is dragged into order because the
  first one is the main one, a tag box that makes the tag it cannot find, the author, the cover,
  the pin, and the articles pinned under this one by hand. Five of them are node types the blog
  registers on both halves, so a rubric that is not a rubric is refused where every screen is
  checked rather than wherever somebody remembered.

  **The day is the part that is not a page editor.** The date in the settings tab is what
  "publish" publishes under, and the bar says which day that is before it is pressed: ahead, the
  article waits and answers 404 until its morning; behind, it moves down the feed. For an article
  that has never been on the site the day waits in the draft, because `published_at` is what "on
  the site" means and there is no column for a date that has not happened yet. For one that is
  already dated, moving the date writes the column at once — every listing orders by it.

  `WxActionBar` wraps. Its state box may shrink to nothing, and the words in it went on being
  painted where the box no longer was — straight across the buttons. Measured on a 375px screen:
  the box 0px wide and 105 tall, "Saved · goes out on 25 September at 17:06" over the top of "Save
  draft". Past the width of a short sentence the buttons now take a line of their own, still
  against the end of the bar.

  Saving is autosave, checked against the revision the form read and refused with a 409 when
  somebody wrote in between; the answer carries the article as it now is, so the panel asks which
  version the site gets instead of keeping one of the two silently. `PUT` now takes the screen's
  `values`, the history has its own two routes, `POST .../discard` throws away what is waiting,
  and `GET|POST /blog/tags` is the half of the tags API the article form needs — the screen that
  rakes them over comes with session D.

- f87e4ec: `Blog`: the section, the panel API and the list of articles

  The blog arrives in the navigation as three entries under one heading — Articles, Rubrics,
  Tags — because the panel draws one entry per module and a blog wants three. Rubrics and tags are
  declared on the server and stay out of the menu until their screens are written: an entry with
  no screen has nowhere to send anybody, so it is silently skipped.

  The API is a paginator rather than a level of a tree, which is the whole difference from
  `Pages`: `GET /api/cms/blog/articles` with a search term, a rubric, a tag, an author and a
  state, plus create, save, publish, unpublish, delete and restore. Every filter is a subquery and
  none of them is a join — an article is in several rubrics and carries several tags, and joining
  the pivot turns a page of twenty into seventeen articles with three of them drawn twice.

  Five states, and the pair worth keeping apart is the last two: an article that was never
  published and one that was taken off the site this morning both have no publication date, and
  only the history tells them apart. Publishing takes an optional date, so "on the site next
  Tuesday" is that date and not a scheduler.

  What is saved goes to two places, and the split is deliberate. The title, the address, the lead
  and the cover go into the draft — the site keeps showing what was published. The rubrics, the
  tags, the related articles and the pin do not, and cannot: a pivot row is not a column, and
  there is no such thing as half a row. A translated field travels as its whole language map, so
  saving from a Russian panel that is showing an English fallback no longer copies the English
  title into the Russian slot.

  `@webx-ui/module-blog` is the front end: the list with its filters, its views as tabs, the bin,
  and a row menu. Below 640 pixels the row becomes a card with the cover on the left and the title,
  one rubric, the state, the date and the author beside it — ten articles on a phone screen rather
  than two.

- f87e4ec: The blog through an agent's doors, and the guide

  Nine tools, one resource and one prompt, all of them the same doors the panel uses: `articles_list`
  is the panel's own query, so the five states of an article are one answer and not two;
  `articles_update` goes through the described screen, so a tab `module-seo` put on the editor is a
  field an agent can write; `articles_publish` takes `at`, because in this module the date _is_ the
  publication and there is nowhere else for it to live.

  `rubrics_list` only looks, and `tags_create` does not exist — both on purpose. Deciding the site
  has a ninth section is a decision about its navigation, and inventing a tag while writing one
  sentence is exactly how a blog ends up holding "belts", "belt" and "drive belts". What an agent
  gets instead is `tags_merge`, sorted by use so the duplicates stand next to the word they
  duplicate: the irreversible half of the job nobody gets round to, with a dry run that reports how
  many articles would come out carrying the surviving word — counted once, because an article that
  carried both tags is one article.

  `blog://feed` is the last thirty articles as a reader sees them rather than a second editor's
  view. Half of what it is for is finding out that this was published in March; the other half is
  picking up how the blog writes before writing for it. The prompt `write_article` puts the loop in
  front of the agent, and spends two of its lines on the step a first attempt gets wrong twice: the
  body is `blocks_edit_content` and not `articles_update`, and writing `published_at` while filling
  in the settings puts a half-written article on the site without anything named "publish" being
  called.

  `apps/docs/guide/blog.md` is both halves on one page, and the package README now says what an
  agent may do.

- f87e4ec: Blog: the screens for rubrics and tags

  **Rubrics** are a menu, so they are edited as one: `WxListDetail` with the list on the left,
  dragged into the order the site has them in, and the form for the one that is open on the right.
  No paginator and no search — a site has eight rubrics, and a menu you have to search is a menu
  that is already wrong. The form looks up `wx-media` and `wx-seo` in the panel's own type
  registry rather than importing either, so a panel without the file manager or without SEO gets a
  shorter form instead of one that will not mount. The SEO card starts folded behind a sentence
  saying where the title of the page comes from without it.

  Deleting a rubric that still holds articles is refused with the number in the message, and the
  button stays on screen and out of reach with the reason beside it: a button that disappears does
  not answer "why can I not delete this".

  **Tags** are entered from the article form by the hundred, so the screen is built for raking them
  over. Renaming happens in the row — Enter saves, Escape puts back — and the address does not move
  with the word, because a tag spelled three ways before lunch would otherwise leave three aliases
  behind a decision nobody made. Selecting rows raises a bar that opens, closes or deletes the pile
  at once, and merges it: the articles move over, the pivot deduplicates, and a checkbox decides
  whether the addresses that existed go on answering as redirects. The merge is irreversible and
  the dialog says so.

  The column **Indexing** has three states, not two — `indexed`, `indexed — SEO rule`, `noindex` —
  and the filter beside it counts by the same rule the rendered page follows, through
  `UrlRuleSource::hasRuleFor()`. Anything less leaves the editor who wrote the rule looking at a row
  that says `noindex` about a page that is in the index.

  Server side: `GET/POST/PUT/DELETE /api/cms/blog/rubrics` with `rubrics/reorder`, and
  `GET/POST/PUT/DELETE /api/cms/blog/tags` with `tags/merge` and `tags/mass`. The tags endpoint is
  one answer to "which tags are there": the dropdown on the article form asks for its first page.
  `HasUrl` gains `urlOf()`, so a screen that has already loaded the `routes` relation for a page of
  rows does not go back to the registry once per row to learn what it was handed.

- f87e4ec: `webx-ui/module-blog`: the package, the addresses and the public half

  Articles, rubrics and tags. Almost none of it is written here — the address is `routing`, the
  content is `module-blocks`, the draft and the history are `module-admin`, the covers are
  `module-media`, what a page says about itself is `module-seo` — and what the package adds is the
  three things that make an article an article rather than a page: a date, several rubrics, and
  tags.

  Three types in the registry under one prefix (`webx-blog.prefix`, `blog` by default), all
  `OnConflict::Fail` in one flat namespace: a rubric called "Repairs" and an article slugged
  `repairs` are one address, and the second of them is an error under the field rather than a
  quiet `repairs-2`. The rubric is deliberately not part of an article's address — an article has
  three of them, "which one" has no answer, and any answer would be a hidden main rubric that
  moved the article when somebody reordered the checkboxes.

  Publication is one column and no scheduler. `published_at` in the future means the article is
  waiting, in the past means it sits where that date puts it in the feed, and which of the two it
  is gets decided where the article is read. Worth remembering: to the frame underneath, a
  scheduled article is already published, so a general count of live records elsewhere in the
  panel counts it.

  A rubric has `is_visible` instead of a draft, and refuses to be deleted while it holds articles,
  naming how many — its articles are not its property, and a soft-deleted rubric with live
  articles in it is a hole in the navigation nobody notices. Tags merge into one, and the
  addresses that existed can be kept as rows in `seo_redirects`: an alias of `routing` is keyed to
  the entity and dies with it.

  A tag page is out of the index by default, and a rule in `seo_urls` for its address opens it
  completely. That cannot be a merge of fields — a rule filling in a title and leaving `robots`
  empty would leave the module's `noindex` standing underneath it, and the editor who wrote the
  rule would never find out — so `Panel\UrlRuleSource` in `module-seo` gains `hasRuleFor()`, over
  the same compiled list `UrlMatcher` works on, and the blog asks that instead of matching masks
  of its own.

  The public half ships as five bare views, a feed at `{prefix}` with `?page=`, an RSS, and worked
  out "read next": pinned first, then most tags in common, then the main rubric.

- f87e4ec: `wx-rich-text`: the editor as a field of a screen

  A node type on both halves. On the server it is checked against `props.maxlength`, stored
  through an allowlist — a `<script>`, an `onclick` or a `javascript:` address does not survive —
  and an emptied editor is stored as `null` rather than as `<p></p>`. `localized` needs nothing of
  its own: the language map is picked apart one layer up, so a translated article is the same type
  run once per language.

  Pictures come from the file manager. `AdminModule` gains `pickImage`, which `module-media`
  supplies and the panel hands to every editor on every screen; a panel without a file manager
  draws no image button, because the editor does not offer what it cannot do.

  What a document keeps for a picture is the library's **key**, as `data-wx-path`, and the address
  is worked out again on every read through `WebxUi\Admin\Contracts\AssetUrls`. The same rule
  `wx-media` has always followed, one layer in: the address differs between deployments of one
  site, a private bucket's address expires, and an image edited in place changes the version stamp
  without changing the key.

  `WxRichText` itself gains `localized` — one editor with a language chip, as `WxInput` and
  `WxTextarea` have — and `labels`, so the panel can put its own words on the toolbar.

  `HasDraft::publish()` takes an optional `?CarbonInterface $at`: the date an entity is published
  under is not always now, and it cannot travel through the draft.

### Patch Changes

- 5d24fd2: The blog's five public views are plain, not broken

  Two things an unstyled page still owes the reader. Without `max-width: 100%` a 1200px cover
  pushed a phone's page out to 1248px and took every line of text off the screen with it — three
  rules in a partial the four page views include, the same three `module-pages` shows in its own
  example. And nothing derives a title from an entity, so an article whose SEO card was never
  filled had no `<title>` at all: each view now falls back to what it is about when the card and
  the defaults are silent, which is what the demo site had already written by hand for pages.

## 0.22.1

### Patch Changes

- 74d1369: A round of panel fixes, mostly from looking at the two demo sites on a phone.

  - A field stops at a width it can be read at: `WxFormItem` caps its control at
    `--wx-field-max-width` (640px), and what is not a field in that sense says `wide` — a prop on
    the item, `wide: true` on a registry entry.
  - A hovered table row and the `···` at its end no longer paint themselves the same grey: the row
    goes a tone softer, and the row menu carries no fill at rest.
  - Tooltips never open on a touch screen, where the tap that opens one is the tap that was meant
    for the button under it. `useHoverPointer()` is the question, asked once for the application.
  - The panel's step reaches what the panel teleports out of itself — drawers, dialogs, the toaster
    — so a phone no longer lays one screen out with desktop air.
  - Blocks: a row of the tree offers its actions as the panel's `···` rather than three icons that
    only appeared on hover, and removing a block always asks first.
  - Inbox: the form editor no longer draws its save bar across the middle of the form, the section's
    panes stay inside the card's corners, the recipient's bin is red and asks, and a submission
    keeps its notes, its log and its metadata in one card with three tabs instead of three cards.

## 0.22.0

### Minor Changes

- 4644d28: `webx-ui/module-inbox`: the form on the site.

  `<x-webx-form slug="contact" />` prints a form of the panel — a control per field type, the
  honeypot, the hidden timestamp, the captcha block a form asks for — out of views the site
  publishes and rewrites, with no stylesheet and no design tokens of ours following it there.

  It works with JavaScript switched off: the form posts, and the page comes back with the errors
  under their inputs or the thank-you in place. The script adds only that this happens without a
  reload; it is one file with no dependencies, served from the package.

  A field the panel says is not full width carries `wx-form__field--half`. Without it the switch in
  the editor meant nothing on the site, which is worse than not offering it: the package has no
  layout of its own to apply, so naming the field is all it can do and the site's stylesheet does
  the rest.

  The form says which language it was printed in (`webx_locale`), and the intake answers in that
  one. Its middleware is written out by hand and so runs nothing the site added to its own `web`
  group — the language above all — so a Russian page was thanked in English, refused in English,
  and the submission recorded English as the visitor's language. How a site chooses its language is
  the site's business; the one thing always known is what the page came out in, so the form says
  it, exactly as the panel tells the server in `X-Webx-Locale`.

- 4644d28: `webx-ui/module-inbox`: the section by its other doors, and the command that forgets.

  Six MCP tools under `inbox:read` and `inbox:write` — `inbox_forms_list`, `inbox_form_get`,
  `inbox_form_save`, `inbox_list`, `inbox_get`, `inbox_set_status` — through the same rules the
  panel's own editor is refused by: the rules and the row of a form and of a field now live in
  `FormInput` and `FieldInput`, which the form requests and the agent both go through, so a slug
  that is not an address is refused at either door. A save changes only what it names, and a field
  sent with `remove: true` is put aside rather than destroyed, which leaves the answers already
  given through it readable.

  Receiving a submission is deliberately not a tool, and neither is deleting one. What deletes is
  `webx:inbox:prune`: spam older than one age, everything older than another, both from the config
  and both nought by default, row by row through the model so the files on the disk go with them.

- 4644d28: `webx-ui/module-inbox`: the panel's side of the forms, the fields and the statuses (§12).

  Reading the section and changing what it asks are two permissions: `inbox.view` opens the
  column of forms, because that column is the navigation of the section, and `inbox.manage`
  writes a form, a field or a status.

  Two things the settings needed saying out loud. Their keys are literal and several have dots
  in them, so nothing validates them by name — a rule called `options.thank-you.heading` reads
  the dot as a path and the value disappears without an error — and what is saved goes through a
  white list instead, which also keeps a `select`'s choices from surviving on a field that is no
  longer one. A recipient is the one setting refused rather than dropped: an address nobody will
  ever be written to looks exactly like one that works.

  A field's machine name is unique among the live fields of its form only, so a name comes back
  when the field that held it is deleted; a name with a dot in it is refused, because the intake
  would look for a nested array and report the error under a key nothing on the page has. A copy
  of a form is switched off and carries the questions and none of the answers.

  `GET /inbox/recipients` names the administrators a form can be told to write to — the ones who
  may actually open a submission, since a notification is a link and the alternative is a letter
  followed by a 403.

- 4644d28: The submissions: the list, the card, and notes on any record of the panel.

  **`@webx-ui/module-admin` and `webx-ui/module-admin` — notes.** A record somebody can write a
  note on takes `HasNotes`, declares `Notable` and names the permission its notes are behind; the
  table, the endpoint and the `WxNotes` feed are the panel's own, so the next section that wants
  one — an order, a client — adds a trait rather than a copy. Two things the shared endpoint
  cannot be allowed to get wrong are closed in it: the type in the address is an alias of the
  morph map and never a class name, and the permission is the record's answer, never the
  controller's guess.

  **`webx-ui/module-inbox` — the panel's side of a submission.** One form's list, with its own
  `in_table` fields as columns and the counts of every tab travelling beside the rows; spam out of
  "all" and reachable by its own tab; a pile moved, marked or thrown away row by row, so the log is
  written and the attachments go with it. Beside it, one submission opened at an address of its
  own: the answers with the words they were asked in, the files, what the intake saw around it, the
  status and the assignee, the notes, the log, a reply by `mailto:`, and the arrows to the next one
  in the same filtered pile. Submissions can also be typed in by hand, through the same intake as
  the public door — a call that came by telephone lands in the same list, and carries none of the
  administrator's own browser and address as if a visitor had them.

- 4644d28: `webx-ui/module-inbox`: forms and submissions, the package half.

  A form and its fields as rows, statuses seeded in ten languages, a public intake standing
  deliberately outside CSRF, the four antispam layers, attachments on the module's own private
  disk served only by the panel, and a notification per recipient in that recipient's own
  language. The submission is written before anything is sent, so a mail server that is down
  costs a notification and not an enquiry.

  The panel's screens, the site's form component and the agent's tools come in later sessions.

### Patch Changes

- 4644d28: `webx-ui/module-blocks`: the preview answers in the language the site answers in.

  Its route ran through `web` alone, and the language of a page is not decided there — it is
  decided by a middleware the site puts on the route that answers for a page. So the preview came
  out in the application's default: an editor writing a Russian page was shown it in English, with
  every localized thing in it — the words of a block, the labels of a form standing on it — in the
  wrong language, while the published page was right. The default is now `['web', 'webx.locale']`,
  which is what the setting already said it was for.

  A site that published `config/webx-blocks.php` keeps its own copy of that list and has to add
  `'webx.locale'` to `preview.middleware` itself.

## 0.21.0

### Minor Changes

- 0ff296d: The search box in the pages section looks in every language the site has.

  A list shows the title a page carries, not the one the reader asked for: a page named in English
  alone is drawn with that English name in a Russian panel. A search that looked at the current
  language only could not find what was on the screen, and said so with an empty list — which reads
  as a broken box rather than as an answer.

  `webx-ui/localization` gained `whereTranslationLikeAny()` for it, which walks the site's languages.
  The record's own keys cannot be walked instead: asking the database about them means reading the
  column as text, and in the stored JSON a translation is escaped (`Д…`), so a term in anything
  but ASCII would never match. `pages_tree` follows the panel — its `locale` argument says which
  language to answer in, not which one to look in.

## 0.20.1

### Patch Changes

- 2c4ee49: The bin of the pages section answers the search box.

  `GET /api/cms/pages` read the term for the tree and dropped it for the bin, so an editor looking
  for one deleted page among a hundred got the whole bin back and no sign that the box had been
  ignored. The term now narrows both lists — and `pages_tree` with `trashed`, which had the same
  gap, because an agent asking the bin for a name should not be handed everything in it either.

  The section says the right thing when a search comes back with nothing: it used to answer “the
  bin is empty” for any empty bin view, which was true until the box started working.

## 0.20.0

### Minor Changes

- ad9ead7: A pass over the panel: the chrome, the editors and the constructor.

  **The chrome.** The button that collapses the sidebar stands at the far end of
  the brand row instead of against the logo. The foot of an open sidebar says who
  is signed in rather than only showing them. The menu drawer keeps its width on a
  phone instead of covering the page — `WxDrawer` has `full-screen` for that — and
  icon buttons there are one size down.

  **Settings the panel wears.** A picture chosen in the library no longer vanishes
  from its field when the form is saved, and the logo in the corner changes with
  it: `AdminContext` gained `refreshManifest()`, which fetches a new manifest
  without the panel passing through `loading`.

  **`WxActionBar`.** The strip of body colour the bar painted in the gap below it
  is gone: it erased the part of the bar's own shadow that fell there, and a
  descendant is always on top of its ancestor's shadow. What shows through the gap
  instead is a sliver of the page still moving, which is what a bar floating over
  a scrolling page looks like.

  **Editors.** `WxBackButton` and `WxRenameButton` in `@webx-ui/module-admin`: a
  way out of a screen that opens one record, and renaming as an act rather than as
  typing into what looks like a heading. Both editors use them.

  **Tabs that hold a form.** Only the tab holding the constructor is a box of a fixed height
  with its own scrollbar; the others grow with their content and the page scrolls. A scroll box
  clips, and the cards inside one had their shadows cut off square at all four edges.

  **The constructor.** The preview is a picture of the page rather than the page:
  nothing in it navigates or submits, a click opens the block it landed in, the
  block under the pointer is outlined, and choosing one in the tree scrolls the
  frame to it.

  **Help.** `WxHelpButton` shows a page of Markdown from a module's own `lang`
  files — and `module-blocks` hands the identical page to an agent at
  `blocks://schema`.

  The page explains fields in more than one language, and writing it turned up that they
  did not work: a block with a `localized` field handed its template the whole language
  map, Blade refused to print an array, and the renderer caught that and printed nothing —
  the block vanished from the page. It is given one language now, down the same chain every
  localized value is read through.

  **The library.** A file can be downloaded from its card: `WxFileCard` takes
  `download-url`.

  **Smaller things.** A dialog puts air between whatever its body was given, so three stacked
  fields are not one block of controls. `WxSkeleton` is `border-box`, so a loader given padding
  no longer stands wider than the card it is in. And there is a guide to
  [languages](https://webx-ui.github.io/webx-ui/guide/languages).

  **Yourself.** `PUT auth/me` and the profile dialog behind the corner menu: your
  name, your photograph, your password — the last of those only with the current
  one.

- d80a19e: A picture's captions reach a template in one language.

  `alt` and `title` are written per language — the field gives each of them a language
  switcher — and they were handed to a template as the whole map, so `{{ $picture['alt'] }}`
  was Blade being given an array and refusing. All four fields that hold a file are read
  through the same code, so all four had it: `wx-media`, `wx-file`, `wx-gallery`, `wx-files`.

  They are picked apart now, down the chain every localized value is read through: the
  language asked for, the site's default, its fallback. A caption that was never a map — one
  written before the site had a second language, or sent by an agent — is left as it is.

  Only the read side changed. The panel edits the whole map, and it never went this way.

## 0.19.0

### Minor Changes

- 14d79c1: A block can be switched off

  A block on a page gets a switch: it stays in the content, it is edited in the panel exactly as
  before, and the site does not draw it. Not a page draft, not a delete, and not the block type's
  `is_enabled` — a switch on one block.

  The eye stands first in the row of the tree, before duplicate and remove. A switched-off block
  is dimmed and carries an `eye-off` beside its name, always and not only under the cursor: the
  actions are invisible at rest, and a row that is merely dimmer than its neighbours says nothing
  on its own. It is absent from the preview too — a preview that still showed it would not tell
  you which block is the one that is off.

  In the content it is a fourth key on the node, `hidden: true`, written only when it is on: every
  tree from before the switch existed reads as visible, with no migration. The skip is one line in
  `Renderer::list()`, which is why a switched-off container takes everything inside it with it
  while their own switches keep their values — the recursion never reaches them — and why the
  type's styles and script stay off the page.

  `blocks_edit_content` gets `hide` and `show` by key, and `outline` reports `hidden: true`: an
  agent has no other way of telling that a block it can read is not on the site.

- 2e27380: Lists that mean what they show: a tree stays a tree, a row promises only what it does, and
  anything that cannot be undone asks first.

  **The page tree is a table at every width.** Below 640px it used to become cards, and a card has
  no indentation to read and no chevron to open — so the section quietly asked the server for a flat
  list instead, and a phone had no tree at all. The cards were the mistake, not the tree. The table
  now drops columns as the width goes: when it was last touched, then what state it is in, then
  where it lives, until a row is its title and its `···`. Measured at 375px: 65px a row against
  250px a card, ten pages on screen instead of three and a half, with the chevron still opening
  branches.

  **`WxTable` takes a `clickable` prop.** It still infers the answer from whether anybody listens
  for `row-click`, which is right for an ordinary list and needs nothing said. It is not right for a
  list whose rows stop leading anywhere while it is on screen — the bin of `Pages`, an archive, a
  picker taking several rows at once — because the listener a component was rendered with cannot be
  read again. `:clickable="false"` withdraws the whole promise: no pointer, no highlight, and no
  `row-click` either. The bin, the two SEO lists for a reader who may not edit them, and the
  administrator picker in multiple mode all say so now.

  **One place decides what a failed request says.** `useErrorText()` turns an error into a sentence
  in the panel's language. A 422 is repeated word for word — every refusal that reaches one is
  written by a module to be read — and every other status gets the panel's own words, so clicking a
  page somebody else deleted says "It is not there any more" rather than
  `No query results for model [WebxUi\Pages\Models\Page] 8`. Twenty-odd places that printed the
  server's `message` now go through it, and `webx-admin::errors` ships the lines in ten languages.

  **Confirmations, in numbers.** Restoring from the bin, publishing a page, moving a branch by drag
  or by the "Inside" picker, deleting a block that holds others and deleting an empty media folder
  all ask now, and the question carries the consequence as a figure: how many pages come back, which
  address the page starts answering at, how many addresses a move rewrites, how many blocks go with
  the one being removed. A move of a single page stays a gesture and asks nothing, because a redirect
  is left on every address a move vacates — there is no undo to offer, only a second move.

- e93ae5b: The panel's frame: the bar goes into the sidebar, and the page scrolls itself.

  On a desktop and a tablet there is no bar across the top of the panel any more. The sidebar is
  the whole of the chrome and has three zones — the brand and the collapse button, the menu with its
  own scrollbar, the account at the bottom with its menu opening upwards — and the 56px the bar took
  out of the window's height go to the screen. A phone has no such column, so there the bar comes
  back with the burger, the brand and the account, and the menu is a drawer; choosing a section
  there now closes the drawer, which it did not before.

  The frame floats: the sidebar and the phone's bar are cards inset from the edges of the window,
  with the body colour running all the way round them. The inset is the panel's spacing step —
  8 on a phone, 12 on a tablet, 16 on a desktop — and the column is 220px wide, 56px as a rail,
  which leaves a screen exactly the width it had under the old frame at 1280 and at 1440.

  What scrolls is the page, natively: the shell no longer caps itself at one viewport, and the
  sidebar stands still beside a document that moves. A screen that has to be exactly as tall as the
  window still says `data-wx-fill`, but the height it gets is now measured from the window rather
  than from a scrolling column.

  `WxAside` grew the `top` and `bottom` slots — with either of them filled, `scroll` moves to the
  middle zone — plus `sticky`, for a column that stands beside a scrolling page, and `floating`, for
  one drawn as a card. `WxHeader` takes `floating` too. Both are additions: every existing shape
  behaves exactly as it did, and `viewport` shells are untouched.

  `webx-ui/module-admin` adds `nav.expand` in all ten languages, for the button on the rail.

- 046c6ba: The panel wears the client's logo

  The corner used to hold `WEBX_ADMIN_TITLE`, a name from a deploy file, which made every
  installation look like the same borrowed tool. Settings gets a **Branding** tab with two
  pictures, and the frame wears them: `branding.logo` in the corner of the open sidebar at 28 px
  tall, `branding.mark` on the 56 px rail, above the button that opens the sidebar again.

  Two pictures rather than one and a cropping rule — a wordmark cut to a square is its first two
  letters, and only the client knows what their mark is. A mark left empty leaves the rail
  exactly as it was.

  The name does not leave. `general.project-name`, the localized field that has sat on the
  `General` tab since the section was written without anybody reading it, now becomes
  `manifest.title`: the text in the corner when there is no logo, the logo's `alt` when there
  is, and the deployed title again when it is cleared.

  On the server this is one binding — `WebxUi\Admin\Contracts\BrandingSource`, answered by
  `module-settings`. `module-admin` neither knows nor requires the section that holds a logo, and
  a panel with no source bound is the panel as it always was. The picture fields are `wx-media`,
  so `module-media` is what turns them into addresses; without it the values stay library paths
  the frame cannot read and the corner keeps its name, the same tolerance `module-seo` has for
  its `og:image`.

### Patch Changes

- 738a7e9: A note under a field is smaller than its label, and a group of fields has a card

  `WxFormItem` sets the help text and the error to `--wx-font-size-xs` — 12 against the label's 14.
  They used to share a size and differ only in weight and colour, so a two-line note read as a
  paragraph of its own and the eye lost the seam between one field and the next. The error moves
  with the help text rather than staying at 14: it takes the help text's place, and a line that
  jumps a size on the first failed save is worse than either size.

  The SEO tab of a page gets the card it never had. It arrives as a patch from `module-seo`, so
  the fix is in the patched node: the `wx-seo` field now travels inside a `wx-card`, and the tab
  stops being the one place in the panel where fields lie straight on the page background. The
  card holds SEO's own three sub-tabs — one card, tabs inside it, nothing nested.

  The snippet preview inside that card also gets its frame back. It asked for
  `--wx-color-border`, `--wx-color-surface-sunken`, `--wx-color-text-muted` and `--wx-color-text`,
  none of which are tokens; a name that does not exist resolves to nothing without complaint, so
  the box had no border, no background and no colour of its own.

- 923a5ae: The file library moves into the `System` group

  `MediaModule` had no `group()` at all, so `Files` hung at the top level of the menu next to
  `Pages` — as if a file store were one of the things a site is made of, rather than a tool the
  sections share. It now returns `'system'` and leads that group with `order() = 500`, ahead of
  `Blocks` (600), `SEO` (700), `Settings` (800) and `Administrators` (900): of everything in
  `System`, the library is the one an editor opens while writing.

  The cost is a click: the library is now behind a group that starts collapsed. If it turns out
  to be opened more often than the settings around it, the order to reconsider is this one — but
  on watched use, not on argument.

- 738a7e9: One shape for every list in the panel

  Five sections each answered "where does the heading go" on their own, and there were five
  answers: a heading inside the card on `Administrators`, two headings on `SEO`, a search outside
  the card on `Blocks`, no heading at all on `Files`, a bare red bin in every row here and a menu
  there. They are one shape now — the section's name on its own line, the one action it exists
  for beside it, the views of the list as tabs under that, and a card holding nothing but the
  rows. The search stays inside the table: it narrows the rows, not the screen.

  `WxListScreen` in `module-admin` is that frame, and it is a screen node type — `wx-list` — so
  the next section describes its list rather than writing a sixth copy of the same markup.

  `WxTabs` grew an `items` mode for it: the strip is built from a list and the default slot is the
  **one** panel under it. A view is a different question to the server, not a different panel, so
  nothing is unmounted on a switch and the table keeps its search, its page and its scroll.
  `collapseBelow` folds the strip into a single switch labelled with the open view when its own
  container gets narrow — not into three dots, which in this panel mean actions.

  `WxRowMenu` is the other half: a `···` at the end of every row in every list, even for a single
  action. It orders the destructive one last, behind a rule, in red, and what somebody has no
  right to is left out rather than greyed. Underneath it is `WxActions` with the new
  `collapse="always"`, which never builds the row of icons at all — so the width of a table cell
  stops deciding whether a row has a menu. `WxFileCard` takes the same choice as `actionsMenu`,
  which is how a single file in the library gets one.

  Uploading is the media library's main action now: a filled blue button with a word on it in the
  line of the heading, rather than the third grey icon in a row of six. Blue, because green in
  this system means "it worked". Inside the picker dialog the toolbar keeps its upload icon —
  there is no screen around it there.

- 30a3d30: One way for the panel to say when something happened

  `module-admin` gains `useDates()` and `WxDate`, and every section that showed a date now calls
  them: "today at 08:10", "yesterday at 14:03", "16 September at 14:03", "16 September 2025",
  "never". Twenty-four hours and no seconds in the column; the exact moment stays in a tip and in
  `<time datetime>`.

  There were three formats on three screens before this, all of them `toLocaleString()` with no
  locale — so a panel drawn in Russian dated its rows in American order, with `AM/PM` and seconds
  nobody wanted. The month names and the order of the parts now come from `Intl` in the panel's
  own language; only `today`, `yesterday` and `never` are translated by hand, and the word for
  "never" moved out of the two modules that each had their own copy.

  "Today" is counted in the reader's calendar day rather than in UTC, which is what the server
  sends: the small hours of a morning are today to the person reading them. Sorting is untouched —
  a column still sorts on the value the server sent, never on the words.

## 0.18.0

### Minor Changes

- 22130c0: An agent changes part of a page without sending the page. `blocks_edit_content` takes operations
  by key — `set`, `add`, `move`, `remove`, the same vocabulary screen patches use — so changing one
  heading no longer means reading the whole tree and writing it back, which cost the page twice and
  quietly dropped whatever an editor had done in between. `set` merges field by field, and `locale`
  writes one language of a localized field rather than replacing the map with a string; a localized
  field refuses a bare value, and a plain field refuses a `locale`. Reading is cheaper too:
  `blocks_get_content` answers with the map of the page under `outline`, with one node under `key`,
  and always with a `revision` — a short hash of the content, which both writing tools accept and
  refuse to write over when the entity has changed since. The tree is edited by `ContentEdit`, pure
  functions the panel can use as well. `media_*` now report a file's `path` beside its `url`: a media
  field stores the key, so an agent that could only see the address had nothing to write into the
  field it belongs in.
- 2c2c2ba: The page editor: `pages.form` as a described screen, and the screen that saves it.

  `webx-ui/module-pages` registers `pages.form` — four tabs, Content · Settings · SEO · History —
  so the SEO card can arrive as a patch rather than as a fork of the editor. `GET /api/cms/pages/{id}`
  now answers with the values of that screen, the trail above the page, a signed preview link and a
  `revision`; `PUT` takes the values back through `ScreenValues`, so what the tree does not name is
  dropped and a 422 lands under the field it is about. The `revision` is a short hash of the
  content, the way `module-blocks` computes one: a save that names a revision that is no longer the
  current one is refused with a 409 carrying the page as it now is and who wrote it, and two people
  who saved the same thing are not a conflict. `GET .../versions` lists the publications with their
  author and source, and `POST .../versions/{n}/restore` makes an old one the draft — publishing it
  stays the separate step it always was.

  `@webx-ui/module-pages` draws it. The head stays put: the trail through the tree, the page's state,
  the preview link and the two buttons, folded into a menu on a narrow panel. Saving is by autosave —
  a pause after the last keystroke and the moment a field is left — with an explicit button beside a
  chip that says saved · saving · not saved yet, and a guard that flushes the pause on the way out
  and only asks when the save did not go through. The parts of the screen that are not fields are
  node types of their own: `wx-page-place` prints the whole address the page answers at and moves it
  in the tree, `wx-page-danger` takes it off the site or into the bin, `wx-page-history` is the
  history.

  `@webx-ui/module-blocks`: `wx-blocks` takes a `fill` prop — be as tall as what it is drawn in and
  let the tree, the form and the preview scroll each in itself. Without it the constructor sizes
  itself to its content, and a page made of twenty blocks scrolls the editor's head off the top of
  the screen along with it.

  `@webx-ui/core`: a screen that fills its column says so with `data-wx-fill`, and `WxMain` stops
  growing with it. A percentage height inside a scrolling column resolves against nothing while the
  column is as tall as its own content, and a screen cannot reach its own ancestors any other way.

- 2c2c2ba: A page says something about itself. `webx-ui/module-seo` grows the half it had deliberately left
  out: `seo_meta`, one translated row per entity; the `HasSeo` trait, which is the whole of what a
  content module has to write (`$page->seoValue()`, `$page->saveSeo()`, `$page->seoData($locale)`);
  and `EntitySource` at priority 50, between the rules an editor wrote for an address and the
  defaults the site falls back on — a rule was written because a page was wrong, so it wins; the
  defaults are what is said when nothing was said, so they lose. `wx-seo` is now a field type on the
  server as well, so the card can be dropped onto any entity's screen by a patch, and
  `webx-ui/module-pages` gets it that way: the SEO tab of the page editor is filled in by the SEO
  module rather than described by the pages one. What the card holds is saved the moment it is
  saved, on a published page and on an unpublished one alike — it never goes into the draft, because
  a description that only reaches search engines at the next publication is the kind of thing an
  editor finds out about from a search engine. An emptied card deletes its row instead of keeping
  one full of blanks, which is what lets the site's defaults back through.
- 2c2c2ba: Agents get the pages of a site. `webx-ui/module-pages` offers nine tools — `pages_tree`,
  `pages_get`, `pages_create`, `pages_update`, `pages_move`, `pages_publish`, `pages_unpublish`,
  `pages_delete`, `pages_restore` — through the same doors the panel uses: `PageForm` decides what
  a page's values are and checks them against the described screen, so a field another module put
  on `pages.form` is writable here by having done nothing, and the `revision` that answers a
  panel's 409 refuses an agent's stale write with the same sentence. Where a page may go is now
  `Placement`, one class the move endpoint and the move tool both ask, rather than two copies of
  the three rules that keep the home page where it is.

  A page is named by its id or by its address — `"/catalog/shoes"`, and `"/"` for the home page —
  because that is what a site is talked about in; text fields answer with every language at once,
  since an agent that got one title has no way of knowing whether the others exist. Content does
  not travel through any of this: a `blocks` key sent to `pages_update` is refused with the name of
  the tool that does it (`blocks_edit_content`, §13.1), rather than accepted and dropped. The new
  resource `pages://sitemap` is the map to read first — the tree nested the way it is nested, each
  page with its address per language and its status — and the prompt `build_page` puts the loop in
  front of an agent that was asked for a page: read the map and the block catalogue, create, fill
  with blocks, look at the preview, write the SEO card, and leave it a draft for a person.

  `webx-ui/mcp` raises the page size of `tools/list` from fifteen to a hundred. Six modules now
  offer more than forty tools between them, and a client that does not follow the cursor was seeing
  a third of them and concluding the rest did not exist.

- 2c2c2ba: `webx-ui/module-pages`: the tree of pages, the home page as its root, and the addresses.

  A new Composer package. Pages are a nested set with translatable `title` and `slug`, content
  made of blocks, a draft and a history — almost all of it from packages that already existed.
  What the package adds is the rules that are about pages: the home page is the root, is always
  there, and cannot be moved, deleted or given an address of its own; there is only ever one of
  it; and deleting a page trashes its whole branch, one node at a time, so that every address in
  it is released and a restore brings back exactly what went down together.

  `webx-ui/nested-set` learns soft deletes, by opt-in: a model that says `softDeletesInTree()`
  keeps its trashed nodes standing in the tree instead of being refused the delete, and takes
  charge of what happens to their descendants. Without it, the delete is still refused — a node
  that vanishes while its bounds are reclaimed leaves its children outside their parent.

  `webx-ui/routing` learns that an entity may have no address in a language at all:
  `HasUrl::hasUrlIn()` answers yes for everything unless a model says otherwise, and a language it
  says no to gets no row in the registry and loses the one it had. Syncing also stopped writing
  the slug it read back into the entity, which quietly filled in languages the editor had left
  empty; `webx:routes:check` no longer reports those languages as missing addresses.

- 2c2c2ba: The pages section: the tree of a site's pages, and the panel API behind it.

  `@webx-ui/module-pages` is a new npm package — the front end of the section. The list is a table
  tree read a level at a time: the home page is pinned at the top and its children are the top
  level, because everything on the site is inside it and a branch drawn for that would give every
  row a step of indentation that says nothing. Children arrive when a branch is opened, searching
  puts the tree away and answers with a flat list of matches and their addresses, and the bin is a
  filter rather than a section of its own. A page is moved by dragging it or through “Move…” and a
  tree of pages — the one that works on a touch screen and in a catalogue where the page and its
  new parent are four screens apart — and either way the section says out loud how many addresses
  the move rewrote, because an editor should not hear about a thousand redirects from a search
  engine. Row actions: open, add a page inside, duplicate, move, copy the address, open on the
  site, delete; in the bin, restore.

  `webx-ui/module-pages` gains the section and the endpoints under `/api/cms/pages`: the level of
  the tree with `can` and `children_count` on every row, create, save the draft, move, duplicate,
  publish, unpublish, delete into the bin with the branch, and restore. A page's title comes from
  its draft and its address from the registry, so a page renamed and not yet published shows its
  new name beside the address the site is still serving. Its refusals — the home page cannot be
  moved or deleted, a page cannot be dropped into its own branch, nothing stands beside the home
  page — answer as a 422 under the field they are about, the same way a taken address does.

## 0.17.0

### Minor Changes

- 4d1d996: `module-media` stores and resolves the three new field types, `module-blocks` tells an agent
  about them.

  `GalleryFieldType`, `FileFieldType` and `FilesFieldType` join `wx-media` in
  `WebxUi\Media\Screens`, sharing one set of rules, one `store` and one `resolve`. A resolved value
  now carries the whole of what the library knows — `url`, `thumb`, `name`, `extension`, `mime`,
  `size`, `width`, `height` — because a Blade template has nothing else to ask with, and a whole
  list is looked up in one query rather than one per value. `props.accept` is checked on the way in
  against the row fetched for the resolve; a key whose file has been deleted is kept and resolves
  to `url: null`.

## 0.16.2

### Patch Changes

- dd41bd8: A block's template is handed what the field type makes of a value, not the row as stored — the
  same way a screen's values are read for the site. A `wx-media` field keeps `{ path, alt, title }`
  and the template now also gets `url`, worked out when the block is printed, so the picture no
  longer has to be assembled from the disk's configuration inside the Blade. A value whose type
  nobody registered — `wx-blocks` above all — and a value whose key the schema does not name pass
  through as they are; a field inside `wx-repeater` or inside layout is resolved too, and the
  preview, the panel's own drawing and the check before publishing all see the same values as the
  page. The media field remembers the files it looked up for the length of one response, so a
  gallery costs one query per picture rather than one per mention.

## 0.16.1

### Patch Changes

- f7bdc63: `module-blocks`: the section moves into the System group of the navigation, first in it, above
  SEO — a block type is made once and then lives on the pages, so the section is opened the way
  the settings are.

## 0.16.0

### Minor Changes

- 7cecf88: The block constructor: the agent's doors, and the files

  `webx-ui/mcp` now serves what the modules declare. `WebxServer` is a `laravel/mcp` 1.0 server
  that reads the tool registry when it starts, so a panel exposes exactly the tools of the modules
  it has — over Streamable HTTP at `{api_path}/mcp`, closed by `webx.mcp-auth` until a Sanctum
  token opens it, and over stdio as `mcp:start webx`. `php artisan webx:mcp:token` issues a token
  to an administrator with the scopes as its abilities; a scope is checked once, before any handler
  runs. A handler now also receives the administrator the call acts as, and refuses with a thrown
  `ToolFailure` that the agent reads verbatim.

  `webx-ui/module-blocks` speaks it: `blocks_list`, `blocks_get`, `blocks_create`, `blocks_update`,
  `blocks_publish`, `blocks_render`, `blocks_get_content`, `blocks_set_content` and
  `blocks_preview_url`, through the same doors the panel uses and with `mcp` as the source in the
  history; the resources `blocks://guidelines`, `blocks://catalog`, `blocks://fields` and
  `blocks://site`; the prompt `design_block`. And the files: `webx:blocks:export` writes each type
  to `resources/blocks/{slug}.json`, `webx:blocks:import` reads them back — a version only where the
  content differs, `--publish` to publish what passes the checks, `--dry-run` to be told.

- 7cecf88: The block constructor: the panel

  `@webx-ui/module-blocks` is new: the section where a block type is made — a list of cards with
  live thumbnails, and an editor with the template, styles, script, fields and settings on one side
  and the block drawn on its sample, the sample's form built from the schema being edited and where
  the type stands on the other. Checks run live under the editor; publishing is a separate step,
  refused with the line when the template fails on the sample or on a page. `wx-blocks` is the field
  that builds an entity out of blocks: a tree with drag to reorder, a picker of types with pictures
  that offers only what may go here, the selected block's fields as a form, and the entity's own
  preview beside them — the whole page while looking at it, a phone at one to one while editing a
  block, swapped in place after a field changes. `provideBlocksPreview()` is how the hosting screen
  hands the preview address in.

  In `webx-ui/module-blocks`, the panel half: the module (`blocks.view`, `blocks.manage`, the groups
  and `webx.provide()` names in the manifest), the API under `/blocks` — types, catalogue, save
  (a version per save, none for an unchanged one), publish with the check on every page's values,
  render on sent values or on an unsaved template, usage, history and restore — the lints the
  server sends back with a saved version, `webx-blocks.editing` as a read-only switch, and the
  dictionary in ten languages.

- 7cecf88: The block constructor: the package and the renderer

  `webx-ui/module-blocks` is the section of the panel where a block type is made entirely — its
  fields, its Blade template, its styles and its script — and the mechanism that prints an entity's
  content from such blocks. This is its first half, the rendering: three tables (`blocks`,
  `block_versions`, `block_bundles`), the `Block` and `BlockVersion` models with `saveVersion()` and
  `publish()`, the cached registry `BlockTypes`, Blade compiled from the database into one file per
  version, the `@blocks` directive for nesting, the `HasBlocks` trait with the `$table->blocks()`
  macro, and `Blocks::render()`.

  Every block renders inside its own try/catch, so one broken template leaves a gap and a report
  rather than taking the page with it; in preview mode the gap is a notice with the template's line,
  and every block is wrapped in a pair of comments the panel finds it by. Publishing a version renders
  it on its sample values first and refuses, with the line, when that throws. The schema's fields are
  the template's variables — a field added after the content was written is `null` on the old pages,
  not an error.

  The second half of the same package, the styles and scripts: the set of types a page rendered, at
  their versions, makes a hash that names a row of `block_bundles` with the glued CSS and JS, served
  by `/blocks/{hash}.css` and `.js` with a year-long immutable cache. `@webxBlocks` in the layout
  prints the tags — evaluated where it stands, after the content under `@extends` and components —
  with `('styles')`, `('scripts')` and `('runtime')` variants and an inline mode for small sets. A
  block's script is an initialiser per instance behind a small runtime (`webx.block`, `webx.mount`,
  `webx.provide`, `webx.use`) that also ships on its own at `/blocks/runtime.js`. Commands:
  `webx:blocks:bundles --prune|--warm` and `webx:blocks:clear`.

  The preview: `/_preview/{type}/{id}?token=…` shows the draft of an entity as the page it will
  be, through the same handler and view that answer the real address, with a `Resolution` of the
  entity's own, the drafts of the block types, the marker comments, and `no-store` plus `noindex`
  on the response. The token is one signed parameter that opens one entity for an hour;
  `Preview::url($entity)` makes it, `PreviewGrant::of($request)` is how a handler tells a preview
  from a visit. The preview prefix is closed to the address registry.

  What the preview stands on, in `webx-ui/module-admin`: drafts and versions for any entity.
  `HasDraft` keeps what is being prepared in a `draft` column next to what the site shows, with
  `saveDraft()`, `withDraft()`, `publish()`, `unpublish()` and `isPublished()` by `published_at`;
  `HasVersions` writes a numbered snapshot into `entity_versions` on every publication, keeps a
  ring of autosaves beside the history, trims to `webx-admin.versions.limit` with pinned versions
  excepted, and restores an old version into the draft. `$table->draft()` adds the columns,
  `webx:versions:prune` applies a lowered limit. And in `webx-ui/nested-set`: a detached node —
  `saveDetached()` saves a row with no place in the tree, for a "new page" that exists before
  anybody has decided where it goes; it joins the tree with the first placement.

  The panel section and the MCP tools follow.

### Patch Changes

- f78e485: A compiled block template is named by its content as well as its version. The slug and the
  version number alone are not unique across databases: a test suite on an in-memory database
  and the developer's own site compile into one directory, each with a `hero` at version 1 and
  a different template — and whichever compiled first served both, so a page printed the other
  site's block, or nothing. The file name now carries a hash of the template; a new version is
  still a new file, and nothing is ever invalidated.

## 0.15.0

### Minor Changes

- f98786f: The registry of a site's public addresses

  `webx-ui/routing` is one flat namespace — `/about`, `/blog`, `/alternator-belt-7100104` — one row
  per address, and one resolver that hands a request to whoever owns the path it matched. Uniqueness
  holds across every kind of content at once, which is the part no single module can do on its own: a
  module cannot see another module's addresses, and those are exactly the ones it collides with. A
  library rather than a section: the public side of a site uses it with no panel in sight.

  Add `HasUrl` to a model and register its type, and saving, renaming, moving in the tree, deleting
  and restoring keep the registry in step from then on. The address itself is built by the type's
  formatter — `Slug`, `TreePath`, `SlugId`, `SlugSku`, `Prefixed`, or one of your own — and a
  formatter is a pure function of the entity, so a save, a preview in a form and
  `webx:routes:rebuild` cannot disagree about what the address is. A project overrides somebody
  else's formatter from config and moves the addresses that already exist with one command; nothing
  dies in the move, because every address that changes leaves an alias behind. Collisions are settled
  by the type: refused under the slug field, or suffixed with the suffix written back into the entity
  so the form shows what the site will serve.

  Reading is a fallback route, which is the whole trick — a fallback is tried only when nothing else
  matched, so a project's own `/search` wins with no ordering to arrange. One spelling per address
  (a trailing slash, a capital letter or a doubled slash is a 301, query kept); exact beats prefix, so
  `/about/mission` is its own page rather than a tail handed to `/about`; an alias answers 301 and
  takes the tail with it, so a renamed category keeps its pages of filters. Publication stays the
  entity's business: the registry has a row for everything that exists, and the handler decides
  whether to show it. Addresses the application answers itself are refused when an entity is saved,
  not when a request arrives — losing silently to a live route leaves an editor with a page that
  exists everywhere except on the site.

  `webx:routes:rebuild` recomputes the addresses of a type, `webx:routes:check` reports what no
  constraint can — rows with no entity, entities with no row, aliases leading nowhere, addresses a
  project has since claimed with a route of its own — and exits 1 so a deploy can stop on it.

  `UrlNormaliser` has moved here from `webx-ui/module-seo`: one spelling of an address for both
  halves of the system, with `key()` added beside `normalise()` for the registry's own. `module-seo`
  now depends on `webx-ui/routing`, reads the aliases through the narrow `RouteAliases` contract,
  says in `POST /seo/test-url` what the registry holds at an address, and prints the `<head>` of a
  page the resolver found without the template having to name it.

  The guide is `apps/docs/guide/routing.md`.

## 0.14.1

### Patch Changes

- aac21d2: `webx-ui/module-media` stops shipping its tests, and stops flaking

  Seven of the eight composer packages carry a `.gitattributes` that keeps `tests/` out of the
  published archive and normalises line endings; `module-media` was the one that did not, so its
  test suite has been riding along to Packagist. It has one now, the same one.

  The tests it keeps to itself are one bug lighter. A fake upload is a blank canvas of the size
  asked for and nothing else — the name is never drawn into it — so two of them with the same
  dimensions are the same bytes, and the store deduplicates by content inside a directory. One
  helper drew a random width to keep its two files apart, which worked about seven hundred and
  ninety-nine times out of eight hundred; the other trusted a unique name, which never mattered at
  all and held only because no test yet puts two such files in one folder. Both now give each file
  a width of its own, the way the third helper already did.

## 0.14.0

### Minor Changes

- c92f42a: SEO: rules for addresses, redirects, and the `<head>` a page prints

  `webx-ui/module-seo` is the panel section for SEO that belongs to no entity, and the renderer that
  turns it into markup. Sources are asked in order and merged **field by field** — a rule that fills
  in nothing but a title keeps the description and the picture that came from below it — so the
  entity source that arrives with the first content module is an addition, not a change.

  One matcher serves rules and redirects alike: exact, then mask (`*` inside a segment, `**` across
  them), then a regular expression, by priority inside each group, against the path with its query
  string. A pattern that will not compile is refused when it is saved and never matches if it got in
  anyway. The active ones are one compiled list in the cache, dropped whenever any of them changes.

  Redirects run as global middleware rather than in the `web` group, because the addresses worth
  redirecting are the ones the site has no route for and those never reach a group at all — with the
  panel's own paths stepped over, so a mask cannot lock an editor out of the screen they wrote it on.
  `/robots.txt` answers from a setting; the SEO tab of the settings screen now comes from the module
  instead of from each project's own patch. `POST /seo/test-url` says what an address ends up saying
  and where every part of it came from.

- c92f42a: SEO in the panel: rules for addresses, redirects, and `wx-seo`

  `@webx-ui/module-seo` is the front half of the section, and the card that edits what a page says
  about itself. Two screens rather than two tabs — rules and redirects each have their own paging
  and their own search, and a tab that resets both on the way back is worse than a second address.
  Rules are listed in the order the site tries them, so reading the table top to bottom is reading
  what will happen.

  `WxSeo` is registered as the `wx-seo` field type on both halves: its value is everything a page
  says about itself as one object, so an entity's form gets the whole card from a patch the day it
  has somewhere to keep it. The text fields are language maps and grow the same chip every localized
  field in the panel does; the picture is not one, deliberately. The length counters are soft —
  search engines shorten what they shorten, and nothing here refuses a longer line.

  The share image comes in as `seo({ mediaField: WxMediaField })` rather than as an import, so the
  package does not depend on the library being installed.

  **Check an address** answers the question this section gets asked most — which redirect catches
  it, which rule matched, what each source contributed, what the page ends up with — in one call.

  The settings tab has moved out of every project's own patch and into the module, which makes it
  the first screen patch laid by a module rather than by a project. The guide is
  `apps/docs/guide/seo.md`.

- 0304791: Repeater: a field whose value is a list of records

  `WxRepeater` is `WxSortableList` once every row is a form — a set of fields, repeated, in an order
  that is part of the answer. Rows fold to a name taken from their own fields, and each keeps a key
  of its own, so writing a field, removing the row above or dragging one elsewhere never rebuilds the
  form under the caret. `WxSortableList` gained `itemLabel` for the same reason: a row has to be
  called something out loud.

  In a described screen it is `wx-repeater`, the one type whose model is nested: the node's children
  are the fields of one item, and a `name` inside it is a key of that item. A type of its own can do
  the same with `nested: true`, which hands the component the node and the render context.

  On the server `RepeaterType` checks, stores and resolves items with the types their children
  declare — per language where a child is localized — and a failed row says which row it was.
  `FieldType::resolve` now takes the requested locale as a third argument, and `Tree::fields` stops
  at a named node: a repeater's children belong to its value, not to the screen.

## 0.13.0

### Minor Changes

- 47d52ee: Screens in the panel, both halves, and the first section built on them.

  - `module-admin` (PHP): `Screens::register` / `Screens::extend`, the `FieldTypes` registry with the core types, `ScreenValues` (save by description — a key the tree does not name is dropped, a node without permission is closed for writing, rules per type and per language), `GET /api/cms/screens/{name}` (patched, permission-filtered, translated). The manifest carries `screens`, `groups` and each module's `group`; `Module::group()` is new on the contract (`AbstractModule` answers null). `webx-admin.groups` declares the `system` group.
  - `@webx-ui/module-admin`: `createAdmin({ screens, types })`, `WxScreen`, `admin.loadScreen()` cached per language, `admin.types`, navigation groups drawn as branches — "System" holds settings and administrators.
  - `module-media`, both halves: registers `wx-media` (the field on the client, the stored key with the resolved address on the server).
  - `module-settings`, both halves, new: the `settings.index` screen with one "General" tab, `cms_settings`, `GET`/`PUT /api/cms/settings`, `settings()` on the site, cache and `SettingsSaved`, MCP `settings_list` / `settings_get` / `settings_set`. The SEO tab is a project patch, not part of the module.
  - `core`: a `gear` icon; the language chip on a localized field unrolls every language in the site order — the current one included and marked — instead of reshuffling, and switching puts the caret into the field that was switched.
  - `module-auth`: the administrator implements `HasPermissions`, `cms.auth` makes the guard the request's default, and the section sits in the "System" group.

## 0.12.1

### Patch Changes

- b554497: Фотография в углу шапки. Сессия (`/auth/me`, ответ на вход) теперь несёт `avatar` — ключ, под
  которым лежит фотография, тот же, что в ресурсе администратора. `auth()` принимает
  `resolveAvatar` — ту же функцию, что и `admins()`, — и `WxUserMenu` показывает картинку вместо
  инициалов; без неё всё как раньше. Аватар в шапке стал крупнее (`lg`).

  Мелочи вокруг: чекбокс «Stay signed in» придвинут к полю пароля, иконка показа пароля больше не
  уменьшается до шрифта кнопки и совпадает по размеру с замком, разделители в меню пользователя
  получили настоящий `spacing="sm"` вместо несуществующего значения, из-за которого они брали
  отступ по умолчанию.

## 0.12.0

### Minor Changes

- e5e129d: Каркас панели называется `module-admin`: `@webx-ui/module-admin` на npm и `webx-ui/module-admin` на
  Packagist вместо `@webx-ui/admin` и `webx-ui/admin`. Правило теперь одно на обе половины: всё, из
  чего состоит панель, — каркас и разделы — с префиксом `module-`, библиотеки, которые живут и без
  панели (`nested-set`, `localization`, `mcp`), — без него.

  Код не изменился: namespace `WebxUi\Admin`, конфиг `webx-admin`, экспорт `createAdmin` — те же.
  В composer `webx-ui/module-admin` объявляет `replace: webx-ui/admin`, так что сайт, который ещё
  требует старое имя, получит новый пакет; в npm старый пакет помечен deprecated. В сайте меняется
  импорт: `from '@webx-ui/module-admin'` и `'@webx-ui/module-admin/style.css'`.

## 0.11.0

### Minor Changes

- 6f7b501: Модуль администраторов называется `admins`, а не `users`: раздел живёт по адресу `/admins`, права
  стали `admins.view`, `admins.manage`, `admins.audit`, области MCP — `admins:read`, `admins:write`,
  `admins:audit`, инструменты — `admins_list_admins`, `admins_grant_role` и остальные с тем же
  префиксом. `users` оставлено пользователям сайта: когда сайту понадобятся регистрация и вход, они
  станут отдельным модулем, и два раздела с одним именем столкнулись бы в правах и в навигации.

  Миграция переписывает права в уже сохранённых ролях, так что роль, которой вчера выдали
  `users.manage`, сегодня по-прежнему разрешает управлять администраторами.

## 0.10.0

### Minor Changes

- 3e64cd7: Администраторы: список, форма и выбор из кода.

  `webx-ui/module-auth` получил CRUD поверх того, что уже было, — поиск, фильтры по роли и
  состоянию, пагинация, роли только на чтение. Два действия сервер отклоняет сам, а не прячет
  кнопку: выключить или удалить самого себя и снять последнего суперадминистратора. Оба способа
  оставить панель, в которую некому войти. У администратора появилось фото — ключ библиотеки, как
  и везде.

  `@webx-ui/module-auth`: раздел `admins()`, диалог создания и редактирования, `selectAdmin()` и
  `selectAdmins()` — тот же список в диалоге, возвращает выбранных. Для выбора достаточно права
  `users.view`: назначить кому-то задачу и редактировать его учётную запись — разные вещи.

  Модуль не зависит от медиа: поле для фотографии и способ превратить ключ в адрес передаёт панель
  — единственное место, которое знает, что установлено и то и другое.

  Попутно: `WxAvatar` берёт `name` и сам считает инициалы, а проп `label` у него отсутствует — меню
  пользователя показывало всем заглушку вместо букв. И сама аватарка в меню больше не завёрнута в
  экшен: это не ещё один инструмент в ряду иконок, а кто вошёл.

- 3e64cd7: Таблицы на телефоне и восемь правок по форме администратора.

  `@webx-ui/core`: ниже `cardsBelow` (480 по умолчанию, ширина самой таблицы, а не окна) строка
  рисуется карточкой из пар «подпись — значение». Ячейки те же самые — те же слоты `cell-<key>`,
  те же форматтеры, — так что экран, написанный под таблицу, переживает телефон без единой правки.
  Колонка с `hideOnCards` в карточку не попадает, а действия строки уезжают в слот `card-actions`:
  в карточке нет колонки, где им быть. Пагинация показывается только когда страниц больше одной —
  одну страницу листать некуда, а строку экрана она занимает. Проп `flush` снимает с таблицы
  её собственные поля вокруг шапки и карточек — для таблицы внутри карточки, где поля уже держит
  карточка, и второй их комплект ставил поле поиска на шаг правее кнопки над ним.

  `webx-ui/localization` кладёт под приложение переводы сообщений валидатора на десять языков.
  Laravel везёт их только по-английски, и панель, переведённая на десять языков, отвечала на
  незаполненную форму по-английски. `addPath` добавляет, а не заменяет: строка, опубликованная в
  `lang/` приложения, по-прежнему главнее.

  `@webx-ui/module-media`: у поля картинки появились пропорции — `aspect` с пресетами `16/9`,
  `4/3`, `1/1` и любым `width / height`.

  `@webx-ui/module-auth`: форма в две колонки — фото сбоку, поля справа, а на узком экране одной
  колонкой; кнопка и заголовок диалога короткие («Добавить», «Редактировать»); фильтры над списком
  администраторов убраны (их полдюжины, фильтр над шестью строками — это контрол, который надо
  прочитать вместо того, чтобы посмотреть).

## 0.9.0

### Minor Changes

- 0d288c1: Форма научилась говорить на нескольких языках, а картинка — жить на форме.

  `@webx-ui/core`: `localized` на `WxInput` и `WxTextarea`. Модель становится записью по языкам,
  инпуты называются `title[uk]`, `title[ru]` — так, как это читает обычный POST, — а язык, который
  редактируется, общий на весь экран: страница наполовину на одном языке и наполовину на другом это
  и есть непереведённая запись. Языки берутся не из пропсов каждого поля, а из панели
  (`provideLocales`), и это языки контента сайта, а не язык самой панели. `WxLocales` — то же самое
  вокруг секции, вкладками. Плюс `localizedValue()` для чтения такого значения в таблице.

  Пикер теперь умеет и загружать. Выбор только из того, что уже лежит в библиотеке, отправляет
  человека уходить со страницы, загружать файл и искать форму заново. И `accept` стал не
  подсказкой, а правилом: когда вызывающий сказал «картинка», фильтр типов из панели убирается и
  вернуть «все» нельзя.

  `@webx-ui/module-media`: `WxMediaField` переписан под два состояния — пустая рамка, открывающая
  библиотеку, и картинка с двумя действиями: подписи (`alt` и `title`, мультиязычные) в поповере и
  очистка поля с подтверждением, которая не удаляет файл. Запись хранит только ключ, поэтому поле
  само находит файл по нему: `webx-ui/module-media` отвечает на `GET files/by-path`.

  `@webx-ui/core` заодно: внутри `WxSelectionArea` больше не терялся двойной клик. Указатель
  захватывался на нажатии, и совместимостные mouse-события уходили вместе с ним — файл в менеджере
  настоящей мышью не открывался, хотя синтетический `dblclick` в тестах проходил.

## 0.8.0

### Minor Changes

- 9e1b513: Three things found by putting the library on S3 behind a CDN.

  The image editor could not open a picture at all. It draws onto a canvas and writes that canvas
  out, which a browser refuses for bytes fetched from another origin without CORS headers — and a
  private bucket cannot be given those headers for the panel in any case. So `webx-ui/module-media`
  serves the picture itself at `files/{id}/source`, behind the same permission as the listing, and a
  `MediaFile` now says where that is. `url` stays what everything that only looks at a file uses.

  The editor also spoke English in a Russian panel: it is a component of the design system, so its
  words are props, and the manager was not passing any. It has its own ten-language group now, as
  does the question the card asks before deleting one file.

  `@webx-ui/admin`: changing the language renames the sections too. Titles are translated on the
  server and travel in the manifest, which was fetched in the previous language — so the panel used
  to switch everything except its own navigation until the page was reloaded.

## 0.7.0

### Minor Changes

- 760d372: `webx-ui/module-media` gains its folder endpoints: the tree in one answer, create, rename, move,
  and a delete that refuses a folder with anything in it until it is asked again with `force`. The
  refusal carries the counts, because the panel has a real question to put to the person: pictures
  already placed in articles will stop opening.
- a7fd824: `webx-ui/module-media` gains image editing and its MCP tools. An edit is written over the same
  key, so every address already in an article keeps working, and the picture as it arrived is kept
  once so any edit can be undone. The tools cover the library the way an agent would use it —
  except deleting a folder, which is deliberately absent.
- de2b3a4: `webx-ui/module-media` gains its file endpoints and its previews: a paginated, searchable,
  filterable listing, multi-file upload, rename, batch move, batch delete, and a thumbnail
  endpoint that cuts a variant once and then redirects to it so the disk — or the CDN in front of
  it — serves the grid instead of PHP.
- 47cc998: The file manager after an hour with it: icon actions instead of labelled buttons, filters behind
  popovers, folders in a drawer on a phone, case-insensitive search in any alphabet, previews that
  change when a picture is edited, and a copy-the-link that says whether it worked.

  `@webx-ui/admin` gains the toaster the panel never had — until now every `toast()` from every
  module reported into silence.

- 5ccdccc: New package `webx-ui/module-media`: the panel's file manager. This is its first step — the
  section registers itself, carries its configuration and its ten languages, and declares the
  permissions the rest of the module will be built against. Folders, files and the endpoints
  around them follow.
- 377e894: `webx-ui/module-media` gains its schema and its storage: folders as a nested set, files that
  belong to one, and the service that puts bytes on a disk and takes them off it. Keys are built
  from a uuid and say nothing about the folder, so the same picture in two folders is two keys and
  moving a file between folders never touches the bytes.
- 47cc998: A second pass over the file manager, from using it: upload refusals written in extensions rather
  than a paragraph of mime types, a status bar under the grid instead of a toolbar that grows a
  line, folders created through a dialog rather than a `prompt` the browser may refuse, the page in
  a card, and the image editor cropping what the person actually framed.

  `@webx-ui/core`: `WxFileCard` falls back to the old clipboard when the modern one refuses, so the
  green tick appears wherever the copy actually worked.

### Patch Changes

- 47cc998: New package `@webx-ui/module-media`: the file manager as a section of the panel, a picker that
  opens from code, and a form field that keeps `{ path, alt, title }` on the entity rather than on
  the file. Batch deletion moved to `POST files/delete` on the server, because the panel's own HTTP
  client sends no body on `DELETE`.
- 47cc998: Three things the file manager got wrong in Russian: the dialogs' buttons stood shoulder to
  shoulder, the confirm button read `manager.save` because nobody had shipped the key, and an
  upload refusal came back in English — the uploader is a bare `XMLHttpRequest` and was the one
  request in the panel that never said which language it was drawn in.

## 0.6.0

### Minor Changes

- a9240d6: Seven more languages in the panel: German, Polish, French, Spanish, Italian, Portuguese and
  Turkish, alongside the English, Russian and Ukrainian that were already there. A site still
  decides which of them to offer in `config('webx-localization.panel')`.

  A test in each package holds the ten key sets together — a missing line falls back to English
  rather than to a key, which is right and also the reason a gap can sit unnoticed.

## 0.5.0

### Minor Changes

- 78d8ef3: The panel answers in the language of whoever is reading it. `webx-ui/admin` serves the language
  list and the interface dictionary — both public, because the sign-in screen is drawn before
  there is a session to ask — and carries `locale`, `locales` and `panelLocales` in the manifest.
  `webx-ui/module-auth` stores each administrator's choice on the administrator, so it follows
  them to the next machine and so validation messages arrive in the same language as the labels
  above them.

  Both packages ship English, Russian and Ukrainian. A site adds a language they never shipped by
  publishing their `lang` files and translating what is missing; the merge is per line, so an
  untranslated key falls back on its own rather than taking its screen with it.

- 78d8ef3: `webx-ui/localization` — the languages a site is published in, translated Eloquent attributes,
  and the dictionary the admin panel is drawn from.

  It keeps two things apart that are easy to run together. Interface phrases are written by
  whoever wrote the module, change at deploy, and live in the package's `lang` files; content is
  written by whoever runs the site, changes all day, and lives in the database. One store for
  each, and neither knows about the other.

  A model names its translatable columns and goes on being a model — the value is a JSON language
  map, readable by anything that understands `spatie/laravel-translatable`. The panel's own words
  come from the same `lang` files the server reads, so a module is translated once rather than
  once per half.

### Patch Changes

- 9c0a762: The panel's sidebar toggle has a translated label: `webx-admin::nav.collapse`, in English,
  Russian and Ukrainian. Without it that one control fell back to the English the npm package
  carries, which is a small thing that looks exactly like a broken translation.

## 0.4.0

### Minor Changes

- fd98bdd: `webx:panel` wires the panel's front end into the application that hosts it: it writes the
  entry file, adds it to the Vite inputs, points `webx-admin.vite` at it, and names the npm
  packages to install. Where it cannot recognise a Vite configuration it says which line to add
  rather than rewriting a build it does not understand.
- 7fa1539: `webx-ui/admin` can load the panel's own assets. `webx-admin.assets` names the built files, or
  `webx-admin.vite` names entry points for an application that builds the panel with Laravel's
  own Vite. With neither, the shell stays deliberately blank — the frame installed and the panel
  not is a real state, and it should look like one.

### Patch Changes

- 48c6e34: The entry `webx:panel` writes now imports the stylesheets. The packages ship compiled CSS that
  nothing imports on its own, so the panel built from the previous stub ran perfectly and looked
  like an unstyled form.

## 0.3.1

### Patch Changes

- b193bdf: `webx:admin` accepts the password in `WEBX_ADMIN_PASSWORD` when there is nobody to ask, so a
  provisioning script or a container entrypoint can create the first administrator. Still no
  `--password` option: an argument lands in the shell history and in the process list.

## 0.3.0

### Minor Changes

- 18d9a7e: `webx-ui/module-auth`: administrators, roles and sign-in. Installing it is what closes the
  panel — until now `webx-ui/admin` served its API to anyone. Administrators live in their own
  table behind their own guard, roles grant the permissions modules declare in the manifest, and
  every sign-in attempt is written down. Its MCP tools can read who has what and move people
  between roles, and deliberately cannot touch a password or mint a token.

## 0.2.0

### Minor Changes

- cc9d156: Two packages the admin panel is built from. `webx-ui/admin` carries the module contract, the
  registry, the manifest the front end reads before it draws anything, and the catch-all that
  keeps a deep link from 404ing. `webx-ui/mcp` carries the contract by which a module offers
  itself to an AI agent — a mutating tool is given `dry_run` and a write scope whether its author
  remembered them or not.

## 0.1.0

### Minor Changes

- 219fd59: First release of the Composer packages. `webx-ui/nested-set` brings nested set trees to
  Eloquent: subtree reads in one query, placement and moves that keep the bounds consistent,
  `toTree` for handing a whole tree to the front end, and `fixTree` / `checkTreeIntegrity` for
  when something has gone wrong anyway.
