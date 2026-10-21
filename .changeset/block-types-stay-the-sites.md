---
'@webx-ui/php': minor
'@webx-ui/module-blocks': minor
---

**The deploy no longer writes block types — import them with `webx:blocks:import`.** A block type on a running site belongs to the site: the client changes it in the panel or by MCP, and an update must not undo that.

- `webx:boot` no longer runs `webx:blocks:import --publish` for `resources/blocks/*.json`, and `webx:theme:sync` (the deploy's and `post-update-cmd`'s) publishes files only: it no longer puts the theme layers' block types on the site. `--force` re-copies files and nothing else; `--adopt` is gone — a script that still passes it fails with «option does not exist».
- `webx:blocks:import` is the one way onto a running site, run by a person: `--path` (default `resources/blocks`) or `--theme` for the layers' types. New types are created; a type the site has is kept — `unchanged`, or `differs` with what differs (fields `+ − ~`, template, styles, script, sample, row). `--replace=<slug>` (repeatable) writes that one type over as a new version; there is no replacing all at once (`--replace=*` is refused). `--publish` publishes what was created or replaced.
- The panel's «Import» shows the same: a `differs` row with its diff and a «Replace» switch of its own, off by default.
- `webx:setup` still gives a new site the theme's block types (`webx:blocks:import --theme --publish`) and the chosen modules' offers. `webx:module:add` installs none and prints `webx:blocks:offered --install --module=<id>` instead.

Existing sites need nothing: their types are in place, and the `webx:theme:sync` line in `post-update-cmd` is safe now. A new block of a theme release reaches a site by `php artisan webx:blocks:import --theme` (`--dry-run` first).
