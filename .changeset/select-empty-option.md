---
'@webx-ui/core': patch
---

`WxSelect` takes an option worth `''` — the "All" of a filter, the "Any event" of a dialog. Reka
refuses such an item and threw while drawing the list, so the list opened empty, and inside a modal
opened from code the error left the dialog on screen and dead to every click. The field now shows
that option's label instead of the placeholder and hands back `''` when it is picked.

`openModal` takes a modal whose component throws away once the render is over and prints the error,
instead of tearing the tree down mid-render and leaving a dead panel behind.
