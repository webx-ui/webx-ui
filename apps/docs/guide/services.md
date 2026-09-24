# Services

`@webx-ui/module-services` is a catalogue of services as two sections of the panel, and
`webx-ui/module-services` on the server is what they edit. This page is both, because neither is
useful alone.

A service is built like a [page](/guide/pages): the address is the registry of
[`webx-ui/routing`](/guide/routing), the body is [blocks](/guide/blocks), the draft and the history
are `module-admin`, what a service says about itself is [`module-seo`](/guide/seo), the covers are
[`module-media`](/guide/media). The categories are the panel's shared
[categories](/guide/categories), the same code the blog's rubrics run on.

What this module adds is the catalogue around a service: it can be in **several categories**, each
category is **a page of the site**, and there are **two orders** — the whole list, and each
category's own.

## Install

```bash
pnpm add @webx-ui/module-services
composer require webx-ui/module-services
php artisan migrate
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { services } from '@webx-ui/module-services'
import '@webx-ui/module-services/style.css'

createAdmin({
  modules: [...services()],
})
```

`services()` returns two modules — **Services** and **Categories** — which arrive under one
heading because the server puts both in the `services` group.

Permissions: `services.view` opens the list, `services.manage` writes (the order too), and
`services.categories.manage` covers the categories.

## Addresses

A service and a category live **on one level**, under one prefix:

| Type               | Address                                  |
| ------------------ | ---------------------------------------- |
| `service`          | `/services/implants-turnkey`             |
| `service-category` | `/services/implants`                     |
| the index          | `/services`, route `webx.services.index` |

The category is not part of a service's address: a service in three categories has one address,
and "which of the three" has no answer. The price is that their slugs share the level — a category
and a service that want the same slug are refused, the second one under its own field and with the
name of whoever took it.

`webx-services.prefix` is the prefix. Empty puts both types at the root beside the pages, and the
index route is not registered: `/` is the site's, and a list of services there is a page the site
writes. Changing the prefix later:

```bash
php artisan webx:routes:rebuild --type=service --type=service-category
```

`webx-services.index` off (`WEBX_SERVICES_INDEX=false`) keeps the prefix but drops the index
route, so the address is free for a page with the slug `services`, built of blocks like any other.
Categories and services stay under the prefix. The first step of their breadcrumbs is then
whatever the registry holds at the prefix, named the way it names itself — and no step while
nothing, or only a draft, is there.

The old addresses stay behind as aliases that answer with a 301.

## Order in categories

Every service has a place in the whole list (`position` on the service) and a place inside each of
its categories (`item_position` on the link). They are independent: a service can be first in one
category and last in another.

**The editor drags the list they are looking at.** Without a category filter, the drag writes the
whole list's order. With a category chosen, the list shows that category in its own order, and the
drag writes only that category. While a search or a state narrows the list, there are no grips:
the gaps between the rows shown are rows that are not, and a drop into one is not an order anybody
chose.

**A service newly filed into a category takes its place by the whole list.** So a category nobody
has rearranged lists its services exactly as the list does, and the first drag inside it is what
makes it different.

**The first category of a service is its main one.** It is the one in the breadcrumbs and in
`BreadcrumbList`. In the editor the categories are chips that can be dragged; putting another one
first changes the breadcrumbs.

The public side reads the same two orders:

- the **index** lists every visible category in its order, each with its services in _that
  category's_ order, and then the services no visible category lists, by the whole list;
- a **category page** lists its services in its own order.

```php
use WebxUi\Services\Models\Service;

Service::query()->visible()->orderedIn($category->id)->get(); // one category's order
Service::query()->visible()->orderedIn()->get();               // the whole list
```

An agent has the same two with `services_reorder`: with `category`, only that category moves.

## Fields of the project

A clinic wants «Price from» on every service, a studio wants «Duration». Neither is a column of the
package: the site lays a patch over the screen, and whatever the screen draws that the model has no
column for is kept in `extra`.

Both screens — `services.form` for a service, `services.category-form` for a category — keep an
empty card with the public id `project-fields` for this. The renderer does not draw the card until a
patch puts something in it.

`resources/screens/services.form.json` in the site:

```json
[
  {
    "op": "add",
    "target": "project-fields",
    "node": {
      "id": "price-from",
      "type": "wx-input-number",
      "name": "price-from",
      "label": "Price from"
    }
  }
]
```

and in a service provider of the site:

```php
use WebxUi\Admin\Facades\Screens;

public function boot(): void
{
    Screens::extend('services.form', resource_path('screens/services.form.json'));
}
```

That is the whole of it. The field appears on the **Settings** tab, is checked by its type on
every save, and is kept in `services.extra`:

- **through the draft** for a service — a price is published with the service, not before it;
  a save of another tab does not empty it;
- **at once** for a category, which has no draft.

In the view:

```blade
@if ($price = $service->extra('price-from'))
  <p class="price">from {{ $price }} €</p>
@endif
```

`extra()` reads the value the way its field type says: a field with `localized: true` in the
language of the page, a picture (`wx-media`) as an address rather than a library key. An agent
writes the same field with `services_update` under the same name, and is refused by the same
check.

## The panel

**Services** is the whole catalogue on one screen, without pages — a site has dozens of services,
not thousands, and a drag cannot cross a page boundary. The row is the cover, the title with the
address under it, the categories as chips (the first one is the main one), the state and the
row menu. Filters: a category, a state, words. The row is a card rather than a line of cells, so
the same list works on a phone: the categories and the state move under the name.

Four states: **draft** (never published), **published**, **published with edits**, and
**unpublished** (taken off the site; only the history tells it from a draft). There is no
«scheduled»: a service has no date to go out on.

**The editor** is the screen `services.form`, four tabs. **Content** is the blocks, autosaved into
the draft, with a preview by token. **Settings** is the title, the address with the prefix in
front, the categories, the lead with a counter, the cover, and the project's card. **SEO** is the
card from `module-seo`. **History** is the publications, any of which comes back as the draft.
The action bar: the state, «Discard changes», «Save draft», «Publish». A save over somebody else's
is refused with a `409` carrying the service as it now is.

The categories take effect when saved; everything else waits in the draft. A category is a row in
a link table, and half a row does not exist.

**Categories** is the shared category list and page (`services.category-form`): **Content** —
the name, the address, whether it is on the site, the introduction, the project's card; **Image**;
**SEO**; and **Blocks** when `webx-services.categories.blocks` is on — off by default, because a
site that rewrote the category view may not print them. A category with services in it refuses to
go into the bin and says how many.

```
GET    /api/cms/services                        q, category, status, trashed — no pages
POST   /api/cms/services                        { title, slug? }
GET    /api/cms/services/{id}                   values, the revision, the prefix, a preview link
PUT    /api/cms/services/{id}                   the draft: { values, revision }
POST   /api/cms/services/{id}/discard           · /publish · /unpublish
DELETE /api/cms/services/{id}                   to the bin · POST /restore
GET    /api/cms/services/{id}/versions          · POST /versions/{n}/restore
POST   /api/cms/services/reorder                { ids, category? }

GET    /api/cms/services/categories             the shared categories API
```

## The public half

Three pages, each with the package's own view underneath until the site writes its:

| Config key                     | Default             | Handed                                            |
| ------------------------------ | ------------------- | ------------------------------------------------- |
| `webx-services.views.index`    | `services.index`    | `$categories` (with `services`), `$uncategorised` |
| `webx-services.views.category` | `services.category` | `$category`, `$services`                          |
| `webx-services.views.service`  | `services.service`  | `$service`, `$category` (the main one)            |

`webx-services.layout` names the site's layout component, the same seam as the
[blog's](/guide/blog#the-layout).

A service is on the site when it is published and not in the bin; a category when it is visible and
not in the bin. A service in a hidden category still answers at its own address — it is not the
category's property. The sitemap, `hreflang` and the canonical come from `module-seo` by
themselves; the breadcrumbs are index → main category → service. A service describes itself as a
schema.org `Service` whose `provider` points at the site's `Organization` by `@id`; the index and a
category page push an `ItemList`.

## For an agent: MCP

With the panel's MCP server on (see [AI agents](/guide/agents)), both sections are tools too:

| Tool                      | What it does                                                                |
| ------------------------- | --------------------------------------------------------------------------- |
| `services_list`           | The catalogue, or a category in its order, a state, words — or the bin      |
| `services_get`            | One service in full: the values, the revision, a preview link               |
| `services_create`         | A new service as a draft, at the end of the list                            |
| `services_update`         | The values into the draft, guarded by the revision                          |
| `services_publish`        | The draft onto the site · `services_unpublish` takes it off                 |
| `services_delete`         | To the bin, and its address is released                                     |
| `services_reorder`        | The whole list's order, or one category's with `category`                   |
| `service_categories_list` | The categories in their order, with how many services each holds            |
| `service_categories_*`    | `create`, `update`, `delete`, `reorder` — behind the categories' permission |

A service is named by its id or its address (`"/services/implants-turnkey"`), a category by its id
or slug. Every tool that changes something takes `dry_run: true`. The body is not written here —
blocks are `blocks_edit_content`, and a `blocks` key sent to `services_update` is refused with that
sentence.

Before writing, an agent reads **`services://catalog`**: every category in its order, hidden ones
too, with its services in that category's order, their addresses and states, drafts included — and
the services filed nowhere at the end. It is there so that an agent asked for «implants» finds the
half-written one instead of starting a second.

The category tools are named after the module id, `service-categories`, which is why they read
`service_categories_*` rather than `services_categories_*`.

## Demo content

`php artisan webx:demo` seeds three categories and eight published services, each with a cover
from the library and two blocks. One service, «Site maintenance», is filed under two categories and
stands last in one and first in the other — the two orders, where they can be seen. `--remove`
takes all of it back out. A catalogue that already has anything in it is left alone.

## Config

`config/webx-services.php`:

| Key                 | Default    | What it is                                           |
| ------------------- | ---------- | ---------------------------------------------------- |
| `prefix`            | `services` | The first segment of every address; empty — the root |
| `index`             | `true`     | The package answers the prefix with its own index    |
| `categories.blocks` | `false`    | A Blocks tab on the category page                    |
| `breadcrumbs`       | `true`     | The package views print the visible trail            |
| `views.*`           |            | The views each page is printed with                  |
| `layout`            |            | The Blade component those views stand in             |
| `middleware`        |            | What the index route runs through                    |
