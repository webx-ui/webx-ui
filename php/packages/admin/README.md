# webx-ui/admin

The frame a [WebX UI](https://github.com/webx-ui/webx-ui) admin panel is built on.

It answers three questions and stays out of everything else: what modules this panel has, where
the front end can ask, and what to serve when someone opens a deep link. Entities, screens and
permissions belong to the modules.

## Requirements

- PHP 8.3+
- Laravel 13

## Install

```bash
composer require webx-ui/admin
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

## The shell

Every address below the panel prefix serves the same page: routing inside the admin belongs to
the front end, and a reloaded page three levels deep must not 404. The view carries the manifest
URL in a meta tag and mounts `#webx-app`.

To point it at your own assets, either publish it —

```bash
php artisan vendor:publish --tag=webx-admin-views
```

— or push onto the `webx-head` and `webx-body` stacks from a view composer.

## Configuration

`config/webx-admin.php` covers the title, the two paths and the middleware groups. Moving the
panel means clearing the route cache afterwards.

## Licence

MIT.
