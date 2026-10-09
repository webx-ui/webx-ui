---
'@webx-ui/php': minor
---

New package `webx-ui/theme-default` (`"type": "webx-theme"`): the theme every new site stands on.
It gives a value to every token of the engine's vocabulary, with the presets `warm` and `night`;
a layout with `@webxTheme`, the page's head and the `header`/`footer` regions, whose fallbacks are a
header with the site name and `menu('header')` and a footer with `menu('footer')`; and the shell and
prose stylesheet, written on `--site-*` tokens only and inside `:where()`, so anything above
overrides it without a fight. `dist/theme.css` is built by Vite and committed — a site installs the
theme with no Node — and the package's test fails when `src/` changed and `dist/` did not, when a
token has no value, when a preset names a token outside the vocabulary, when body text falls below
WCAG AA in any preset, or when a stylesheet uses a literal colour or an unknown token.
