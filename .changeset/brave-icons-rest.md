---
'@webx-ui/core': patch
---

A field the browser has filled in keeps the panel's own colours. Browsers paint an autofilled
input in a colour that belongs to no theme — yellow in a light panel, olive in a dark one — and
set it with `!important`, so `WxInput` covers it with an inset shadow instead of trying to
override it.
