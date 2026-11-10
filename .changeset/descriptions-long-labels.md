---
'@webx-ui/core': patch
'@webx-ui/module-orders': patch
---

`WxDescriptions`: a label sizes to its content up to half its column and wraps past it, so a long label no longer takes a narrow card and leaves its value no room; `labelWidth` still overrides. The order fields card drops its own `fit-content(50%)`, now the default.
