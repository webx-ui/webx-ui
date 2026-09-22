# webx-ui/module-admin

The frame a [WebX UI](https://github.com/webx-ui/webx-ui) admin panel is built on.

It answers three questions and stays out of everything else: what modules this panel has, where
the front end can ask, and what to serve when someone opens a deep link. Entities, screens and
permissions belong to the modules.

## Requirements

- PHP 8.3+
- Laravel 13

## Install

```bash
composer require webx-ui/module-admin
php artisan webx:install
```

`webx:install` publishes the config and the shell view, then tells you where the panel and its
manifest are. The panel is **open until an auth module adds its middleware** — the command says
so too, because it is the kind of thing that is easy to leave for later.

## A module

A module describes itself. Its own service provider does the real work — routes, bindings,
migrations — exactly as any Laravel package would; this contract is only what the front end
needs in order to know the module exists.

```bash
php artisan webx:make-module Pages
```

```php
namespace App\Cms\Modules;

use WebxUi\Admin\AbstractModule;

class PagesModule extends AbstractModule
{
    public function id(): string
    {
        return 'pages';
    }

    public function icon(): ?string
    {
        return 'file-text';
    }

    public function order(): int
    {
        return 10;
    }

    public function permissions(): array
    {
        return ['pages.view', 'pages.manage'];
    }

    public function manifest(): array
    {
        return ['tree' => true];
    }
}
```

Register it from a service provider, so it is there on every request:

```php
public function boot(ModuleRegistry $modules): void
{
    $modules->register(new PagesModule);
}
```

`AbstractModule` fills in everything but `id()`. Two modules may not share an id — they would
collide in URLs, in permissions and in the manifest, and the failure would surface far from its
cause, so registration refuses it outright.

## The manifest

```
GET /api/cms/manifest
```

```json
{
  "data": {
    "title": "WebX UI",
    "branding": {
      "logo": { "url": "/storage/media/logo.svg", "width": 240, "height": 48 },
      "mark": null
    },
    "path": "/cms",
    "apiPath": "/api/cms",
    "modules": [
      {
        "id": "pages",
        "title": "Pages",
        "icon": "file-text",
        "order": 10,
        "permissions": ["pages.view", "pages.manage"],
        "meta": { "tree": true }
      }
    ]
  }
}
```

Whatever a module returns from `manifest()` lands under `meta`, in its own room, so it can never
shadow the fields around it.

`title` and `branding` are whose panel this is. They come from whoever is bound to
`WebxUi\Admin\Contracts\BrandingSource` — `module-settings` binds itself to it, and a site
without that section gets `WEBX_ADMIN_TITLE` and two nulls. There is one brand, so it is a
binding and not a registry: a second source would only raise the question of which logo wins.

## Responses

Laravel already has the two shapes that matter, and the WebX UI front end is built against them:
a paginator serialises with `data` and `meta`, and a failed validation is a 422 with `message`
and `errors`. So a table endpoint returns `->paginate()` as it is, and a form endpoint lets
validation fail on its own.

`ApiResponse` covers the third case — a plain payload, wrapped in `data` so it arrives like
everything else:

```php
use WebxUi\Admin\Http\ApiResponse;

return ApiResponse::data($page);
return ApiResponse::message('Published.');
return ApiResponse::noContent();
```

## The panel's front end

The panel is built by the site rather than shipped prebuilt: which modules it contains is a
decision only the site can make, so there is no one bundle to ship.

```bash
php artisan webx:panel
```

writes `resources/js/admin.ts`, adds it to the `laravel()` plugin's inputs, points
`webx-admin.vite` at it, and puts the npm halves of the installed packages into `package.json`.
Then:

```bash
npm install
npm run build      # or npm run dev while working — @vite serves from the dev server
```

Which npm package goes with which Composer one is not something to remember: each package says
so itself, in its own manifest.

```json
"extra": {
    "webx": {
        "module": "pages",
        "npm": { "@webx-ui/module-pages": "^0.3.10" },
        "panel": {
            "import": "import { pages } from '@webx-ui/module-pages'",
            "style": "@webx-ui/module-pages/style.css",
            "register": "pages()"
        }
    }
}
```

Install a module six months later and

```bash
php artisan webx:panel --sync
```

adds its four lines to the entry file and its dependency to `package.json`, leaving everything
else where it is. Run it twice and the second run changes nothing.

The entry file carries three pairs of markers — `webx:imports`, `webx:styles`, `webx:modules` —
and the command writes between those and nowhere else. Everything outside them is the site's:
the avatar resolver, the media field handed to the modules that take one, whatever else only
the site knows. A module the site has configured its own way (`seo({ mediaField: WxMediaField })`)
counts as registered and is left alone; delete the markers and the command prints the lines to
add by hand rather than guessing where they went.

If your Vite configuration is shaped in a way the command does not recognise, it says which
line to add rather than rewriting a build it does not understand.

Building the panel outside Laravel's Vite — in CI, say, or alongside a front end that has its
own toolchain — is the other supported route: name the files instead.

```php
'assets' => ['/webx/webx.css', '/webx/webx.js'],
```

Give those files hashed names. With stable ones a browser keeps the panel it saw yesterday.

## The shell

Every address below the panel prefix serves the same page: routing inside the admin belongs to
the front end, and a reloaded page three levels deep must not 404. The view carries the manifest
URL in a meta tag and mounts `#webx-app`.

To point it at your own assets, either publish it —

```bash
php artisan vendor:publish --tag=webx-admin-views
```

— or push onto the `webx-head` and `webx-body` stacks from a view composer.

## Drafts and versions

Three places, and one rule that keeps them apart: an entity's columns are what is on the site
now, its `draft` column is what is being prepared, and `entity_versions` is the site's past.

```php
Schema::table('pages', fn (Blueprint $table) => $table->draft());   // `draft` json, `published_at`

class Page extends Model
{
    use HasDraft, HasVersions;
}

$page->saveDraft(['title' => 'New', 'blocks' => [...]]);   // on every save in the panel
$page->withDraft()->title;                                  // 'New' — a copy, for the preview
$page->title;                                               // still what the site shows
$page->isPublished();                                       // false until the first publish

$page->publish(authorId: 7, comment: 'First cut');          // columns ← draft, version 1 written
$page->publishedVersions();                                 // the history, newest first
$page->restoreVersion(1);                                   // into the draft; publish to roll back
$page->publishedVersions()->first()->pin();                 // kept whatever the limit says
```

The history is made of publications only: every save writes an autosave into a ring of the
last five instead, insurance rather than history, and publishing drops them. A version holds
every attribute except the key, the timestamps, the draft and what describes the entity's place
rather than its content — `slug`, `parent_id`, the tree bounds; override
`unversionedAttributes()` to change the list. Structure is applied at once, never at
publication. An entity keeps thirty publications (`webx-admin.versions.limit`), pinned ones
excepted; `webx:versions:prune` trims everything to a limit lowered after the fact.

A handler answering a public address reads `isPublished()`; the preview that shows a draft
lives in `webx-ui/module-blocks`.

## Demo content

```bash
php artisan webx:demo            # something to look at on a site nobody has written into yet
php artisan webx:demo --remove   # and all of it back out again
```

Every module brings its own: a section with something worth showing implements `ProvidesDemo`,
and the command walks the ones that do. `requires()` names the modules it has nothing to seed
without — and is the order too, so a page made of blocks is seeded after the block types
whatever the navigation says. A module whose requirement is not installed is skipped out loud
(`blog skipped: no media`) rather than failing halfway.

```php
final class ProductsModule extends AbstractModule implements ProvidesDemo
{
    /** @return list<string> */
    public function requires(): array
    {
        return ['categories'];
    }

    public function seed(DemoLedger $ledger): void
    {
        $product = Product::query()->create([...]);

        $ledger->created($product);                 // removing it deletes it
        $ledger->changed($catalogue, ['intro']);    // called before the change; removing puts it back
        $ledger->note('the catalogue page was left alone: …');   // printed as a warning
    }
}
```

Removal follows the journal in `storage/app/webx-demo.json` backwards and knows nothing else:
what a module did not write down is not removed, and nothing is guessed from a slug. A record
that was there before the demo is restored rather than deleted — the home page of
`webx-ui/module-pages` comes from a migration and has to survive this — so afterwards the
database holds what it held before, which for a page that was an unpublished stub means an
unpublished stub again.

`webx:make-module` generates the interface and an empty `resources/demo/<id>` with the class,
so a new section starts with somewhere to put its fixtures.

## Nightly database backup

```
php artisan webx:db:backup
```

A gzipped dump into `storage/app/private/backups`, on the scheduler at `webx-admin.backup.at`
and kept for `keep` days. Insurance rather than a restore system — the file is on the same disk
as the database — and there is no restore anywhere in the panel; what the panel has is one line
at the foot of the settings screen saying when the last one was, and a warning when there has
not been one for two days. The site still needs a system cron on `schedule:run`. The whole of
it, including how to pull one table out of a finished dump, is in
[the guide](https://webx-ui.github.io/webx-ui/guide/backups).

## Configuration

`config/webx-admin.php` covers the title, the two paths, the middleware groups, the version
limits and the nightly backup. Moving the panel means clearing the route cache afterwards.

## Languages

Ten shipped: en, ru, uk, de, pl, fr, es, it, pt, tr. English is the fallback, and it is laid
_under_ the chosen language line by line, so a half-translated group shows what it has and
English for the rest.

Only English, Russian and Ukrainian have been read by a speaker; the other seven are machine
translations. A correction from someone who speaks the language is welcome — they are plain PHP
arrays in `lang/`.

## Licence

MIT.
