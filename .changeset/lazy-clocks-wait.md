---
'@webx-ui/core': patch
---

`WxCountdown` no longer starts its timer during server-side rendering. The interval
had nothing to clear it there — `onBeforeUnmount` never runs on the server — so it
held the render process open: a static build of a page carrying a countdown finished
rendering and then hung. The clock now starts in `onMounted`, the one hook a server
render never reaches, while the displayed value is still computed in both places.
