---
'@webx-ui/core': patch
'@webx-ui/module-blog': patch
'@webx-ui/module-pages': patch
'@webx-ui/module-inbox': patch
---

A cell keeps what it holds inside its own column. `table-layout: fixed` gives a column the width
it was declared and nothing else, so a value wider than that used to be painted straight across
the column beside it — measured on the panel, a date cell 130px wide with 152px of text, its tail
sitting under the status badge. Cells clip now.

`TableColumn.minWidth` says what it can and cannot do: a `<col>` takes four properties and
`min-width` is not one of them, so the floor only means something with `layout="auto"`.

The lists that showed it — articles, pages, tags and submissions — carry the widths their longest
values actually need, and the columns that can be spared step aside a little later so that the
name keeps the room.
