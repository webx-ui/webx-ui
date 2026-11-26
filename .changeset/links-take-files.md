---
'@webx-ui/module-admin': minor
'@webx-ui/module-media': minor
'@webx-ui/module-menu': minor
'@webx-ui/module-blocks': patch
'@webx-ui/module-configurator': patch
'@webx-ui/php': minor
---

Every link may be a file of the media library, and the file field looks like the link field.

- **A file on any link.** `files` is now on by default — on `WxLinkField`, `WxLinkPicker` and `LinkType::rules()` alike — so every button, menu item, banner and form link offers «A file» next to «This site», «An address» and «Nowhere». A node that has to be a page says `props.files: false`: the configurator's page and the region preview do. This changes what the server accepts: `target: "file"` is taken by every `wx-link` and by every caller of the rules with no node, `menu_add_link`/`menu_update_link` included.
- **Menu items keep a file.** `menu_items` gets `path` and `download` (migration `2026_11_26_000000_add_file_to_menu_items`, reversible). The site prints the library's address with `download="<name>"` when ticked, and calls an item with no label of its own by the file's name; a file gone from the library takes the item out of the menu, like a draft page, and deleting a library file forgets the built menus. `MenuLink` has `download`; the MCP tools take `target: file` with `path` and `download` and refuse a key the library does not have.
- **The redirect and the thank-you button of a form keep a file** — they used to drop the key on the way to the site.
- **`wx-file` is drawn as a row and a panel**, the way a link is, on the new shared `WxFieldRow` of `module-admin`: the glyph (a thumbnail for a picture), the upload name and «PDF · 61 kB», a clear button beside it; the panel chooses from the library, uploads straight into it, edits the caption (`alt` only with `accept: image`), and with `props.download: true` offers «Download instead of opening» — the site then reads `download` as the upload name. The stored value is unchanged, so every saved value opens as it is. A link to a file now says the file's name and size on its row too (`PickedFile` has `size`).
