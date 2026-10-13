# Building for the panel on the site

The [panel on the site](./site-bar.md) is the drawer an administrator gets on the public site:
what this page is, a way to it in the panel, its SEO, its blocks. This page is for whoever writes
a section — a product, a category, an article — and needs that section to take part. The design
and its reasons are in `docs/architecture/WEBX_UI_SITE_BAR.md`.

**The rule:** a section that gives its records a public address registers them with the bar in
the same commit. A record the bar does not know is a page where the administrator sees «nothing
in the panel stands behind this address» — on a page that plainly has a record behind it. A test
in `module-admin` counts the registrations against the address types and fails the build on a
section that forgot (see [Check your work](#check-your-work)).

## What a section gets for free

Most of the bar is not the section's code. With one registration (below), a record gets:

| What                                                               | Comes from      | The record needs                                                    |
| ------------------------------------------------------------------ | --------------- | ------------------------------------------------------------------- |
| «Page» tab — kind, title, status, «Open in panel»                  | `module-admin`  | an `EntityLink`                                                     |
| «SEO» tab — what the head says and why, edits saved at once        | `module-seo`    | the `HasSeo` trait                                                  |
| «Blocks» tab — pick a block on the page, edit, add, remove         | `module-blocks` | `HasBlocks`, the model in `webx-blocks.entities`, `renderBlocks()`  |
| «Publish» / «Discard» in the head of the drawer                    | `module-admin`  | a draft (`HasDraft`) and `publish: DraftPublishing::class`          |
| Presence — the editor in the panel sees the record is being edited | `module-admin`  | the record's editor in the panel listening on `editing/{name}/{id}` |

Nothing has to be printed in a view for any of it. The bar decides on the server whether to show
itself, and the markers it finds blocks by come out of `renderBlocks()` on an administrator's
response only.

## Registering a record

In the section's service provider, once per type the section registers in `RouteTypes` — under
**the same type**, because an address is what the bar starts from and the type is what
`Resolution` knows:

```php
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\SiteBar\DraftPublishing;
use WebxUi\Admin\SiteBar\EntityLink;
use WebxUi\Admin\SiteBar\SiteBar;

private function registerSiteBar(): void
{
    $bar = $this->app->make(SiteBar::class);

    $bar->entity(Product::TYPE, new EntityLink(
        // Where the panel edits it, relative to the panel's path: the npm module's route.
        edit: static fn (Model $record): string => '/catalog/products/'.$record->getKey(),
        // The bar offers the record only to whoever may change it.
        permission: 'catalog.manage',
        // What the record is, in the singular — a key of the section's own dictionary.
        kind: 'webx-catalog::site-bar.kind-product',
        // Only for a record that keeps a draft; without it there is no «Publish» button.
        publish: DraftPublishing::class,
    ));
}
```

- **`edit`** must match the npm module's route for the record's editor. When the section lets
  the site move it (`path` option of the npm module), the server cannot know; keep the default
  and say so in the section's README.
- **`permission`** is the section's `*.manage` right. A right to look (`*.view`) is not enough:
  the bar is for changing things, and offering a record nobody may change is a dead end.
- **`kind`** is a translation key. A new section puts it in its own dictionary
  (`lang/*/site-bar.php`), in every language the panel ships. The sections that came with the bar
  keep theirs in `module-admin` (`webx-admin::site-bar.kind-*`) only because they were registered
  all at once; that is not the pattern to copy.
- **`publish`** — `DraftPublishing` for a model with drafts (`publish()`, `discardDraft()`).
  A record whose publication does more (a product that rebuilds a search index) implements
  `WebxUi\Admin\SiteBar\Publishing` itself and does it there, not in a listener the bar does not
  know about.
- **`label`** — only when the record is not named by `title`, `name` or `question`: a closure
  `(Model, ?string $locale)`. The status (published, scheduled, unpublished, trashed) is read off
  the model's `isVisible()` and friends — nothing to register.

A page of the site's own routes has no `Resolution` and therefore no record in the bar; it still
gets «SEO» (a rule for the address) and the regions. That is by design, not something to work
around with a registration.

## A tab of the section's own

When a record has a few things worth changing from the page itself — a product's price and stock,
a category's description, an article's publication date — the section adds its own tab. Two
halves, as everywhere in the panel: the server says **whether** the tab is there, the npm module
says **what it looks like**.

**Server** — a section with a name and a closure that answers for this administrator on this page:

```php
$bar->section('product', static fn (HasPermissions $user, ?Model $entity, ?EntityLink $link): bool =>
    $entity instanceof Product && $user->hasPermission('catalog.manage'));
```

The closure is asked on **every** page an administrator opens, before anything is drawn: no
queries in it. It is the same answer that decides whether the bar appears at all, so a tab that
says yes too easily puts the bar on pages where it has nothing to offer.

**npm** — the module lists the tab under the same name:

```ts
// packages/module-catalog/src/module.ts
siteBarTabs: [
  {
    name: 'product',
    label: (admin) => admin.i18n.scope('webx-catalog')('site-bar.tab-product'),
    component: markRaw(ProductSiteBarTab),
  },
],
```

The component gets the page's `context` (`SiteBarContext`: `url`, `locale`, `entity`,
`sections`) as a prop and is drawn inside the panel itself, in a frame — so it has the panel's
`http`, dictionary, rights and field types like any screen:

```vue
<script setup lang="ts">
import { tellSitePage, useAdmin, useSiteBar, type SiteBarContext } from '@webx-ui/module-admin'

const props = defineProps<{ context: SiteBarContext }>()
const admin = useAdmin()
const bar = useSiteBar()

async function save(): Promise<void> {
  await admin.http.put(`${admin.apiPath}/catalog/products/${props.context.entity!.id}/price`, form)
  // The head counts the record's draft: read it again after anything that changes it.
  await bar?.refresh()
  // What the page shows is the published record: a change that is live at once needs a reload.
  tellSitePage({ type: 'reload' })
}
</script>
```

How such a tab behaves — the same choices the built-in tabs made:

- **A few fields, not the form.** The drawer is 380 px; the record's full editor is one click
  away («Open in panel» opens it in a new tab). If the tab grows past one screen, it is the wrong
  tab.
- **Save the way the section's form saves.** Fields the form keeps in the draft go into the
  draft (and the head's «Publish» publishes them); fields the form saves at once — SEO, stock —
  are saved at once. Two behaviours for one field, depending on where it was edited, is a bug.
- **The record's own routes and rights.** No route of the bar's: the tab calls the section's API
  under the section's `*.manage`, with the revision it read, and shows a 409 the way the section's
  editor does.
- **Words are props or the section's dictionary**, in every language; numbers with `:count` at
  the end of the string.
- **Nothing on the page itself.** The tab talks to the page only through `tellSitePage()`
  (`reload`, `title`) and `onSitePage()`. A section never prints anything for the bar into its
  Blade views — not a `data-` attribute, not an `@auth` block: whatever a view prints, a visitor
  gets too, and the bar's promise is that a visitor gets nothing.

## Things that are easy to break

- **A visitor's page must not change by a single byte.** Every section test that touches the
  public side keeps passing with the bar installed; a section that adds a tab adds a test that a
  guest's response is the same with and without it.
- **Do not check the administrator in a view.** `auth('cms')` in Blade puts an administrator's
  markup into a response a proxy may cache for everybody. The bar's response is `no-store`; a
  view's is not.
- **Blocks are found by markers from `renderBlocks()`.** A view that prints blocks some other way
  (`Blocks::render($tree)` directly) gets no markers, and its blocks cannot be picked on the page.
- **The record's type is the address type**, not the model class: a model with two kinds of
  address (a category of services and a service) registers two `EntityLink`s.

## Check your work

- `module-admin/tests/SiteBarCoverageTest.php` — every `new RouteType(` in a section's provider
  has its `new EntityLink(` beside it. It fails with the provider's name.
- Copy the shape of `module-pages/tests/SiteBarTest.php` for the section: the record is named in
  `context`, the right is checked, a guest's response is unchanged, the tab is offered only where
  its closure says.
- Look at it on a real site linked to the monorepo: log in to `/cms`, open a record's public
  address, open the drawer. jsdom does not lay anything out — see `docs/pitfalls/layout-and-browser.md`.
