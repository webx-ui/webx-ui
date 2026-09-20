---
'@webx-ui/module-blog': minor
'@webx-ui/php': minor
---

Rubrics are one list and a dialog over it

The section used to be a list beside a form, and the form took two thirds of a screen whose
whole job is the drag: the order of this list is the order of the menu on the site. Now the list
is the screen — grip, name, address, the number of articles, and a `···` with `Edit`, `Show its
articles` and `Delete` — and a rubric is edited in a dialog with three tabs: `Content` (the
name, the address, the switch and the introduction), `Image` and `SEO`. One `Save` for all
three, and a `422` opens the tab the failing field is on.

The introduction is a rich text document now (`wx-rich-text`) rather than a line of plain text:
cleaned by its own field type on the way in, printed with its library pictures resolved on the
way out. Nothing migrates — the column is the same one, and a line of text is a document with no
markup in it.

Two things this fixes on the way: the SEO card used to open at zero width inside the old form,
and the footer of that form broke apart onto three rows on a one-pixel overflow.
