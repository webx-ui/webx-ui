---
'@webx-ui/php': minor
---

Themes: a dark scheme for the visitor. A layer's `tokens.json` takes `"schemes": { "dark": { … } }` — the same tokens, merged up the chain like `defaults`. With it `@webxTheme` prints the dark values under `prefers-color-scheme: dark` (unless `data-theme="light"` is on `<html>`) and under `data-theme="dark"`, with `color-scheme`; without it the output is unchanged. The theme's test (`ThemeContract`) measures the contrast of rule 29 in the dark scheme too, `webx:theme:check` says it as notes, notes a light token recoloured over a dark value left from a layer below, and refuses `@media (prefers-color-scheme)` in a theme's CSS; `webx:theme:show` gives each token its dark value and its layer. Letters stay light. The switch is the site's: the guide shows a button and the early script that sets `data-theme` before the first paint.
