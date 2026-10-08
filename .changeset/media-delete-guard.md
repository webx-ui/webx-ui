---
'@webx-ui/core': minor
'@webx-ui/module-admin': minor
'@webx-ui/module-media': minor
'@webx-ui/module-pages': patch
'@webx-ui/php': minor
---

Deleting from Files keeps the rule `media_delete_files` keeps for an agent. Before a file, a
selection or a folder goes, the panel asks the server which of the files the site still uses and
shows where — by the row's title where it has one — in one question with «Delete anyway»; a folder
says what is inside through its whole subtree, with proper plurals («3 файла и 1 папка»). The
delete endpoints refuse a used file without `force` (409 `files_in_use`); `POST files/usage` and
`GET directories/{id}/contents` answer the question first.

The previews of a file go with it (single, batch and folder delete). Previews left by files deleted
earlier are found by the audit's `media.orphan_thumbs` and swept by its fix or by
`php artisan webx:media:prune-thumbs`.

«Move to…» shows the folder tree with the current folder marked; tiles (or the selection) can be
dragged onto a folder of the tree; a move says where the files went, with Undo. Sizes are written
in the panel's language («3,5 КБ»), and so is the upload limit.

In the design system: `WxActions` and `WxFileCard` take `moreLabel` (and the card `actionsLabel`);
`WxSelectionArea` takes `dragItems` and keeps a ctrl or shift held at the press; `WxTooltip` no
longer opens on the focus a closing dialog hands back. `pluralForm` moves to `module-admin`.
