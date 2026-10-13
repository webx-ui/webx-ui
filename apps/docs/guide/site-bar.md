# The panel on the site

An administrator who is signed in to the panel and opens a page of the site sees a small tab at
the right edge of the window. It opens a drawer with the panel in it: what record this page is,
whether it is published, whether somebody has left unpublished changes in it, and the way to
open it in the panel. A visitor sees nothing — no script, no attribute, no header.

It is part of `webx-ui/module-admin` and comes with the panel; there is nothing to install.

## What it shows

- **The record behind the address** — «Page · Pricing», «Article · …», «Product · …»: every
  section that gives its records addresses tells the bar where the panel edits them.
- **Its state** — published, scheduled, not on the site, in the bin — and when it was last
  edited.
- **A draft somebody left** — «J. Doe has unpublished changes here», before you go and edit on
  top of it.
- **Open in panel** — the record's own screen, in a new tab: the page stays where it was, to check the edit on.

When the record has a draft and you may publish it, the head of the drawer counts the changes —
«Unpublished changes: 2» — with **Publish** and **Discard**. Publish puts the draft on the site
exactly as the record's own button in the panel does; Discard throws the draft away. The page
reloads after either. If somebody else has written into the draft since the record was last
published, the bar says so — before your first edit and after it, since **Publish** puts the
whole draft on the site, their part with yours: «J. Doe has a draft here; your edits go into it».

## Blocks

The **Blocks** tab edits the blocks of the page without leaving it — their fields and where they
stand. It is there on a
record made of blocks — a page — for somebody who may edit the record (`pages.manage` for a
page) and read blocks (`blocks.view`).

- **The map.** Without a block chosen the tab lists the record's blocks, nested ones indented.
  A row under the pointer frames its block on the page and scrolls to it.
- **Picking on the page.** The pencil (**Pick a block on the page**) turns picking on: a frame
  follows the pointer with the block's type, a nested block wins over the one around it, and a
  click chooses the block instead of following its link. <kbd>Esc</kbd> turns picking off; the
  next <kbd>Esc</kbd> closes the drawer. With picking off the site behaves as the site — links
  lead, buttons press. On a phone, picking folds the drawer away so the page can be seen, and a
  tap on a block opens it again on that block.
- **The form** is the constructor's: the same fields, with the values of the **draft**, not of
  the site — so an edit never writes over what somebody prepared in the panel. Above it, the way
  back up to the blocks it is inside.
- **Save to draft** writes the fields you changed into the record's draft and puts the block,
  drawn from the draft, in place of the old one on the page, with a dashed **Draft** frame: the
  page in front of you is now partly the site and partly the draft. Its script starts again the
  way the preview starts it. Visitors see nothing until somebody presses **Publish**.
- **Somebody else in the meantime.** If the record changed after the form read it — a colleague
  saved it in the panel, an agent edited it — the save is refused, the form shows what is there
  now and says who changed it. While a form is open the bar tells the panel it is there, so an
  editor who opens the same page in the panel sees that it is being edited.

A block of old content that was saved before blocks had keys says **Open in panel to edit** and
cannot be chosen: the first save in the constructor gives it a key.

### Adding, moving and removing

Every row of the map and the head of a block's form have a `···` menu:

- **Add below** — a new block right after this one, in the same list; **Add inside** on a
  container — at the end of what it holds. Under each map, **Add block** puts one at the end of the
  page, the header or the footer. The type is chosen in the constructor's own picker, which offers
  only what may stand there; the new block's form opens at once.
- **Duplicate**, **Move up**, **Move down**, **Hide** / **Show**, and **Delete**, which asks first.

What cannot be done right now stays in the menu, greyed, with the reason beside it — «Already
first», «No room for more blocks here. Most it takes: 2», «A page takes no more blocks of this
type» — rather than disappearing. While picking, the chosen block has a round **+** on its lower
edge: the same **Add below**, where the new block will go. On a phone fold the drawer with its
tab first — the drawer covers the page.

All of it goes into the draft of the page or of the region, exactly as an agent's
`blocks_edit_content` would, and the page follows at once: a new block appears drawn from the
draft, with its dashed **Draft** frame — with its styles and script even when no block of that type
was on the page before — a hidden or deleted one goes, a moved one moves. Moving a block into
another container, into a region or onto another page is the constructor's work.

After a reload the page is the site again and shows none of the draft. The draft's notice — in the
head for the page, over its map for a region — has **Preview draft**: the whole draft, in a new tab.

### The header and the footer

The regions of the layout — the header and the footer that `<x-webx-blocks::region>` prints (see
[Layout regions](./layout-regions.md)) — are in the same tab, each under its own name and «On every page»,
below the record's blocks. They are there for whoever edits the regions in the panel
(`blocks.regions`), on every page of the site, a page without a record too.

- **Picking** works for them as for the page: the frame says whose block it is — «Menu · Header».
- **Save to draft** and the structure write into the region's draft, not the page's, and the block comes back drawn
  as it stands on this page: its active menu item, the record the page shows. The attributes the
  layout's tag passes to the region are known only to the layout, so a block that reads
  `$region->data()` looks right again on the next load.
- **Publish** and **Discard** for a region are over its own map and over the form of its block, not
  in the head of the drawer:
  the head is about the record, and a region's draft goes on every page at once. A draft with a
  block that fails is not published — the lines say which block and why, as in the region's
  editor.
- A region the site does not show yet — it prints the markup from code — has no map here, only
  the way to its editor in the panel.

### How the page knows its blocks

For an administrator who gets the bar, the page is printed with a pair of comments around every
block — `<!--wx:{key}-->…<!--/wx:{key}-->` — and around the record's blocks
`<!--wx-entity:page:12-->`, around a region's `<!--wx-region:header-->`: the header and the footer
are on the page too, and the bar has to know whose block is under the pointer. The values are the site's, and a block that fails is a gap, as
for a visitor. A visitor's page has none of it.

## SEO

The **SEO** tab answers «why does this page have that title»: for the title, the description,
the H1 and «hide from search engines» it shows what the page prints and where that came from —
a rule written for the address, the record itself, the record's name, or the site's defaults —
with a search-result preview and length counters. The share picture, the canonical address and
structured data are behind **All fields in panel**.

Where a change is saved:

- **the record's own SEO**, when the page is a record you may edit (`pages.manage` for a page).
  Only the fields and the language you changed — the other languages stay as they were. SEO is
  saved at once, never into a draft;
- **a rule for this exact address** otherwise, with `seo.manage`. That is how a page of the
  site's own code — a route the project wrote, with no record behind it — gets SEO, and why such
  a page gets the bar only for somebody with `seo.manage`. If a mask covers the address, the new
  rule starts as a copy of it, so the page loses nothing the mask gave it.

A field that a rule sets above the record is flagged **before** you save, with a link to the
rule: a change in the record would not show on the page. The language is the page's own. After
saving, the browser's tab shows the new title at once; the rest of `<head>` changes on the next
load.

## Who gets it

The server decides on every page, never the script. The bar is put on a page when all of these
hold:

- the request is a `GET` of a page in the `web` group that answered `200` with HTML and has a
  `</body>`;
- it is not the panel, its JSON or a block preview, and not inside a frame;
- somebody is signed in to the panel (the `cms` guard) and their account is active;
- they may edit the record behind the address — the permission of its section, `pages.manage` for
  a page — or they have `seo.manage` or `blocks.regions`, which work on any page;
- they have not turned the bar off for themselves: **Panel on the site** in the user menu;
- the site has not turned it off for everybody:

```dotenv
WEBX_SITE_BAR=false
```

On a phone the drawer takes the whole width. The tab can be dragged up and down the edge, and
the browser remembers where, and which tab you chose: the next page opens on it (`localStorage`,
key `wx-bar` — only an administrator's browser ever gets it). <kbd>Esc</kbd> closes the drawer.

## Caching

A page with the bar is sent with `Cache-Control: private, no-store` and `Vary: Cookie`, so a proxy
does not hand an administrator's page to the next visitor. The other way round is harmless — a
cached visitor's page reaching an administrator only lacks the bar. If the site sits behind a
CDN that caches HTML, let requests that carry the session cookie pass straight through.

## For a section of your own

A module whose records have addresses registers each type it gives one, under the same type the
address registry knows it by:

```php
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\SiteBar\EntityLink;
use WebxUi\Admin\SiteBar\SiteBar;

// In the provider's boot():
$this->app->make(SiteBar::class)->entity('recipe', new EntityLink(
    edit: static fn (Model $recipe): string => '/recipes/'.$recipe->getKey(),
    permission: 'recipes.manage',
    kind: 'webx-admin::site-bar.kind-recipe',
));
```

`edit` is the panel's address of the record, relative to the panel. A record that keeps a draft
(`HasDraft`) adds `publish: DraftPublishing::class` (`WebxUi\Admin\SiteBar\DraftPublishing`) for
**Publish** and **Discard** in the bar's head; a section that does more on publishing names its
own class implementing `WebxUi\Admin\SiteBar\Publishing`. `kind` is a translation key
for what the record is, in the singular. The label is the record's `title` or `name` in the
language of the address; a `label` closure says otherwise. A record that uses `HasSeo` gets the
SEO tab with this registration and nothing more.

### A tab of your own

A tab has two halves with one name. The server says on which pages it is there:

```php
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\SiteBar\EntityLink;
use WebxUi\Admin\SiteBar\SiteBar;

$this->app->make(SiteBar::class)->section(
    'reviews',
    static fn (HasPermissions $user, ?Model $entity, ?EntityLink $link): bool => $user->hasPermission('reviews.manage'),
);
```

The panel module brings what it looks like; the component gets the page's `context` (address,
language, record, the sections the server offered) as its one prop. A tab that stands for more
than one section names the others in `sections` and is drawn once — «Blocks» is both `blocks` and
`regions`:

```ts
import { markRaw } from 'vue'
import type { AdminModule } from '@webx-ui/module-admin'
import ReviewsBarTab from './ReviewsBarTab.vue'

export const reviews = (): AdminModule => ({
  id: 'reviews',
  siteBarTabs: [
    {
      name: 'reviews',
      label: (admin) => admin.i18n.scope('reviews')('site-bar.tab'),
      component: markRaw(ReviewsBarTab),
    },
  ],
})
```

The bar is shown on a page when at least one tab says yes there. `tellSitePage({ type: 'title',
title })` from a tab puts a new title into the browser's tab of the page. `useSiteBar()` gives a
tab `show(name)` — bring a tab forward — and `refresh()` — read the page's context again after a
change, so the draft count in the head is right.
