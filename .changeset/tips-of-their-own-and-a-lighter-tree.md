---
'@webx-ui/core': minor
'@webx-ui/module-media': patch
---

Icon buttons say what they are, and the tree gives its width back

`WxAction` draws its own tooltip. `title` is now what the tip says rather than what the browser
draws: one shape across a panel, a delay of our own, and a side that can be turned away from the
edge of a dialog with the new `tooltipSide`. The accessible name is unchanged — `aria-label`
carries `label`, then `title`, then the English name of the type — but the `title` attribute is
gone from the markup, so an action is found by its name and no longer by `[title=…]`.

A greyed action keeps its tip, which is when an icon needs one most. That needed the other half
of the change: `disabled` puts `aria-disabled` and `tabindex="-1"` on the control instead of the
attribute, because a disabled button receives no pointer events at all and nothing would ever
open. Clicks and keys are turned away as before, and the control stays out of the tab order.

The tooltip brings a portal beside the control, so an action is no longer a single root node.
Templates do not notice; a test does — reach for the `button` inside the wrapper rather than for
the wrapper itself.

`WxTree` gives back the width it was spending on nothing. Two pixels between branches, so a list
of names stops reading as one block. The level step is 16 rather than 21, and `indent` now
counts the guide line inside itself, so a lined tree and a plain one indent by the same amount.
The drag handle leaves the flow: it sits in the row's left margin and appears on hover, instead
of holding 20 px of every row at every level. From the edge to the text: 77 px to 60 at the
first level, 127 to 100 at the fourth. A leaf keeps the room where a chevron would be, so the
names of leaves and branches still line up.

New icon: `folder-move`, a folder with an arrow going down into it.

The media library uses both. The button that shows the folder tree on a narrow screen is now
`sidebar` — the same icon the shell shows and hides its own column with — and the one that moves
files into a folder is `folder-move`. They were both drawn as `folder`, in the same row.
