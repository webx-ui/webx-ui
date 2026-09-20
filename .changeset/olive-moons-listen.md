---
'@webx-ui/core': patch
---

A filterable `WxSelect` no longer takes the focus off whoever was reading

Reka's `autoFocus` on the filter box fires once, on mount. Inside an open list that is the
right moment; on a form it is not, because there the filter _is_ the field — so nothing anybody
had just opened was ever focused, and instead the focus went to whichever select was drawn
last. A tab holding four of them scrolled itself to the bottom the moment it appeared, and the
caret ended up in a field nobody had clicked.

The caret now goes into the filter when the field is opened, which is what the flag was there
for: clicking the box or its arrow puts it where the typing goes, and drawing the field puts it
nowhere.
