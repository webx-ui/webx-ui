<script setup>
import TransferDemo from '../components/demos/TransferDemo.vue'
</script>

# Transfer

`WxTransfer` is two lists and a pair of arrows: everything there is on the left, everything chosen
on the right. It is the shape for choosing from a set you also need to see — permissions, roles,
the columns of a report.

<TransferDemo />

## Usage

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { WxTransfer, type TransferItem } from '@webx-ui/core'

const permissions: TransferItem[] = [
  { value: 'posts.read', label: 'Read posts' },
  {
    value: 'posts.write',
    label: 'Write posts',
    description: 'Create and edit, without publishing',
  },
  { value: 'settings.write', label: 'Change settings', disabled: true },
]

const granted = ref<string[]>(['posts.read'])
</script>

<template>
  <wx-transfer
    v-model="granted"
    :items="permissions"
    :titles="['Available', 'Granted']"
    searchable
  />
</template>
```

`items` is everything, both panels together. `v-model` is the right-hand panel — the values that
were chosen — so saving is the model as it stands, and the left panel is simply everything else.

## The right panel is in the model's order

The model is an array, and the order in it is the order it will be saved in. So the right panel
shows it in that order rather than the catalogue's: a panel that showed something else would be
quietly lying about what is about to be sent.

## When to reach for it, and when not

It earns its width when the reader needs to see **what they did not choose** — a list of forty
permissions where the point is which ones are missing. When they do not, a `WxSelect` with
`multiple`, or a Find button over a [SortableList](/components/sortable-list), says the same thing
in a quarter of the room.

Where the chosen order matters and is dragged rather than picked, that is
[SortableList](/components/sortable-list) — two of them sharing a `group` pass rows between
themselves.

## Moving

- Tick and press an arrow. The arrow is off until there is something for it to move.
- The checkbox in a heading ticks **what the search has left showing**, and nothing behind it — a
  select-all that quietly took forty hidden rows with it would be a trap.
- Double-click a row to move that one.
- `disabled` on an item pins it to the side it is on, from either direction.

Every move is announced in a live region: `3 moved to Granted`.

## Narrow

Side by side is the point of the component, so when there is no longer room for it the panels stack
and the arrows turn to point up and down. That is decided by the **panel's** width, not the
window's — a transfer in a 380px drawer on a wide screen is narrow, and the window has nothing to
say about it.

## Props

| Prop                | Type                   | Default                     | Description                         |
| ------------------- | ---------------------- | --------------------------- | ----------------------------------- |
| `modelValue`        | `(string \| number)[]` | `[]`                        | The values on the right             |
| `items`             | `TransferItem[]`       | `[]`                        | Everything, both panels together    |
| `titles`            | `[string, string]`     | `['Available', 'Selected']` | Headings of the two panels          |
| `searchable`        | `boolean`              | `false`                     | A search field over each panel      |
| `searchPlaceholder` | `string`               | `'Search'`                  | Placeholder for both                |
| `height`            | `number \| string`     | `260`                       | Height of a list                    |
| `emptyText`         | `string`               | `'Nothing here'`            | Shown in a panel with nothing in it |
| `size`              | `'sm' \| 'md' \| 'lg'` | `'md'`                      | Size of the controls                |
| `disabled`          | `boolean`              | `false`                     | Nothing moves                       |
| `toRightLabel`      | `string`               | `'Move to the right'`       | Accessible name for the first arrow |
| `toLeftLabel`       | `string`               | `'Move to the left'`        | Accessible name for the second      |

**TransferItem:** `{ value, label?, description?, disabled? }` — `label` falls back to the value.

**Events:** `update:modelValue`; `change` (`{ values, to }`, where `to` is `'left'` or `'right'`).

**Slots:** `item` (`{ item, side }`) — one row; `empty` (`{ side }`).

## Accessibility

Each row is a real checkbox with a real label, so a keyboard reaches them by tabbing and ticks them
with space; the arrows are buttons with names, since an arrow glyph is not one. The heading
checkbox carries the indeterminate state when only some of the shown rows are ticked, and each move
is announced.

A double-click is a shortcut, never the only way to do something.
