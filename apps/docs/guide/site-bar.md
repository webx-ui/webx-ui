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
- **Open in panel** — the record's own screen, in the whole window.

Editing blocks in place comes in the next stages (`WEBX_UI_SITE_BAR.md`, §13).

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
- they may edit the record behind the address — the permission of its section, `pages.manage`
  for a page — or they have `seo.manage`, which works on any page;
- they have not turned the bar off for themselves: **Panel on the site** in the user menu;
- the site has not turned it off for everybody:

```dotenv
WEBX_SITE_BAR=false
```

On a phone the drawer takes the whole width. The tab can be dragged up and down the edge, and
the browser remembers where (`localStorage`, key `wx-bar` — only an administrator's browser ever
gets it). <kbd>Esc</kbd> closes the drawer.

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

`edit` is the panel's address of the record, relative to the panel. `kind` is a translation key
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
language, record) as its one prop:

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
title })` from a tab puts a new title into the browser's tab of the page.
