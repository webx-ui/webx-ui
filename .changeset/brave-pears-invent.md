---
'@webx-ui/core': minor
---

`WxFileCard` — one file in a media library.

A preview where there is a picture to show and a glyph where there is not, the name on one line
with the full one in a tooltip when it does not fit, and the few things that can be done to the
file: rename it, open a picture in an editor, copy its link, delete it.

Renaming opens a small panel beside the name rather than replacing it with a field: swapping a line
of text for an input changes the height of the card, and a card in a grid changes the height of its
row, so renaming one file made the whole library jump.

It does none of them. Renaming reports a name, deleting reports a wish, the edit action reports
that somebody wants an editor — nothing happens to the file until the screen holding the cards says
so. The clipboard is the exception, because copying a URL is finished the moment it happens.

The icon set gains a `file-<extension>` family — around sixty names over thirteen drawings, since a
`.docx` and an `.odt` are both a page of prose — and `crop`. An extension nobody has drawn gets the
plain page with its own name written under it, and `registerIcons({ 'file-dwg': … })` in an
application is enough for a `.dwg` to have a drawing of its own: the card asks the registry, so no
release of this library is involved.

`extensionOf`, `isPicture` and `fileIconName` are exported, since a table of files wants the same
answers.
