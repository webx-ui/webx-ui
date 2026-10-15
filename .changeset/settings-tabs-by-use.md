---
'@webx-ui/php': minor
---

`module-settings` offers Branding and Contacts only where something uses them. Whoever prints contacts declares them: a theme under `settings` of its manifest (`"settings": {"contacts": ["phones", "hours"]}`, summed down the chain — `theme-default` declares phones, emails, addresses, hours and socials), a package under `extra.webx.settings`, the project in `webx-settings.uses`. Branding is the panel's own and is hidden only without `wx-media` (no `webx-ui/module-media`) — no more `Unknown type: wx-media` on a site without the file library. A hidden tab is gone from the panel, from `settings_*` over MCP and from the demo; its stored values stay, `contacts()` and the panel's logo still read them, and it comes back with them once declared. `settings_list` / `settings_get` / `settings_set` no longer reach keys hidden by `contacts.sections` either. `webx:doctor` reports «Settings: branding / contacts» and warns when a declared tab lacks its field type.

Transition — **removed in the next minor**: a site whose theme chain has no `settings` key keeps Contacts, and so does a site with no theme but contacts already stored (with a `webx:doctor` warning to add `'uses' => ['contacts']`).

`module-admin`: `ScreenRegistry::extend()` takes a closure — a patch worked out when the tree is built, the tree not kept before the application has booted; `Tree::editable()` — the fields not under a `visible: false` node. `themes`: the manifest key `settings`, `ThemeChain::settings()`; `webx:theme:eject` carries it into the local theme.
