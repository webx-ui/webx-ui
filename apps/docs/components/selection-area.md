<script setup>
import SelectionAreaDemo from '../components/demos/SelectionAreaDemo.vue'
</script>

# SelectionArea

`WxSelectionArea` is the rubber band: drag across a grid or a list and everything the box
touches is selected. It is what a file manager does, and what an image library is expected to do.

<SelectionAreaDemo />

## Usage

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { WxSelectionArea, vWxSelect } from '@webx-ui/core'

const picked = ref<number[]>([])
</script>

<template>
  <wx-selection-area v-slot="{ isSelected }" v-model="picked" class="grid">
    <figure
      v-for="file in files"
      :key="file.id"
      v-wx-select="file.id"
      :class="{ 'is-selected': isSelected(file.id) }"
    >
      …
    </figure>
  </wx-selection-area>
</template>
```

Two parts: the area, which draws the box and holds the selection, and `v-wx-select`, which hands
it an item and the value that stands for it. `app.use(WebxUI)` registers the directive along with
the components; a named import brings it in on its own.

## The item is whatever is already there

A directive rather than a wrapper component, because the thing being selected is already an
element — a card, a row, a list item — and putting a box of ours around every one of them would
break the grid or the table it sits in. `v-wx-select` goes on a `<tr>` as readily as on a
`<figure>`, and it keeps the value's type: `v-wx-select="42"` selects the number `42`, which is
what the model holds and what the API expects back.

For markup that is not written in Vue, `data-wx-selectable="42"` does the same thing and yields
the string `'42'` — all an attribute can carry.

## What the area does not touch

A drag that begins on a link, a button, a field or anything else a pointer already means
something to is left to that control. The bin on a tile stays a bin. Mark anything else with
`data-wx-no-select` — a description a reader is meant to be able to copy, say.

## The whole gesture, not just the box

A selection people can only make by dragging is a selection they cannot make one item at a time,
so the area handles the rest of it as well:

| Gesture              | What it does                                    |
| -------------------- | ----------------------------------------------- |
| Drag                 | Replaces the selection with what the box caught |
| Shift- or ctrl-drag  | Adds to it                                      |
| Alt-drag             | Takes away from it                              |
| Click an item        | Picks that one                                  |
| Ctrl-click an item   | Adds or removes that one                        |
| Shift-click an item  | Takes the run from the last one clicked         |
| Tap an item          | Adds or removes that one                        |
| Click the background | Clears                                          |
| Ctrl+A / Escape      | Everything / nothing                            |

Set `:click-select="false"` to keep the drag and leave clicking to the items themselves — a grid
whose tiles open something when clicked wants that.

## The selection is the model

`v-model` is an array of values, in the order they were taken. The area never keeps a second copy
of it, so a selection can be set from outside — restored from a query string, cleared after a bulk
action — and the grid follows.

`isSelected` comes down with the slot because the alternative is `picked.includes(id)` once per
item, which is a scan of the whole selection per tile on every render. The slot's version is a
`Set`.

## It measures once

The items are measured when the drag begins, in the area's own coordinates, and those do not move
when anything scrolls. So dragging past the foot of a long list — which scrolls it, at a speed that
grows the further past the edge you are — costs nothing per frame but the arithmetic.

The bill for that is a layout that changes mid-drag: a list that loads more rows underneath one
will not catch them until the next drag. Set `:edge-scroll="0"` where the area should not scroll
anything at all.

## A finger is not a mouse

A tap picks the item under it, always — that half needs nothing turned on. It is the box that is
off by default: on a touch screen a drag across a grid means scroll, and taking that away leaves
people stranded. A finger that travels is left to the browser, and the selection it started on is
kept, not replaced.

A tap **adds and removes** rather than replacing, the way ctrl-click does. There is no modifier on
a phone and no box either, so a tap that replaced the selection would be a selection that can never
hold more than one thing. A tap on the background still clears, which is the way back to none.

`touch` turns the box on where the gesture is worth more than the scrolling — a canvas, a seat
picker. The area then sets `touch-action: none`, which is the real price: it stops scrolling with a
finger at all. Those interfaces usually want a long press first, which this does not do.

Test that on a device rather than in a desktop browser's device mode. The emulator sends the
gesture as a pointer and nothing takes it away; a phone hands it to the scroller, which is the
whole difference.

## Props

| Prop          | Type                       | Default       | Description                                            |
| ------------- | -------------------------- | ------------- | ------------------------------------------------------ |
| `modelValue`  | `(string \| number)[]`     | `[]`          | The selection                                          |
| `match`       | `'intersect' \| 'contain'` | `'intersect'` | Whether the box has to cover an item or touch it       |
| `threshold`   | `number`                   | `5`           | Pixels before a press becomes a drag                   |
| `clickSelect` | `boolean`                  | `true`        | Clicks pick items; a click beside them clears          |
| `touch`       | `boolean`                  | `false`       | The box can be drawn with a finger; a tap always picks |
| `edgeScroll`  | `number`                   | `48`          | How near the edge the drag scrolls; `0` never does     |
| `disabled`    | `boolean`                  | `false`       | Leaves every pointer alone                             |

**Events:** `update:modelValue`; `start`; `end` (`SelectionValue[]`).

**Slot props:** `selected` (a `Set`), `isSelected(value)`, `selecting`.

**Exposed:** `selectAll()`, `clear()`.

## Accessibility

A rubber band is a pointer gesture and has no keyboard in it, so it must never be the only way to
select something. Give the items their own affordance — a checkbox on the tile, a checkbox column
in the table — and let the box be the accelerator it is. Ctrl+A and Escape work once the area has
been clicked in; a focusable item inside it keeps its own keyboard behaviour.

If the list has a count or a toolbar that appears with the selection, announce it: a live region
saying `3 selected` is what a screen reader gets in place of watching the box.
