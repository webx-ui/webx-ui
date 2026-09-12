<script setup>
import TreeDemo from '../components/demos/TreeDemo.vue'
</script>

# Tree

`WxTree` is a structure you can see and rearrange: categories, pages, a menu, the sections a role
is allowed into. Nodes open and close, a filter cuts the tree down to what matches, checkboxes
follow the branch they sit on, and a branch can be dragged — or moved with the keyboard, which is
the half most trees forget.

<TreeDemo />

## Usage

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { WxTree } from '@webx-ui/core'

const categories = ref([
  { id: 1, label: 'Engine', children: [{ id: 11, label: 'Pistons' }] },
  { id: 2, label: 'Brakes' },
])

const expanded = ref([1])
const selected = ref<number | null>(null)
</script>

<template>
  <wx-tree v-model="categories" v-model:expanded="expanded" v-model:selected="selected" />
</template>
```

`v-model` is the tree itself. A move rewrites it before `drop` is raised, so the array always says
what the screen says — and saving is a walk over it, not a diff against what it used to be.

## The records you already have

A tree of `{ id, label, children }` needs no props. Anything else is four prop names away, so a
Laravel resource goes in as it arrives:

```vue
<wx-tree v-model="pages" node-key="id" label-key="title" children-key="descendants" />
```

`disabledKey` and `leafKey` name the other two fields the tree reads. A node without a value under
`nodeKey` falls back to its position in the tree — enough to draw and to open, too weak to survive
a move, so name a key whenever nodes can be dragged.

## Opening and closing

`v-model:expanded` is the list of open keys, which makes "remember where the user was" a matter of
storing an array. `default-expand-all` opens everything once, on the first render; `accordion`
keeps one branch open per level.

By default a click selects and only the chevron opens. `expand-on-click` makes the whole row do
both — right for a navigation tree, wrong for one where selection is the point.

## Checkboxes

`checkable` adds them, and a tick runs both ways: down into the children, and up into the parent,
which becomes checked when all of its children are and half-checked when only some are. A disabled
node is left out of the cascade — it is disabled, and a parent's tick is not a way around that.

`check-strictly` turns the cascade off, for the tree where a branch means something of its own
rather than the sum of what is under it.

```vue
<wx-tree v-model="sections" v-model:checked="granted" checkable />
```

## Moving a branch

`draggable` turns every row into something to pick up. Which third of a row the pointer is over
decides where the node lands: the edges put it before or after, the middle puts it inside — and
inside works on a leaf too, which is how a leaf becomes a branch.

```vue
<wx-tree
  v-model="pages"
  draggable
  :allow-drop="(drag, drop, zone) => !(zone === 'inside' && drop.locked)"
  @drop="save"
/>
```

A node can never land inside its own subtree; that check is the tree's, not yours. `allowDrag` and
`allowDrop` are for the rules above it — a locked branch, a depth limit, a page that has to stay at
the top level.

`drop` carries where the node ended up, which is all a backend needs:

```ts
function save(event: TreeDropEvent) {
  return api.patch(`/pages/${event.node.id}/move`, {
    parent_id: event.parent?.id ?? null,
    position: event.index,
  })
}
```

### Without a mouse

Tab into the tree and one row has focus; arrows walk, `→` opens, `←` closes or goes up to the
parent, `Enter` selects, `Space` ticks. Hold `Alt` and the same four arrows **move** the node:

| Keys          | What it does                        |
| ------------- | ----------------------------------- |
| `Alt` + `↑ ↓` | Up or down among its siblings       |
| `Alt` + `→`   | Inside the node above it            |
| `Alt` + `←`   | Out of its branch, after its parent |

Every move is announced in a live region. This is also the path that works where HTML drag and drop
does not — a touch screen — so a tree that can be rearranged is a tree everyone can rearrange. For
a move across a long distance, neither a drag nor an arrow key is the answer: give the row a
**Move** action and open a picker with [`openModal`](/guide/modals).

## Filtering

`filter` is a string, not a callback. Nodes whose label contains it stay, so do the branches
leading to them, and those branches are opened for as long as the term stands — a match hidden
behind a closed parent is the one thing a search must never do. The matched part of a label is
marked.

```vue
<wx-input v-model="term" placeholder="Filter" />
<wx-tree v-model="pages" :filter="term" />
```

## Branches that arrive late

A catalogue of five thousand categories is not a payload, it is a series of them. `lazy` fetches
the children of a branch the first time it opens:

```vue
<wx-tree v-model="regions" lazy :load="(node) => api.get(`/regions/${node.id}/children`)" />
```

The branch opens once the children are in, so a spinner is never followed by an empty box that then
fills. A node that has nothing to fetch says so with `leaf: true` — without it, an empty `children`
cannot tell "not loaded yet" from "nothing there".

## Props

| Prop               | Type                            | Default              | Description                                    |
| ------------------ | ------------------------------- | -------------------- | ---------------------------------------------- |
| `modelValue`       | `T[]`                           | `[]`                 | The tree itself                                |
| `expanded`         | `(string \| number)[]`          | `[]`                 | Keys of the open branches                      |
| `selected`         | `string \| number \| null`      | `null`               | Key of the selected node                       |
| `checked`          | `(string \| number)[]`          | `[]`                 | Keys of the ticked nodes                       |
| `nodeKey`          | `string`                        | `'id'`               | Field holding the identity                     |
| `labelKey`         | `string`                        | `'label'`            | Field holding the text                         |
| `childrenKey`      | `string`                        | `'children'`         | Field holding the children                     |
| `disabledKey`      | `string`                        | `'disabled'`         | Field that makes a node inert                  |
| `leafKey`          | `string`                        | `'leaf'`             | Field saying there is nothing to fetch         |
| `defaultExpandAll` | `boolean`                       | `false`              | Open every branch once                         |
| `accordion`        | `boolean`                       | `false`              | One open branch per level                      |
| `expandOnClick`    | `boolean`                       | `false`              | A click opens as well as selects               |
| `checkable`        | `boolean`                       | `false`              | A checkbox per node                            |
| `checkStrictly`    | `boolean`                       | `false`              | A tick stays where it was made                 |
| `draggable`        | `boolean`                       | `false`              | Rows can be picked up                          |
| `allowDrag`        | `(node) => boolean`             | —                    | Which nodes can be picked up                   |
| `allowDrop`        | `(drag, drop, zone) => boolean` | —                    | Which landings are allowed                     |
| `lazy`             | `boolean`                       | `false`              | Children arrive when a branch opens            |
| `load`             | `(node) => T[] \| Promise<T[]>` | —                    | Fetches one branch                             |
| `filter`           | `string`                        | —                    | Shows matches and the branches leading to them |
| `showLines`        | `boolean`                       | `true`               | Guide lines down the indentation               |
| `indent`           | `number`                        | `20`                 | Pixels per level                               |
| `size`             | `'sm' \| 'md'`                  | `'md'`               | Row height and text size                       |
| `emptyText`        | `string`                        | `'Nothing here yet'` | Shown when there is nothing to draw            |
| `dragLabel`        | `string`                        | `'Move'`             | What the grip is called                        |
| `ariaLabel`        | `string`                        | —                    | Accessible name for the tree                   |

**Events:** `update:modelValue`, `update:expanded`, `update:selected`, `update:checked`;
`node-click` (`node, event`); `select` (`node, key`); `check` (`keys, { node, checked }`);
`expand` / `collapse` (`node`); `drop` (`TreeDropEvent`).

**TreeDropEvent:** `{ node, target, zone, parent, index, via }` — `zone` is `'before' | 'after' |
'inside'`, `parent` is `null` at the top level, `via` is `'pointer'` or `'keyboard'`.

**Slots:** `default` (`{ node, depth, expanded, selected }`) — the label; `actions`
(`{ node, depth }`) — the end of a row; `empty`.

**Methods** (through a template ref): `reveal(key)` opens every branch on the way to a node;
`expandAll()`; `collapseAll()`; `path(key)` returns the node and everything above it, which is a
breadcrumb.

## Accessibility

The tree is a `role="tree"` of `role="treeitem"` rows carrying `aria-level`, `aria-posinset`,
`aria-setsize`, `aria-expanded` and `aria-selected`, so a screen reader announces not just the name
but where it sits. The whole tree is one tab stop with a roving focus inside it — tabbing through a
hundred categories one row at a time is not navigation.

Drag and drop is never the only way to move a node: `Alt` and the arrow keys do the same four
moves, and each one is announced.
