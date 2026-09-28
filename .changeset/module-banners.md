---
'@webx-ui/php': minor
---

`webx-ui/module-banners`: banners in named places, the way menus are — a picture and one for
phones, a video with the picture as its poster, a title, a text and up to three buttons (a
translated label, a `wx-link` and a look from a list the site configures). The configuration
declares the places the templates ask for; an administrator adds their own. A banner with words is
seen only in the languages they are written in. No page, no block and no view: a template of the
site prints `banners('hero')` and lays it out with `banners_layout('hero')` — single, random or
slider, with the options of the config. `webx:setup` knows the module as `banners`, and
`webx:doctor` checks `banners()` and `banners_layout()`.

To an agent it is nine tools — `banners_places`, `banners_place_create`, `banners_place_delete`,
`banners_list`, `banners_get`, `banners_create`, `banners_update`, `banners_delete`,
`banners_reorder` — and `banners://catalog`, through the panel's own form. `webx:demo` fills `hero`
and `promo` on the library's demo pictures.
