---
'@webx-ui/core': minor
---

`WxSelectionArea`: a click picks a card again, and it can be told to hold only one.

- A plain mouse click cleared the selection instead of picking the card under it. The area captures
  the pointer, and every event after that is retargeted to the element holding the capture — so the
  release reported the area itself, which read as a click on the background. The gesture is now
  read off where it began, which is also what a click is: a press and a release on the same thing.
- New `multiple` prop, `true` by default. Off, the model never holds more than one value: there is
  no box, no run and no toggle, and a click or a tap picks the item under it. A gallery wants
  several; a file picker wants one.
