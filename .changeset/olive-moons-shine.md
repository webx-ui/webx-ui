---
'@webx-ui/core': minor
---

`WxCard` gains a `sidebar` slot: filling it splits the body into a narrow column and the main
content. The columns stack once the card itself drops below 560px — a container query, so a card
placed in a narrow column collapses even on a wide screen. Width is `--wx-card-sidebar-width`,
`240px` by default.
