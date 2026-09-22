# A new site

One command turns an empty directory into a Laravel application with the panel on `/cms`, the
modules you chose, an administrator to sign in as, and enough content to look at.

```bash
composer create-project webx-ui/site example.local
```

Then questions, with the answers already worked out from the directory you are standing in:

```
 What the site is called ... Example
 The domain ................ example.local          ← the directory name
 Database .................. mysql / example · create it? yes
 Languages ................. en
 Modules ................... ◼ pages   ◼ media   ◼ seo    ◼ settings
                             ◼ blocks  ◻ blog    ◼ inbox  ◼ admins
 Demo content .............. yes
 Administrator ............. you@example.com
```

At the end it prints the panel's address, the login and a generated password. Roughly three
minutes, most of it `npm install`.

All of it takes flags instead, for a machine that has nobody to ask:

```bash
composer create-project webx-ui/site example.local -- \
  --modules=pages,media,seo,settings,blocks,inbox,admins \
  --db=example --locales=en,uk --demo --no-interaction
```

## The same command, six months later

Everything after `create-project` is `php artisan webx:setup`, and it is written to be run
again. On a site that has been live since spring it asks the same questions with the current
answers as the defaults, installs what is now ticked and is missing, and leaves alone what is
not. **That is how a module is installed** — there is no second, harder path for a site that
already exists:

```bash
php artisan webx:setup            # tick `blog`, answer the rest with Enter
```

Under it, in the order they run:

| step                                                 | what it does                                             |
| ---------------------------------------------------- | -------------------------------------------------------- |
| the database                                         | creates it if it is not there, writes `DB_*` into `.env` |
| `composer require`                                   | the Composer half of every module ticked                 |
| [`webx:panel --sync`](#what-webx-panel-sync-touches) | the entry file, `package.json`, Vite, the config keys    |
| `npm install && npm run build`                       | the panel's front end — the slow step                    |
| `migrate`, `storage:link`, `webx:admin --super`      | the database and the first administrator                 |
| [`webx:demo`](#demo-content)                         | something to look at                                     |
| [`webx:doctor`](#webx-doctor)                        | says whether all of that actually stands up              |

Every one of those runs as its own `artisan` process, which matters more than it looks. The
process running `webx:setup` booted before it rewrote `.env` and before `composer require`: its
configuration is the old file and its autoloader has never heard of the module it just
installed. A child gets both.

### Existing sites

A site that was not made from the skeleton runs the same command. What it needs first is the
frame and an `.env` to write into:

```bash
composer require webx-ui/module-admin
php artisan webx:setup
```

## What `webx:panel --sync` touches

A module of the panel is two packages — a Composer one on the server, an npm one in the build —
and each Composer package names its own npm half, under `extra.webx` in its `composer.json`. The
installer walks what is installed and tops up four things:

| file                    | what it does                                                  | if you have already changed it |
| ----------------------- | ------------------------------------------------------------- | ------------------------------ |
| `resources/js/admin.ts` | adds the import, the stylesheet and the line in `modules: []` | leaves it                      |
| `package.json`          | adds the npm halves that are missing                          | never moves a version          |
| `vite.config.js`        | adds the entry to the `laravel()` plugin's input              | says so instead                |
| `config/webx-*.php`     | the `vite` key, and `layout` for modules with public pages    | never over a value you chose   |

The entry file is yours. Only the three marked regions are written into:

```ts
// webx:imports
import { pages } from '@webx-ui/module-pages'
// /webx:imports
```

Everything outside them — `resolveAvatar`, the `WxMediaField` you hand to `seo()`, the plugins —
is read by nobody and rewritten by nothing. Delete the markers and the command tells you the
four lines to add by hand and exits successfully; it is your file, and erasing them is a fair way
of saying so.

## The layout

`resources/views/components/layout.blade.php` is the document every public page of this site is
printed in, and the agreement between it and the modules is two lines long: a `head` slot, and
the default slot for the content. A page, an article, a rubric asks for it by name — that is what
`config('webx-pages.layout')` holds — and stands inside it.

```blade
<head>
    {{ $head ?? '' }}
    @stack('head')
</head>
<body>
    <x-header />
    <main>{{ $slot }}</main>
    <x-footer />
</body>
```

Both lines are needed and for different reasons. A slot is one place, and what a block type or a
partial pushes cannot reach it; a layout without `@stack('head')` loses metatags **silently** —
the page still looks finished, and you find out from a search engine. `webx:doctor` refuses on
exactly that.

Nothing else is agreed, and nothing else should be. There is no CSS framework anywhere in the
packages: a module's view is semantic markup with class names, and the eighty lines of stylesheet
inside the layout are there so that "unstyled" does not mean "broken". Delete that `<style>`
block the day you start on the real design.

## Demo content

```bash
php artisan webx:demo             # fill the site
php artisan webx:demo --remove    # take it back out
```

Each module brings its own and knows nothing about the others': `blocks` seeds three block types,
`pages` a home page and one below it, `media` a few pictures, `inbox` a contact form, `blog` a
rubric and two articles — one dated ahead, so that "Scheduled" is visible. A module whose
requirements are not installed is skipped with the reason said out loud rather than silently.

Removal follows a journal in `storage/app/webx-demo.json`, backwards, and knows nothing else:
what the demo wrote down is what comes out, and everything you have written since stays. Rows
that existed before the demo filled them — the home page arrives with a migration — are put back
as they were rather than deleted, which means an unpublished home page becomes unpublished again
and `/` answers 404 until you publish it.

The layout is files, not rows, so none of this touches it. After `--remove` the site is working
and empty.

## `webx:doctor`

The last step of the installation and the last step of a deploy, and the same command both
times. It repairs nothing: every line says what to run, and the running is left to you. The one
thing it writes is a probe file on the library's disk, and it deletes that again.

```bash
php artisan webx:doctor
```

```
  ✓ Both halves: 8 packages on the server, all of them in resources/js/admin.ts.
  ✓ Bundle: built, and no newer than every entry it was built from.
  ✓ npm halves: 13 packages, each installed at the version the server asks for.
  ✓ Migrations: 39 applied on [mysql], none pending.
  ✓ storage:link: one link, and it leads where it should.
  ✓ Media disk: [public] takes a file and gives it back.
  ✓ Layout: <x-layout> takes both what is pushed and what is passed.
  ✓ Languages: en, ru — the table and the fallback agree.
  ✓ Panel words: the dictionary answers in en, ru, uk.
  ✓ Panel: answers at /cms.
  ✓ config cache: newer than everything it was built from.
  ✓ route cache: newer than everything it was built from.
  ✓ Rate limits: 2 named limiters, all declared.
  ✓ Agent access: Passport has its keys and its tables — an agent can connect.

   INFO  Everything this site needs is in place.
```

A refusal reads the same way, with the command that mends it in the sentence:

```
  ✗ Layout: resources/views/components/layout.blade.php has no @stack('head') — metatags pushed
    by a block or a partial are dropped, and the page still looks finished. Add it inside <head>.
  ✗ npm halves: package.json asks for @webx-ui/module-pages@^0.2.0; the installed Composer half
    needs ^0.3.10 — run `npm install @webx-ui/module-pages@^0.3.10`.
```

What it looks at is a list of things that have each cost somebody a day, and what they have in
common is that none of them announce themselves:

- **Both halves.** A Composer package installed and never wired in is a section the server
  answers for and nobody can reach; a call left in the entry file whose package is gone is a
  section the bundle draws and the server 404s.
- **The bundle.** Built after the last change to the entry file — the commonest failure of the
  three, and the one that looks most like the change simply not working.
- **npm ranges.** Below 1.0 the caret pins the minor, so a site asking `^0.18.0` never reaches
  the 0.19 the module needs. That shows up as `[MISSING_EXPORT]` in a build, or as a component
  that is quietly missing at runtime.
- **Migrations.** Laravel sorts every package's migrations into one list by file name, so a
  module installed today brings migrations that sort in among last year's. A migration MariaDB
  refused is never recorded as run, so it turns up here as pending whatever the deploy log said.
- **Storage.** `storage:link` is one command and is forgotten on every second deploy, and a link
  whose target has been cleared out looks exactly like one that was never made. The library's
  disk is asked by writing a file to it, because permissions are the other half of that story.
- **The layout.** Above.
- **Languages.** `webx-localization.locales` is only a seed for the `locales` table; the table is
  what the site publishes in. A fallback the site does not publish in falls back to nothing.
- **Caches and limits.** A configuration cache written before `webx:panel` added the entry serves
  a panel with no front end. A `throttle:<name>` nobody declared is read by Laravel as a _number_
  of attempts — zero — so the address answers 429 to everyone, and only ever after
  `route:cache`.
- **Agent access.** With `webx-ui/mcp` installed: Passport's keys and tables. Without the keys
  the guard cannot be built, so a call with no token answers 500 where it should answer 401.

A failure exits non-zero, which is what puts it in a deploy:

```bash
php artisan webx:doctor --strict   # anything worth mentioning stops the deploy too
```

## What the skeleton gives you, and what to delete

```
resources/
├── css/app.css                    # empty: this is where the design goes
├── js/app.js
└── views/
    ├── components/
    │   ├── layout.blade.php       # <x-layout>
    │   ├── header.blade.php       # a menu out of the page tree
    │   └── footer.blade.php
    └── demo.blade.php             # "the modules are standing" — delete it first
routes/web.php                     # empty, and meant to stay that way
```

`routes/web.php` being empty is the point, not laziness. Every public address of this site comes
out of the [address registry](./routing.md) and is answered by `Route::fallback()`. A route
declared here wins over the fallback for good, so it takes an address the panel can then never
hand out — and `/` costs the most, because the home page of the tree is an empty slug formatted
to `/`. A `Route::get('/')` there is a home page the panel cannot even save.

The one exception is written for you: on a site **without** `module-pages`, `webx:setup` puts the
demo page on `/` behind a marker of its own, and takes the line back out the day you install
`module-pages`.

## Containers

The skeleton carries a `Dockerfile`, two compose files and a `docker/` directory, and they are
yours like everything else here — delete them on a site that deploys some other way.

```bash
docker compose up -d --build                                        # the production shape
docker compose -f docker-compose.yml -f docker-compose.dev.yml up   # somewhere to work
```

The first builds one image — Composer dependencies, the front end built by the site's own Vite,
then php-fpm, nginx, a queue worker and the scheduler under supervisor — and starts it beside a
MariaDB. Nothing terminates TLS: the container listens on `${APP_BIND}:${APP_PORT}` and a proxy
in front of it holds the certificate. The second mounts the checkout into the same image, adds
the Vite dev server and a Mailpit, and caches nothing, so a machine with no PHP and no Node on
it is still a machine you can work on.

Both read the `.env` you already have. The variables the containers add are at the bottom of
`.env.example`: where to bind, which port, the database root password, and the first
administrator.

### `webx:boot`

What a container does between starting and serving is one command:

```bash
php artisan webx:boot --pretend    # the list, without running any of it
```

It waits for the database, migrates, links storage, writes Passport's keys if they are not
there yet, seeds the languages and clears the dictionary the release before it built, imports
the block types the repository carries, creates the first administrator out of the environment,
and caches configuration, routes, views and events. Which of those a site needs it works out
from what is installed, so a module added six months from now brings its step with it — and
`--no-cache` is the development container's whole difference.

Every step is idempotent, because every boot runs all of them. The one thing it will not do is
publish Passport's migrations: the copy is stamped with the minute it was made, so a boot that
published would hand each container a migration under a name the migrations table has never
seen, and the second deployment would stop on `table oauth_auth_codes already exists`.
Publishing is `webx:setup`'s, once, into the repository.

### Two things worth knowing before the second deploy

**Storage is one volume, not two.** Uploads, the nightly dumps and the logs are the obvious
part; Passport writes its keys a level above them, in `storage/` itself. A volume around
`storage/app` alone leaves the keys inside the image, and then an upgrade hands out new ones and
disconnects every agent that was connected, with nothing anywhere saying why.

**A volume takes its contents from the image once** — the first time it is used, and never
again. So the entrypoint creates the directories under `storage` on every boot: a release that
starts writing somewhere new finds nothing there on a site that has been running since before
it, and what fails is whatever first tried to write.

## Where to go next

- [Extending](./extending.md) — publishing a module's views, replacing its services, the
  registries to add your own things to.
- [Addresses](./routing.md) — the registry every public URL comes from.
- [Pages](./pages.md), [Blog](./blog.md), [Blocks](./blocks.md) — what the modules actually do.
