<script setup>
import RepeaterDemo from '../components/demos/RepeaterDemo.vue'
</script>

# Repeater

`WxRepeater` is a field whose value is a list of records: offices with a city and an address,
questions with an answer, sliders with a picture and a link. One set of fields, repeated, in an
order that is part of the answer.

<RepeaterDemo />

## Usage

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { WxFormItem, WxInput, WxRepeater } from '@webx-ui/core'

interface Office {
  city: string
  address: string
}

const offices = ref<Office[]>([{ city: 'Kyiv', address: 'Khreshchatyk 1' }])

const newOffice = (): Office => ({ city: '', address: '' })
</script>

<template>
  <wx-repeater v-model="offices" :new-item="newOffice" item-label="city" add-label="Add an office">
    <template #default="{ item, update }">
      <wx-form-item label="City">
        <wx-input :model-value="item.city" @update:model-value="update({ city: $event })" />
      </wx-form-item>

      <wx-form-item label="Address">
        <wx-input :model-value="item.address" @update:model-value="update({ address: $event })" />
      </wx-form-item>
    </template>
  </wx-repeater>
</template>
```

`v-model` is the list itself, in the order it is shown — saving it is saving the array.

## Writing a row

The slot is given `item`, its `index`, and `update`: a patch of the row, applied to a copy of it in
a copy of the list. Nothing the repeater hands out is ever mutated, so a parent that keeps the
model in a store, a history or a `readonly` is safe.

```vue
<template #default="{ item, update }">
  <wx-input :model-value="item.title" @update:model-value="update({ title: $event })" />
</template>
```

`v-model="item.title"` works too when the model is a local `ref` — it writes through the object in
the array — but it is a mutation, and it is the one form that will not survive a frozen model.

## What a row is called

`itemLabel` is a key of the item or a function of it, and it names the row twice: in the header
above its fields, and to a screen reader holding the grip. A key is shown after the position —
`#2 · Lviv` — because a folded list of similar titles is read by number as often as by name, and
a long one is cut with an ellipsis rather than wrapped. A key that holds a translated field
(`{ en: …, ru: … }`) shows the language being edited, else whichever is filled in. A function
answers for the whole header. Without either a row is its number, `#2`.

A header appears when there is something to put in it — an `itemLabel`, or `collapsible`. Two
fields and no title need neither, and the demo's second repeater draws no headers at all.

## Folding

`collapsible` folds a row to its header, which is what keeps a repeater of eight-field records
readable. `collapsed` starts the rows that were already there folded; a row somebody has just
added always opens, because it is the one they are about to fill in.

Folding is remembered per row rather than per position: removing the row above a folded one, or
dragging it elsewhere, leaves it folded.

## Limits

`min` and `max` bound the list: at `max` the add button is disabled, at `min` every remove button
is. Neither fills the list up to `min` on its own — a repeater shows the model it was given.

## Order

Rows are dragged by the grip, or moved with a keyboard: tab to the grip, space to pick the row up,
the arrows to move it, space to drop it. It is [`WxSortableList`](/components/sortable-list)
underneath, and the `move` event is the same one. `sortable: false` takes the grip away for a list
whose order means nothing.

## Inside a screen

A [screen described as JSON](/guide/screens) has this as `wx-repeater`, the one type with a nested
model: the node's children are the fields of one row, and a `name` inside it is a key of the item.

## Props

| Prop          | Type                                         | Default              |
| ------------- | -------------------------------------------- | -------------------- |
| `modelValue`  | `T[]`                                        | `[]`                 |
| `title`       | `string`                                     | —                    |
| `itemLabel`   | `string \| ((item: T, i: number) => string)` | —                    |
| `newItem`     | `() => T`                                    | `() => ({})`         |
| `addLabel`    | `string`                                     | `'Add'`              |
| `removeLabel` | `string`                                     | `'Remove'`           |
| `dragLabel`   | `string`                                     | `'Reorder'`          |
| `collapsible` | `boolean`                                    | `false`              |
| `collapsed`   | `boolean`                                    | `false`              |
| `min`         | `number`                                     | `0`                  |
| `max`         | `number`                                     | —                    |
| `sortable`    | `boolean`                                    | `true`               |
| `disabled`    | `boolean`                                    | `false`              |
| `emptyText`   | `string`                                     | `'Nothing here yet'` |
| `size`        | `'sm' \| 'md'`                               | `'md'`               |
| `plain`       | `boolean`                                    | `false`              |
| `ariaLabel`   | `string`                                     | —                    |

## Slots

| Slot      | Payload                   | What it is                           |
| --------- | ------------------------- | ------------------------------------ |
| `default` | `{ item, index, update }` | The fields of one row.               |
| `header`  | —                         | Replaces the title.                  |
| `extra`   | —                         | The end of the heading.              |
| `actions` | `{ item, index }`         | Row actions, before the remove one.  |
| `empty`   | —                         | Shown in place of an empty repeater. |

## Events

| Event    | Payload           | When                    |
| -------- | ----------------- | ----------------------- |
| `add`    | `(item, index)`   | A row was appended.     |
| `remove` | `(item, index)`   | A row was dropped.      |
| `move`   | `SortableMove<T>` | A row changed position. |
