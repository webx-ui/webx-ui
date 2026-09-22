# A WebX UI site

A Laravel application with the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel on it.

```bash
composer create-project webx-ui/site example.local
```

The questions that follow — name, domain, database, languages, modules, the first administrator
— are `php artisan webx:setup`, and it is the same command on a site that is already running:
run it again after installing a module and it wires in what is new and leaves the rest alone.

At the end it prints the panel's address, the login and a generated password.

## What to delete first

Everything here is yours, and three things are here only so that the first page is not blank:

| what                                                | when                                                     |
| --------------------------------------------------- | -------------------------------------------------------- |
| `resources/views/demo.blade.php` and its route       | as soon as the site has a front page of its own           |
| the `<style>` block in `components/layout.blade.php` | when you start on the design — `resources/css/app.css`    |
| the demo content                                     | `php artisan webx:demo --remove`                          |

The demo content is rows in the database and files on disk, and the command takes out exactly
what it put in, backwards, by a journal in `storage/app/webx-demo.json`. The layout is files and
stays.

## What not to delete

`routes/web.php` is empty, and that is the point. Every public address of this site comes from
the address registry — a page, an article, a tag — and `Route::fallback()` answers them. A route
declared in `web.php` wins over the fallback for good, so it takes an address the panel can then
never hand out. `/` costs the most: the home page of the tree is an empty slug formatted to `/`,
and a `Route::get('/')` here is a home page the panel cannot even save.

## The seams

Three places where the site makes the library its own:

**The layout.** `resources/views/components/layout.blade.php` is `<x-layout>`, and every public
view a module ships stands inside it. The agreement is two lines long — a `head` slot and the
default slot for the content — and `config('webx-pages.layout')` and its siblings are what point
at it. Keep `@stack('head')`: a slot is one place, and what a block type pushes cannot reach it.

**The views.** `php artisan vendor:publish --tag=webx-blog-views` copies a module's public views
into `resources/views/vendor/`, where they win over the package's. Per file, not per directory:
keep the one you rewrote and delete the rest, and those keep arriving fresh with each release.

**The services.** A module's behaviour is replaced through the container, in `register()` and not
in `boot()`. Where the site adds something beside what a package does rather than instead of it —
a route type, a field type, an SEO source — there is a registry to put it in, and that is the
usual answer.

## Containers

Optional, and yours to delete like everything else here: `Dockerfile`, the two compose files
and `docker/`.

```bash
docker compose up -d --build                                        # the production shape
docker compose -f docker-compose.yml -f docker-compose.dev.yml up   # somewhere to work
```

One image — Composer dependencies, the front end built by this site's own Vite, then php-fpm,
nginx, a queue worker and the scheduler under supervisor — beside a MariaDB. Build it after
`webx:setup`: both lock files are what the image installs. Nothing here terminates TLS; the
container listens on `${APP_BIND}:${APP_PORT}` and a proxy in front of it holds the
certificate. The development stack mounts this checkout into the same image, adds the Vite dev
server and a Mailpit, and caches nothing.

The variables they read are at the bottom of `.env.example`, in the same `.env` as everything
else. What a container does between starting and serving is `php artisan webx:boot` — run it
with `--pretend` to see the list.

## Day to day

```bash
php artisan webx:panel --sync     # after installing a module: wires it into the panel's build
npm run dev                       # while working on the panel or the design
php artisan webx:doctor           # before a deploy: says what has come loose
```
