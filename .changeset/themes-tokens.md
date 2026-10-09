---
'@webx-ui/php': minor
---

`webx-ui/themes` gets the token vocabulary and `@webxTheme`. Forty tokens — colour roles, fonts,
spacing, radii, shadows, grid, motion — are `--site-<name>` on the page; a theme sets values in
`tokens.json` (`defaults`, `presets`, its own `vocabulary`) and they merge bottom up the chain,
then the preset (`webx-themes.preset`), then the owner's edits of the `editable` tokens. Every
value is checked against its type, so nothing can close the `<style>`. `@webxTheme` prints the
merged `:root` inline and each layer's stylesheet — packaged layers from `public/themes/`, put
there by the new `webx:theme:sync`, local layers through the site's Vite — and nothing without a
theme. `theme_token()` gives a merged value for mail.
