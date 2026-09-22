# Extending

Three ways a site makes the library its own, in the order you reach for them: publish a view and
rewrite it, register something of your own beside what a package already does, or replace one of
its services in the container. The layout, which is the fourth and the first one you meet, is in
[A new site](./new-site.md#the-layout).

## Publishing views

Every module with a public side ships the views for it — five in `webx-ui/module-blog`, a form
and a letter in `webx-ui/module-inbox`, one in `webx-ui/module-pages` — and they are the least
markup that works. No CSS framework anywhere: semantic elements, class names, and the assumption
that you will rewrite them.

```bash
php artisan vendor:publish --tag=webx-blog-views
```

That copies them into `resources/views/vendor/webx-blog/`, where they win. The package registers
two paths, `resources/views/vendor/<package>` first and its own second, and the resolver takes
the first match — so what you published is what renders, including anything under `components/`.

**The fallback is per file, not per directory.** Publish all five views of the blog, rewrite
`article.blade.php` and delete the other four: those four go back to coming out of the package,
arriving fresh with every release. That is almost always what you want, and almost nobody does
it — the usual outcome is five files carried for years because of one.

Two things publishing does not do:

- **A class component is not overridden by it.** `<x-webx-inbox::form>` resolves to a class, and
  what publishing gives you is the markup that class renders (`webx-inbox::form`), not the class.
  The logic — which form, CSRF, the honeypot, the errors under the fields — stays in the package.
  For the case where you want neither, the component takes a `view` prop that sends the render
  into a template of yours without publishing anything.
- **It is not picked up in the same process.** `loadViewsFrom` decides whether
  `resources/views/vendor/<package>` exists when the view factory resolves the hint, so a command
  that publishes and then renders still renders the package's copy. In ordinary life — publish,
  then a request — it never shows; inside `webx:setup` it would, which is why everything there
  runs as a child process.

## Registries

Where the site adds something **beside** what a package does rather than instead of it, there is
a register to put it in, and this is the ordinary way to extend anything here. No conflict
between two additions, no ordering to arrange, and nothing `final` in the way.

| register         | what goes in it                           | package        |
| ---------------- | ----------------------------------------- | -------------- |
| `ModuleRegistry` | a section of the panel                    | `module-admin` |
| `ScreenRegistry` | a screen described in JSON                | `module-admin` |
| `FieldTypes`     | a field type those screens can use        | `module-admin` |
| `NoteTypes`      | a record that can carry notes             | `module-admin` |
| `RouteTypes`     | a kind of thing that has a public address | `routing`      |
| `SeoSources`     | somewhere `<head>` gets its values from   | `module-seo`   |

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    $this->app->make(FieldTypes::class)->register('shop-price', new PriceType);
}
```

`boot()` here, not `register()`: the register is assembled by the packages' own providers, and
what you are doing is adding to something that already exists.

## Replacing a service

Sometimes it is not the markup that has to differ but the behaviour, and then the question is
what the _consumer_ asked for in its type hint — because that is what decides whether there is a
door at all.

**You cannot extend a package's service.** Almost all of them are `final`: `Forms`, `Assets`,
`Captcha`, `Seo`, `SeoSources`, `SeoRules`, `UrlMatcher`, `ModuleRegistry`, `ScreenRegistry`,
`FieldTypes`, `NoteTypes`, `Backups`, `LibraryUrls`. Of the ones in the container only `Locales`
is open. That is a promise rather than an oversight: a package promises behaviour through a
contract and not through its internal structure, and a subclass reaching into the internals
breaks on the first refactor inside the package.

Three cases:

1. **The consumer asks for an interface** — `AssetUrls`, `BrandingSource`. There is a door, and
   `final` is no obstacle to it: it forbids inheritance, not composition.
2. **The consumer asks for a non-final class** — `Locales`. `extends`, then bind yours. The
   internals are still not yours: everything is promoted `private readonly`, so a subclass uses
   the public API like anybody else.
3. **The consumer asks for a `final` class** — `Forms`, `Captcha`, `Seo`. There is no door,
   neither by inheritance nor by binding: whatever you substitute fails on the type hint of
   whoever asked for it. That one opens with a change in the package — an interface.

The working move for the first case is to implement the interface and **wrap** the package's own
implementation by injecting it:

```php
// app/Assets/CdnUrls.php — a private bucket served through our own CDN
use WebxUi\Admin\Contracts\AssetUrls;
use WebxUi\Media\Storage\LibraryUrls;

final class CdnUrls implements AssetUrls
{
    public function __construct(private readonly LibraryUrls $library) {}

    /** @param list<string> $paths  @return array<string, string|null> */
    public function urls(array $paths): array
    {
        return array_map(
            static fn (?string $url): ?string => $url === null
                ? null
                : str_replace(config('app.url'), 'https://cdn.example.com', $url),
            $this->library->urls($paths),
        );
    }
}
```

```php
// app/Providers/AppServiceProvider.php
public function register(): void
{
    $this->app->bind(AssetUrls::class, CdnUrls::class);
}
```

There is no priority to configure: package providers register before the application's, so the
site's binding always wins.

Two traps:

- **`register()`, not `boot()`.** Somebody else's `boot()` may resolve the service before yours
  runs, and the substitution is then late by exactly the request on which it mattered. It is the
  same rule as "configuration in `register()`", which is where Passport catches people out.
- **Repeat the lifetime.** The package writes `scoped(Forms::class)` or `singleton(Seo::class)`;
  a `bind()` replaces the registration _and_ the lifetime with it, and a service that used to
  cache within a request starts being built on every injection. Nothing fails — it is just
  quietly slower.

### When a new interface is worth it

Not in advance: every contract has to be kept stable afterwards. The sign that it is time is
simple and visible the moment it happens — somebody needed to replace a `final` class. The first
obvious candidate is already in sight: `Captcha` knows exactly two providers as constants, and
which provider a site uses is the site's business rather than the library's.
