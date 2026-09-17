---
'@webx-ui/core': minor
'@webx-ui/module-admin': patch
'@webx-ui/module-blocks': patch
'@webx-ui/module-media': patch
'@webx-ui/module-pages': patch
'@webx-ui/module-seo': patch
'@webx-ui/module-settings': patch
---

One step for the whole panel: cards, grids and forms read `--wx-gap`

The panel's spacing step — 8 on a phone, 12 on a tablet, 16 on a desktop — used to space the
frame alone. It now spaces everything: the air inside a card and between the things in it, the
gap between the fields of a form, the gutter of a grid, the space between the strip of tabs and
what it switches. Where there is no panel around them, the components fall back to 16, which is
what they had.

Two things change on their own account. A form's `gap="md"` is 16 rather than 24, so a form laid
out by a card and a form laid out by itself finally agree. And a tab is now a column that spaces
what it holds — two cards in a tab used to stand flush and read as one.
