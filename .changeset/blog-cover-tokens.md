---
'@webx-ui/module-blog': patch
---

The empty cover placeholder in the article list draws its dashed border and surface background again: it referred to `--wx-border-color` and `--wx-bg-base`, which are not tokens, so both resolved to nothing.
