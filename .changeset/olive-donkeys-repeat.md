---
'@webx-ui/core': patch
---

Three fixes from the same round, read on a phone again.

- The overflow branch of a horizontal `WxMenu` rendered an empty list in a production build. Vnodes
  handed along as a prop skip the cloning Vue does for a slot rendered in a template, so an entry
  moving from the bar to the branch was being asked to mount twice — and in production a static
  entry is cached and handed back as the very same object. `WxNodes` now clones what it renders,
  which covers every split slot at once. It never showed in a development build.
- The splitting bar is `overflow-x: clip` rather than `hidden`. A box with `hidden` is still a
  scroll container, and the browser scrolls one to reveal a focused button inside it — which, in
  the frame where everything is back in the bar to be measured, left the bar pushed sideways.
- A tap in `WxSelectionArea` adds and removes rather than replacing, the way ctrl-click does. With
  no modifier and no box, a tap that replaced the selection meant a touch screen could never hold
  more than one item in it.
