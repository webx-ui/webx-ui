---
'@webx-ui/core': minor
---

TreeSelect: the tree as a form field

`WxTreeSelect` is a trigger that reads like every other control and a panel holding
[`WxTree`](/components/tree). It is the "parent category" field, and with `multiple` the set of
sections a record belongs to.

- **The model is a key, not a node** — `parent_id` is what a form sends. The node comes with the
  `change` event, since an id alone is rarely what the screen needs to show.
- **`multiple` puts a checkbox on every node** and leaves the panel open, because a set is rarely
  finished after one tick. `check-strictly` keeps a tick where it was made, for "exactly these
  three" rather than "everything under Brakes".
- **Opening the panel reveals what is already chosen**: the branches leading to it open, so a tree
  of five hundred nodes does not open on its first page with the answer somewhere below.
- **`show-path` spells out which node it is** — two categories called "Seals" under two different
  parents are otherwise one field showing "Seals" twice.
- `filterable`, `lazy` with `load`, `clearable`, sizes, statuses, and the hidden input a plain
  `<form>` post needs.

`useTreeNodes` now takes the tree as a getter as well as a `ref`: the field reads a prop it never
rearranges, and reuses the same index the tree keeps for finding a node by key and the path to it.
