---
'@webx-ui/php': minor
---

The registry of a site's public addresses

`webx-ui/routing` is one flat namespace — `/about`, `/blog`, `/alternator-belt-7100104` — one row
per address, and one resolver that hands a request to whoever owns the path it matched. Uniqueness
holds across every kind of content at once, which is the part no single module can do on its own: a
module cannot see another module's addresses, and those are exactly the ones it collides with. A
library rather than a section: the public side of a site uses it with no panel in sight.

Add `HasUrl` to a model and register its type, and saving, renaming, moving in the tree, deleting
and restoring keep the registry in step from then on. The address itself is built by the type's
formatter — `Slug`, `TreePath`, `SlugId`, `SlugSku`, `Prefixed`, or one of your own — and a
formatter is a pure function of the entity, so a save, a preview in a form and
`webx:routes:rebuild` cannot disagree about what the address is. A project overrides somebody
else's formatter from config and moves the addresses that already exist with one command; nothing
dies in the move, because every address that changes leaves an alias behind. Collisions are settled
by the type: refused under the slug field, or suffixed with the suffix written back into the entity
so the form shows what the site will serve.

Reading is a fallback route, which is the whole trick — a fallback is tried only when nothing else
matched, so a project's own `/search` wins with no ordering to arrange. One spelling per address
(a trailing slash, a capital letter or a doubled slash is a 301, query kept); exact beats prefix, so
`/about/mission` is its own page rather than a tail handed to `/about`; an alias answers 301 and
takes the tail with it, so a renamed category keeps its pages of filters. Publication stays the
entity's business: the registry has a row for everything that exists, and the handler decides
whether to show it. Addresses the application answers itself are refused when an entity is saved,
not when a request arrives — losing silently to a live route leaves an editor with a page that
exists everywhere except on the site.

`webx:routes:rebuild` recomputes the addresses of a type, `webx:routes:check` reports what no
constraint can — rows with no entity, entities with no row, aliases leading nowhere, addresses a
project has since claimed with a route of its own — and exits 1 so a deploy can stop on it.

`UrlNormaliser` has moved here from `webx-ui/module-seo`: one spelling of an address for both
halves of the system, with `key()` added beside `normalise()` for the registry's own. `module-seo`
now depends on `webx-ui/routing`, reads the aliases through the narrow `RouteAliases` contract,
says in `POST /seo/test-url` what the registry holds at an address, and prints the `<head>` of a
page the resolver found without the template having to name it.

The guide is `apps/docs/guide/routing.md`.
