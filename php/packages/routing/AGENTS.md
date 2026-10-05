# webx-ui/routing

The address registry: one table holds the public address of every entity of every kind, per
language, so two things can never take the same address and a moved entity leaves a 301 behind.
It answers requests through `Route::fallback()`, so a project's own routes always win. It knows
no module names — pages, articles, products arrive as registered types. Hand-written redirects
and meta tags are `webx-ui/module-seo`, a request's language `webx-ui/localization`, the tree
behind `TreePath` `webx-ui/nested-set` — read their guides for those.

## What it owns

- **Table** `routes` (`WebxUi\Routing\Models\Route`): `locale`, `path`, `kind` (`canonical` or
  `alias`), `target_id` (an alias points at the canonical row, so renames never chain),
  `entity_type`, `entity_id`; unique on `locale` + `path`.
- **Trait** `HasUrl` — `routePath()`, `routeCanonical()`, `url()`, `routes()`, `hasUrlIn()`.
  Saving, moving, deleting and restoring the model keep the registry in step.
- **Address types** registered with `app(RouteTypes::class)->register(new RouteType(...))`:
  `type` (the morph alias), `model`, `formatter`, `handler` (a `RouteHandler`), `acceptsTail`,
  `onConflict`.
- **Formatters** (`WebxUi\Routing\Formatters`): `Slug`, `TreePath`, `SlugId`, `SlugSku`,
  `Prefixed`; your own implements `PathFormatter` — a pure function of the entity.
- **`OnConflict`**: `Fail` refuses the save with the error on the slug field (`PathRejected`, a
  validation error); `Suffix` takes `-2`, `-3` and writes it back into the slug.
- **Fallback route** `webx.routing.resolve` (`ResolveController`): one spelling (301), exact
  beats prefix, an alias answers 301 with its tail, the handler decides publication. Then
  `MissHandler`s registered on `Misses`, then 404. `Resolution::of($request)` is what was found.
- **Contracts** `Visible` (shown on the site or not — the handler and the sitemap ask it),
  `RouteAliases` (the read side the SEO screen shows).
- **Commands** `webx:routes:rebuild` (`--type=`, `--dry-run`), `webx:routes:check`.
- Audit checks when `webx-ui/module-audit` is installed: `routing.orphan`,
  `routing.alias_broken`, `routing.shadowed`, `routing.no_address`, `routing.unknown_type`.

## Change it without forking

| You want                                        | Do this                                                                                                                                               |
| ----------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| Another address shape for a module's type       | `'types' => ['article' => ['formatter' => YourFormatter::class]]` in `config/webx-routing.php`, then `php artisan webx:routes:rebuild --type=article` |
| Addresses for a model of your own               | `use HasUrl` on the model, `app(RouteTypes::class)->register(new RouteType(...))` in a provider                                                       |
| A segment in front of a type's addresses        | `new Prefixed('segment', Slug::class)` as the formatter                                                                                               |
| Keep an address from ever being handed out      | add it to `'reserved'`; any route the application declares is reserved already                                                                        |
| Answer a miss (old spelling, deleted item)      | a `MissHandler` class, `app(Misses::class)->register(YourHandler::class)`                                                                             |
| Serve the registry from a route of your own     | `'fallback' => false`, then call the resolver yourself                                                                                                |
| Other middleware on public pages                | `'middleware'` (default `['web', 'webx.locale']`)                                                                                                     |
| Import thousands of rows                        | `app(RouteSync::class)->bulk($query->lazy())` — one upsert per chunk                                                                                  |
| A type's addresses to redirect, not show a page | bind your `RouteHandler` over the module's (`$this->app->bind(EventHandler::class, Yours::class)`) and implement `Contracts\NotAPage` on it           |
| A redirect an editor writes by hand             | a rule in `webx-ui/module-seo`, not an alias                                                                                                          |
| Publish the config                              | `php artisan vendor:publish --tag=webx-routing-config`                                                                                                |

## Do not

- Do not edit anything in `vendor/webx-ui/routing`. Every row above is the supported way; if
  none fits, the package is missing a seam — say so instead of working around it.
- Do not write `routes` rows by hand or fix an address with SQL: the observer and the formatter
  own them. Change the entity's slug, or run `webx:routes:rebuild`, which leaves aliases behind.
- Do not change a type's formatter without rebuilding: existing addresses do not move on their
  own. Run `webx:routes:rebuild --type=<type> --dry-run` first, then without it.
- Do not change slugs with a query-builder `update()` or an import that skips `RouteSync::bulk`:
  the model events never fire and the registry drifts. `webx:routes:check` finds it, `rebuild`
  repairs it.
- Do not add a catch-all route (`{any}`) in `routes/web.php`: it shadows every registry address.
  A route of your own for a fixed path is fine — it wins over the registry by design.
- Do not read anything but the entity in a formatter (no request, no time, no config): a save,
  a preview and a rebuild must compute the same address.
- Do not treat an alias as a redirect rule: it is exact, always 301, and dies with its entity.
  A redirect elsewhere is a rule in `webx-ui/module-seo`.

## Check your work

- `php artisan webx:routes:check` — orphans, entities without an address, broken aliases,
  addresses an application route has claimed. Exit code 1 when anything is found.
- `php artisan webx:routes:rebuild --dry-run` — prints what would move and writes nothing.
- Open the old address of a renamed entity: it must answer 301 to the new one.
- `php artisan webx:doctor` — what is misconfigured on the site.

## Read more

- [README.md](README.md) in this directory — the PHP API.
- Guide: https://webx-ui.github.io/webx-ui/guide/routing
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_ROUTING.md
