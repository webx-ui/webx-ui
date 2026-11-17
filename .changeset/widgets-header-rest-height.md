---
'@webx-ui/php': patch
---

widgets, theme-default: a header a theme makes lower once scrolled no longer moves the page. The runtime keeps `--webx-header-rest-height` (the header not scrolled) next to `--webx-header-height`; an in-flow header keeps the room of its rest height, and the first screen `hero` (`--hero-under`, `--hero-above`) lays out by it, so the picture of its `screen` parallax and its words go on at an even pace across the switch. An overlay header's margin follows its height before the frame is painted. Re-import the `hero` type on a site: `php artisan webx:blocks:import hero --theme --replace=hero --publish`; a theme that gives the room back with its own margin under a header that gets lower can drop it.
