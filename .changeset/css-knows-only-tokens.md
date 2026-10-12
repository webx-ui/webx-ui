---
'@webx-ui/php': minor
'@webx-ui/module-blocks': minor
---

CSS knows only tokens (THEMES §8, TH2.1). `webx-ui/themes`: **`Checks\CssCheck::check($css, $vocabulary)`** finds a literal colour (hex, the colour functions, a named colour — `transparent`, `currentColor` and the CSS-wide keywords are allowed), a literal font family in `font-family`/`font` (generic families allowed), a `var(--site-…)` the vocabulary does not have, a fallback inside `var(--site-…)` and a width `@media`, each with its line. **`php artisan webx:theme:check [path]`** runs it over the chain — the `tokens.json` of every layer (names in the vocabulary, values of their type), `src/css` of the layers, the CSS of the widgets and of the modules' `resources/theme` — printing a line per finding and exiting non-zero. `webx-ui/widgets` and `webx-ui/theme-default` test their stylesheets with it. `webx-ui/module-blocks`: on a site with a theme, saving a block type warns of the same (`literal-colors`, `literal-fonts`, `unknown-tokens`, `token-fallbacks`, in ten languages); the type's answer carries the theme's `tokens`, and the editor says it live (`tokenLints()`). Without a theme nothing changes.
