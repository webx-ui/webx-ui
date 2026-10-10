# webx-ui/module-banners

Banners kept in **named places** the way menus are: a picture (and one for phones), a video, a
title, a text and up to three buttons. A site template asks for a place — `banners('hero')` —
and prints the cards in the site's own design. The module has **no public route, page, block or
view**: what reaches the site is data. The section «Banners» of the panel and the MCP tools
`banners_*` edit it. Pictures and videos are `webx-ui/module-media`, button links the panel's
link picker in `webx-ui/module-admin`, the languages `webx-ui/localization` — read their guides
for those.

## What it owns

- **Tables** `banner_places` and `banners` (`WebxUi\Banners\Models\Place`, `Banner`). A banner is
  off when created (`enabled`); words are translatable, media are not.
- **Places**: those declared in `config('webx-banners.places')` (`hero`, `promo`, `notice` by
  default) exist from day one and cannot be renamed or deleted in the panel; an administrator adds and
  deletes places of their own. An unknown place is an empty list, never an exception. A banner
  needs a picture, except in a declared place with `'image' => false` — `notice`, the words of the
  announcement bar `<x-webx-notice-bar>` of `webx-ui/widgets` prints (title, text, buttons).
- **Helpers** `banners('hero')->get()`, `->first()`, `->take(3)`, `->only([...])`,
  `->except($banner)`, `->locale('uk')` — the cards; `banners_layout('hero')` — the layout
  (`single`, `random`, `slider`) merged with `webx-banners.options` and the place's `options`.
- **Shown in a language** only when the banner's title or text is written in it (a banner with no
  words at all is shown everywhere); a button to a page that is not on the site is left out.
- **Button variants** `config('webx-banners.variants')` — `primary`, `secondary`, `link`.
- **Panel screen** `banners.form`; API under `/api/cms/banners`; permissions `banners.view`,
  `banners.manage`.
- **MCP** tools `banners_places`, `banners_place_create`, `banners_place_delete`, `banners_list`,
  `banners_get`, `banners_create`, `banners_update`, `banners_delete`, `banners_reorder`.
  Resource `banners://catalog`. Scopes `banners:read`, `banners:write`.
- Also registered: demo content (`resources/demo`) — no page or template is touched.

## Change it without forking

| You want                              | Do this                                                                                                 |
| ------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| Banners somewhere on the site         | `banners('<place>')` and `banners_layout('<place>')` in that template; copy the template from the guide |
| A place the templates rely on         | declare it in `places` in `config/webx-banners.php` (`--tag=webx-banners-config`)                       |
| One place as a slider, another single | `'layout' => 'slider'` / `'single'` / `'random'` on the place; `default_layout` for the rest            |
| Another layout in one template only   | `banners_layout('hero', 'random')`                                                                      |
| Other proportions or timing           | `ratio`, `ratio_mobile`, `interval`, `breakpoint`… in `options`, or in the place's `options`            |
| Video on phones                       | `'video_on_mobile' => true` in `options`                                                                |
| Another look of a button              | add a key to `variants`; the template turns the key into a class                                        |
| A field of the project on a banner    | a patch: `Screens::extend('banners.form', [...])` into `project-fields`; the card has it under `fields` |
| Other words in the panel              | `php artisan vendor:publish --tag=webx-banners-lang`                                                    |

A screen patch addresses nodes by `id`: `media`, `image`, `image-mobile`, `video`, `words`,
`title`, `text`, `buttons-card`, `buttons` (rows: `button-label`, `button-link`,
`button-variant`), `settings`, `enabled`, `project-fields`.

## Do not

- Do not edit anything in `vendor/webx-ui/module-banners` or copy it into the site. The rows
  above are the supported ways; if none fits, the package is missing a seam — say so.
- Do not look for a banner block or a view to publish: there is none by design. The markup,
  styles and slider script are the site's own, in the template that calls `banners()`.
- Do not rename a declared place's key in the config while templates ask for it: the template
  then gets an empty list and the banners sit in a place nobody prints. Change the template too.
- Do not take a variant out of `variants` expecting old buttons to change: they keep their key
  and are printed with the first variant. Edit the buttons, or keep the key.
- Do not write a picture or a video as a URL: they are library keys, and an unknown one is
  refused. Upload into the library first; `image` is the one required field.
- Do not create a banner and forget `enabled`: a new one is off, so it is not on the site. Turn
  it on once it is finished.
- Do not delete a place that still has banners — it is refused with the count (the bin counts
  too). Move or delete its banners first.

## Check your work

- Open a page whose template prints the place, in each language: a banner without words in a
  language is not there, and with `random` one banner is shown per view.
- On a phone width: `image_mobile` and `ratio_mobile` below `breakpoint`, the poster instead of
  the video unless `video_on_mobile`.
- With MCP: read `banners://catalog` first, then `banners_list` for a place; every mutating tool
  takes `dry_run: true`.

## Read more

- [README.md](README.md) in this directory — the card's fields, the layout options, a template.
- Guide: https://webx-ui.github.io/webx-ui/guide/banners
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_BANNERS.md
- Extending views and services: https://webx-ui.github.io/webx-ui/guide/extending
