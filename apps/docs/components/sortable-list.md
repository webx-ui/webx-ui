<script setup>
import SortableListDemo from '../components/demos/SortableListDemo.vue'
</script>

# SortableList

`WxSortableList` is a list whose order is the point: pick a row up, move it, put it down. It is the
shape behind a gallery, a set of blocks on a page, the products chosen for a promotion.

<SortableListDemo />

## Usage

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { WxAction, WxActions, WxButton, WxSortableList } from '@webx-ui/core'

const products = ref([
  { id: 80633, title: 'Alternator Belt' },
  { id: 80636, title: 'Drive Pump Belt' },
])
</script>

<template>
  <wx-sortable-list v-model="products" title="Pick the products">
    <template #extra>
      <wx-button size="sm" variant="outline" @click="browse">Find</wx-button>
    </template>

    <template #default="{ item }">{{ item.title }}</template>

    <template #actions="{ item }">
      <wx-actions size="sm">
        <wx-action type="remove" @click="remove(item.id)" />
      </wx-actions>
    </template>
  </wx-sortable-list>
</template>
```

`v-model` is the list itself, in the order it is shown. A move rewrites it before the `move` event
is raised, so the array is always what the screen says — and saving is `products.map(p => p.id)`.

## The heading belongs to the list

A list that is picked into needs somewhere to say what it is and somewhere to put the button that
adds to it. `title` and `extra` are that: the same pair `WxCard` uses, so a list that has grown out
of a card keeps the same markup.

Nothing is drawn when neither is given.

## The grip is ours

By default every row carries a grip, and only the grip starts a drag. Two reasons, and the second
is the real one:

1. Something has to say the row can be moved. A row that happens to be draggable is a row nobody
   drags.
2. A keyboard cannot drag anything. The grip is a real control, so it takes focus, and from there
   space picks the row up, the arrows move it, space drops it and escape puts it back — the same
   splice the pointer performs, announced in a live region.

`handle="row"` drags by the whole row where there is nothing else on it to press; the row becomes
the control instead, and the keyboard works from there. `handle=".my-grip"` gives it to a button of
your own — in that case give it `aria-roledescription` and a keydown of its own, or keep ours.

## Actions do not start a drag

Buttons, links and fields in a row are filtered out of the gesture, so a bin at the end of a row
stays a bin even when the whole row is the handle.

## Between two lists

Lists that share a `group` pass rows to each other — an available list and a chosen one, the usual
pair:

```vue
<wx-sortable-list v-model="available" group="products" title="Available" />
<wx-sortable-list v-model="chosen" group="products" title="Chosen" />
```

Both models are rewritten by the drag. `move` is not raised for a row that left the list, because
what happened is not a move within it: watch the arrays, which are the record either way.

An empty list is still a place to drop into — that is why the empty message is a row of the list
rather than a note under it.

## Keying rows

`item-key` is the field that identifies a row, `id` by default. Pass a function where the key is
computed, or leave it alone for a list of strings — the position is the fallback, which is right
only while nothing is inserted in the middle.

## Props

| Prop         | Type                                          | Default              | Description                                    |
| ------------ | --------------------------------------------- | -------------------- | ---------------------------------------------- |
| `modelValue` | `T[]`                                         | `[]`                 | The list, in the order it is shown             |
| `title`      | `string`                                      | —                    | Heading above the list                         |
| `handle`     | `'grip' \| 'row' \| string`                   | `'grip'`             | What a drag starts from                        |
| `itemKey`    | `string \| ((item, index) => string\|number)` | `'id'`               | What identifies a row                          |
| `group`      | `string`                                      | —                    | Lists sharing a name exchange rows             |
| `disabled`   | `boolean`                                     | `false`              | Nothing can be moved                           |
| `size`       | `'sm' \| 'md'`                                | `'md'`               | Row height and type size                       |
| `plain`      | `boolean`                                     | `false`              | Drops the frame, for use inside a card         |
| `emptyText`  | `string`                                      | `'Nothing here yet'` | Shown in place of an empty list                |
| `dragLabel`  | `string`                                      | `'Reorder'`          | What the grip is called, before the row's name |
| `ariaLabel`  | `string`                                      | —                    | Accessible name for the list                   |

**Events:** `update:modelValue`; `move` (`{ item, from, to, via }`, where `via` is `'pointer'` or
`'keyboard'`).

**Slots:** `header` — replaces the title; `extra` — the end of the heading; `default`
(`{ item, index }`) — one row; `actions` (`{ item, index }`) — the end of a row; `empty`.

## Accessibility

Every row is reachable by keyboard through its grip, which is a button carrying the row's name —
`Reorder: Alternator Belt` — so a screen reader says what is about to be picked up rather than
"button". Moves are announced as they happen: what moved, and where it is now.

A list of fifty rows is fifty tab stops, which is the cost of the grip being real. Where that is
too many, a list that long usually wants a position field per row instead.
