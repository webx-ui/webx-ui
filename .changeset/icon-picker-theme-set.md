---
'@webx-ui/core': minor
'@webx-ui/php': minor
---

`WxIconPicker` picks from a set of its own: `icons` (the names) and `sprite` (an SVG sprite whose `<symbol id="<name>">` draw them, through `<use>`) — the grid, the search and the preview are the set's, and a name outside it is marked unknown. Without them the field is the panel's set, as before; `icons` without `sprite` is a part of the panel's set. In `module-admin` a screen names the set with `props.source: "theme"` — the icons of the site's theme chain (`ThemeIcons`) — and the screen is served with the theme's names and the address of its published sprite in its place (`Presents`, `IconSets`), inside a `wx-repeater` too; nothing is registered into the panel's own icons. On save a new name must be in the set (on the screen and in a repeater's row: `ChecksNewValues` now runs for row fields), a name the record held is let through; a field without a set checks only a short string, as before. A repeater's `itemShape()` lists the set as the field's `options` for agents.
