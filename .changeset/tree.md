---
'@webx-ui/core': minor
---

Tree: a structure you can see and rearrange

`WxTree` draws a tree of records the way the backend already sends them — `node-key`, `label-key`
and `children-key` name the fields, so a Laravel resource goes in without a `map` over it first.

- **A drop is three zones on a row**: the edges put the node before or after, the middle puts it
  inside — on a leaf too, which is how a leaf becomes a branch. A node can never land inside its
  own subtree; that check belongs to the tree, not to the caller.
- **`Alt` and the arrow keys move a node the same four ways a drag does**, and say so in a live
  region. It is the half most trees skip, and it is also the only way to rearrange one on a touch
  screen, where HTML drag and drop does not exist.
- **`filter` is a string, not a callback.** Matches stay, so do the branches leading to them, and
  those branches open for as long as the term stands — a match hidden behind a closed parent is the
  one thing a search must never do.
- **Checkboxes cascade both ways** unless `check-strictly` says otherwise, and a disabled node
  stays out of the cascade.
- **`lazy` fetches a branch the first time it opens**, and opens it once the children are in, so a
  spinner is never followed by an empty box that then fills.

The machinery — what is where, what is open, what a filter leaves standing, what a move does to the
arrays — lives in `useTreeNodes`, which is what a tree-shaped `WxTable` will run on rather than a
second implementation of the same thing.
