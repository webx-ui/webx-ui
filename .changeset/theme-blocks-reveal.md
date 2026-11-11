---
'@webx-ui/php': minor
---

Themes: the blocks of a theme can come in as the page scrolls. `"reveal": true` (or `"fade"`, `"scale"`; `false` switches off a layer below) in the theme's manifest — off unless a layer names it, so a site that never asked looks as it did; `webx:theme:show` prints it. `@webxReveal` (`ThemeReveal`) is the one place that turns a template's mark into `data-webx-reveal`, or into nothing. The six blocks of `theme-default` mark their words and their lists (cards, the picture beside its text, contacts beside the form, one after another) — never the section — and have a «Without animation» switch (`no_reveal`). What is on the screen when the page opens is never hidden; without JavaScript and with reduced motion everything is simply there. A site takes the new blocks with `webx:blocks:import --theme --replace=<slug>`.
