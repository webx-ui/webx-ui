---
'@webx-ui/core': minor
---

`WxIconPicker`: the icon set, picked from rather than typed into

Anywhere an editor names an icon — a block type, a section of a menu — the name went into a text
box, and a name the set does not have draws nothing at all. No warning, no placeholder: the label
beside it quietly moves to where the picture should have been, and the interface says nothing.
That is how `file-text` sat in a menu for a month where the set has `file-txt`.

The field shows the icon it holds and opens the whole set beneath it, four across and scrolled to
the one already chosen. The box is the search: typing filters, clicking picks, and the value only
ever becomes a name the set has — typing one in full and pressing Enter counts as picking it,
since by then it is the only match left. `iconNames()` is the list, so icons a site registered
itself are offered too.

See [IconPicker](/components/icon-picker).
