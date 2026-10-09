---
'@webx-ui/php': minor
---

A new site opens styled. `webx:setup` installs `webx-ui/theme-default`, creates the site's own
theme `theme/` over it and writes `WEBX_THEME=theme` before the panel is wired, then publishes the
theme's files with `webx:theme:sync`; `--theme=<vendor/name>` stands `theme/` on another packaged
theme, and `--no-theme` writes the layout, header, footer and stylesheet the skeleton used to ship.
A site that already has a layout of its own or a theme keeps it. The new `webx:theme:make <name>
--local [--uses=…]` lays out a local theme — `theme.json`, an empty `tokens.json`, the two Vite
entries `@webxTheme` asks for and a test the site's `php artisan test` runs. The skeleton loses its
layout and its eighty lines of inline style; its Vite builds `theme/src` (or `resources/` without a
theme), its Dockerfile copies `theme/`, and `webx:boot` syncs the theme's files into a fresh
container. `webx:panel --sync` points the modules at a layout that comes from the theme.
