---
'@webx-ui/core': patch
'@webx-ui/schema': patch
'@webx-ui/module-inbox': patch
---

`WxInputNumber` in a form item is at most 240px wide everywhere, not only in screens: in a
hand-written form a number stretched across the card with its − and + a line apart. Bare — in a
filter row or a table cell — it keeps the width it is given. The screens' own cap and the field
dialog's are gone, the core's covers both.
