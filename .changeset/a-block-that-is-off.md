---
'@webx-ui/module-blocks': minor
'@webx-ui/php': minor
---

A block can be switched off

A block on a page gets a switch: it stays in the content, it is edited in the panel exactly as
before, and the site does not draw it. Not a page draft, not a delete, and not the block type's
`is_enabled` — a switch on one block.

The eye stands first in the row of the tree, before duplicate and remove. A switched-off block
is dimmed and carries an `eye-off` beside its name, always and not only under the cursor: the
actions are invisible at rest, and a row that is merely dimmer than its neighbours says nothing
on its own. It is absent from the preview too — a preview that still showed it would not tell
you which block is the one that is off.

In the content it is a fourth key on the node, `hidden: true`, written only when it is on: every
tree from before the switch existed reads as visible, with no migration. The skip is one line in
`Renderer::list()`, which is why a switched-off container takes everything inside it with it
while their own switches keep their values — the recursion never reaches them — and why the
type's styles and script stay off the page.

`blocks_edit_content` gets `hide` and `show` by key, and `outline` reports `hidden: true`: an
agent has no other way of telling that a block it can read is not on the site.
