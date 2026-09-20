---
'@webx-ui/module-blocks': minor
---

The constructor gives the page back its room

Two columns now, in both states, and the tree and the open block's form take turns in the narrow
one. It used to be three — tree, form, and a 420 px preview — and a desktop page does not go in
420 px, so the preview was squeezed into a phone at one to one and every block was edited against
a picture of a phone. Measured on a 1474 px window: 260 + 626 + 420 became 455 + 867, which is a
1280 px page at 0.66 rather than a 390 px one.

The preview itself is now as tall as the page inside it, and the panel scrolls. There is no window
of ours over a page that has its own — that was two scrollbars for one document, and the inner one
could not be reached with the wheel, so a block taller than the window could not be seen whole at
all. The height comes from a `ResizeObserver` inside the frame, measured off the body.

Its bar carries the width switcher at all times — desktop, tablet, phone, as icons — and the two
buttons beside it are icons as well. The bar sticks to the top of the window while the page goes by
under it. Selecting a block brings it into view by as little as it takes, never centring it, and
leaves it alone when it is already there; the panel is what moves, and the page inside the frame
never does.

On a panel too narrow for two columns there is one, and it is the preview: the tree and the form
move into a sheet over the page, opened by a pencil on the preview's bar or by clicking a block in
the page itself — which opens it straight onto that block's form. The preview starts as a phone
there, or as a tablet where there is room for one. That replaces the old arrangement, where the
preview was the thing that folded away on a narrow screen and the editor was left with a list of
names.

What the tree carried while it is off the screen lives in the form's head: steps to the previous and
the next block through the flattened page, the trail of containers the open block sits in, and the
button that opens the preview full screen — that one matters below the width where the preview folds
away, which is exactly where the tree's own foot is not on screen.

Breaking, in the small way a `0.x` field can be: the `fill` prop is gone from `WxBlocks` and from
the preview. It meant "be a window tall and scroll each panel inside itself", which is the layout
this release replaces. A screen that passed it can simply stop. The tile with the block type's icon
is gone from the tree rows too — most types have no icon of their own, so it drew the same square on
every row and spent the width of a name on saying nothing.
