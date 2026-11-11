---
'@webx-ui/php': minor
---

Themes: an accent in a heading. Words between two equals signs — `Homes ==by the river==` — print as `<span class="site-accent">` through the new directive `@webxAccent($heading)` (`ThemeAccent`, with `ThemeAccent::plain()` for where a span cannot go); the heading stays one `<h1>`/`<h2>`, each language places the accent where its word order wants, and a heading without a pair of marks prints exactly as before. The six blocks of `theme-default` (`hero`, `text`, `media-text`, `cards`, `cta`, `contact`) print their headings through it, with a hint on the field; `theme-default` colours `.site-accent` in `color-accent`, and on the accent or over a photo the words keep their colour and lean. `media-text` gets an optional `logo` — a small picture above the heading, at most 4rem × 12rem, never stretched, its alt from the media library. A site takes both with `webx:blocks:import --theme --replace=<slug>`.
