---
'@webx-ui/php': minor
---

theme-default: `hero` has a `reveal` field («Comes in»: «As one», the default, or «One after another»), shown while «Without animation» is off. One after another, the words come in in turn — eyebrow, headline, the line under it, the buttons, the badge (`@webxReveal('stagger')` on `b-hero__inner`) — where the theme brings its blocks in on scroll (`"reveal"` in the manifest, THEMES §9.3). A hero saved before the field prints byte for byte what it printed. Sites take the field by re-importing the type: `php artisan webx:blocks:import hero --replace=hero --publish`.
