---
'@webx-ui/php': minor
---

The slider of `webx-ui/widgets`: `<x-webx-slider variant="cards|hero|gallery|logos" :per-view>` with `<x-webx-slide>` inside — Swiper built into the package's own `dist/slider.js|css`, loaded only on a page that has a slider. Slides per view follow the width of the slider's container, not the window's; without JavaScript it is a strip that scrolls and snaps; whatever turns on its own has a pause button and stays still with reduced motion; only the first slides in view load their pictures at once. Every setting is a prop over the variant, `options` passes the rest to Swiper.

The blocks demo seeds the types a theme brings in `demo/blocks/` (the theme's copy of one of the three wins), and `theme-default`'s kitchen sink has a Slider page with every variant at full width and in a narrow column.
