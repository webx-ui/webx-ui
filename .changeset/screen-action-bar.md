---
'@webx-ui/module-settings': minor
'@webx-ui/module-blocks': minor
'@webx-ui/module-pages': minor
'@webx-ui/module-admin': minor
'@webx-ui/core': minor
---

The screen's own bar along the bottom, and a preview that fills the height it was given.

A screen's buttons live in its head, and since the page started scrolling natively the head goes
with it — so a form long enough to need saving is a form whose save button is off the top of the
window by the time it is needed. `WxActionBar` is that button brought back: the state of the work
on the left, what can be done about it on the right.

It is part of the screen rather than of the shell — a list has none, a form has one — and it is
the last row of the screen rather than a layer over it. That is why it never covers anything: at
the end of a page it is the last thing on it, and above that it sticks to the bottom of the window
without taking the room it would need to be there. `--wx-action-bar-bottom` is how far off the
edge it stops, and the strip below it is painted over, or the page scrolling past would show
through the gap.

`WxMain` gives a screen that holds one a floor of `--wx-fill-height`, so a form of one field still
has its bar along the bottom of the window rather than halfway up the page.

In the panel: the settings screen and the block type editor duplicate the buttons from their
heads, and the page editor — which is exactly as tall as the window, so nothing ever scrolls away
— moves `Save` and `Publish` into the bar instead of repeating them, leaving the head the links
out to the site.

The page preview inside the block constructor now fills the card it stands in. A scaled iframe
keeps its layout height, so a page 1280px wide drawn in a 420px column used to paint a third of
the card and leave the rest empty for the card to scroll; the frame is now divided by its own
scale, and what scrolls inside it is the site. On a form that scrolls, the tree and the field
panel are sticky with their own scrollbars, the way the preview beside them already was.
