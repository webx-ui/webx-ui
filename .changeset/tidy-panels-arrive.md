---
'@webx-ui/core': minor
---

Two new components: `WxDialog` and `WxDrawer` — the same panel, one in the middle of the screen and
one anchored to an edge of it.

Both are built the same way: a heading with its `extra` slot and a ×, a body, a footer for the
buttons. The heading and the footer stay where they are and only the body scrolls; a `sidebar` slot
splits that body into two columns that scroll on their own. What has no appearance — the focus
trap, the scroll lock, Escape, the portal, `aria-modal` — is Reka's dialog primitive, the same one
`WxPopover` and `WxDropdown` already sit on; everything that can be seen is ours and is styled
through tokens alone.

Size is given in pixels or per cent: `width` and `height` for the dialog, `size` for the drawer,
where it means the width on the left and right and the height at the top and bottom. `side` picks
the edge the drawer slides in from.

A dialog can be `draggable` by its heading and `resizable` from the grip in its bottom-right corner;
a drawer is resized by the edge it faces the page with, which is a real `separator` — it takes focus
and answers the arrow keys, so the panel can be resized without a pointer. `persist` gives either
one a key in `localStorage` and the panel opens at the size and position it was last left at;
`reset()` puts it back and forgets. Both gestures are pointer-only, since a finger dragging a
heading is a finger not scrolling.

A dialog with more in it than fits on the screen picks with `scroll`: `body` keeps the panel inside
the screen and scrolls what is between the heading and the footer, `panel` lets it grow as tall as
its content and scrolls the whole of it — the heading goes with it, and `sticky-footer` decides
whether the buttons rest against the bottom of the screen or sit at the end of the content. A panel
of that kind has nowhere to be dragged to and nothing to be stretched into, so `draggable` and
`resizable` are ignored while it is in use.

Under 640px the dialog takes the width of the screen and the drawer covers it whichever edge it came
from, the paddings of the heading, body and footer tighten so more of the content fits, a remembered
size and position are ignored rather than opening the panel half off the screen, and a sidebar
stacks above the body instead of standing beside it. The paddings are custom properties —
`--wx-dialog-pad-x`, `--wx-dialog-pad-y`, `--wx-dialog-body-pad` and their `--wx-drawer-` twins — so
a panel can set its own.
