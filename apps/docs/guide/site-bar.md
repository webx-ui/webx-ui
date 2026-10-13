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

Editing SEO and blocks in place comes in the next stages (`WEBX_UI_SITE_BAR.md`, §13). A page of
the site's own code — a route the project wrote, with no record behind it — gets no bar yet:
there is nothing on it to open.

## Who gets it

The server decides on every page, never the script. The bar is put on a page when all of these
hold:

- the request is a `GET` of a page in the `web` group that answered `200` with HTML and has a
  `</body>`;
- it is not the panel, its JSON or a block preview, and not inside a frame;
- somebody is signed in to the panel (the `cms` guard) and their account is active;
- they may edit the record behind the address — the permission of its section, `pages.manage`
  for a page;
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
language of the address; a `label` closure says otherwise.
