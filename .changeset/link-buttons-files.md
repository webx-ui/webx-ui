---
'@webx-ui/php': minor
'@webx-ui/module-admin': minor
'@webx-ui/module-media': minor
---

Links: `wx-link` takes a file of the media library — `{ "target": "file", "path": "media/…", "download": true }` — where the field says `props.files: true`; the picker gets a «File» tab with the library and «Download instead of opening». The site reads it with the file's address and `download`, the name it was uploaded under; `WebxUi\Admin\Links\LinkAttributes::of($link, $oldUrl)` prints `href`, `target="_blank"`, `rel` and `download` for any link. Every other link, the menu and every caller of the rules without `files` are unchanged.

theme-default: the buttons of `hero`, `cta`, `media-text` and `cards` are links — `button_link`, `second_link`, a card's `link` — with a page, an address, a new tab or a file to download. The old `button_url`, `second_url` and `link_url` stay with their type and are read while the link is empty, so saved blocks and theme overrides print what they printed. A site takes the new fields with `webx:blocks:import --theme --replace=<slug>`.

Blocks: `webx:blocks:import --theme` sees a field setting added empty — `"default": ""` — as a change; before, `null == ''` hid it and the type stayed `unchanged`.
