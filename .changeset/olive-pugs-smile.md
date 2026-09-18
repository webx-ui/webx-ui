---
'@webx-ui/module-blocks': patch
---

Block editor styles name tokens that exist: the muted and strong borders and the warning and
danger colours were spelled `--wx-color-border-muted`, `--wx-color-border-strong`,
`--wx-color-warning-text` and `--wx-color-danger-text`, and only the fallback kept them drawn.
