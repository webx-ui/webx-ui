---
'@webx-ui/core': patch
'@webx-ui/schema': patch
'@webx-ui/module-admin': patch
'@webx-ui/module-banners': patch
'@webx-ui/module-faq': patch
'@webx-ui/module-press': patch
'@webx-ui/module-reviews': patch
'@webx-ui/module-tariffs': patch
'@webx-ui/module-team': patch
---

Tails from the last five modules. `WxInputNumber` with `precision` shows a whole number bare
(`1380`, not `1380.00`) and a fraction to the precision (`1380.50`); the model is untouched. A
refusal of a repeater row (`duties.1.text.ru`) is drawn under that row only, no longer a second
time under the repeater as a whole. Ctrl+S in the editors saves also when a click on empty space
left the focus on `<body>` — `useBodyKeys` in `module-admin`.
