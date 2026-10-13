---
'@webx-ui/php': minor
'@webx-ui/module-banners': minor
---

Banners get layers. A place declared with `'layers' => true` (`hero` is) lets its banners carry texts, pictures and buttons placed on the picture by hand, every length a percent of the picture's width, so a banner scales as a whole. `webx-ui/module-banners`: a `layers` column, its checks under `layers.<n>.<field>`, two layouts (computer and phone, switched at the place's breakpoint), `{title}` and `{text}` put in from the banner's fields, a layer's own words falling back to the default language, entrances and loops, `'overflow' => true` for a layer that steps out of the frame; `<x-webx-banners place="…">` prints a place whole — the widgets' slider under a theme, a strip of its own without one — and `<x-webx-banner>` one banner; `GET banners/theme`, `layers` in `banners_create`/`banners_update`, the resource `banners://layers`; the demo lays out `hero`. `@webx-ui/module-banners`: the «Layers» card of `banners.form` (`wx-banner-layers`) — the canvas with the frame's crop shaded, drag, handles, arrows, undo, snapping, the phone's layout, the theme's fonts and palette. Spec: `docs/architecture/WEBX_UI_BANNER_LAYERS.md`.
