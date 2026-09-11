<script setup>
import SegmentedDemo from '../components/demos/SegmentedDemo.vue'
</script>

# Segmented

`WxSegmented` is one of a few, chosen in place — a range, a view, a mode.

<SegmentedDemo />

## Usage

```vue
<script setup lang="ts">
const range = ref('week')
</script>

<template>
  <wx-segmented
    v-model="range"
    aria-label="Range"
    :options="[
      { label: 'Day', value: 'day' },
      { label: 'Week', value: 'week' },
      { label: 'Month', value: 'month' },
    ]"
  />
</template>
```

## It is a radio group

Not a row of buttons — and saying so out loud is what gets a screen reader to announce _2 of 4_,
and the arrow keys to move between the segments. A row of buttons gets neither, and a reader using
one has no way to know the four are alternatives.

Which also says when **not** to use it: three or four short, mutually exclusive options that are
worth showing all at once. More than that, or longer than a word or two, and a
[Select](/components/select) is kinder. Two options that are on and off are a
[Switch](/components/switch).

## Icons alone

A segment showing only an icon needs `ariaLabel` — otherwise it is announced as nothing at all.

```ts
{ icon: 'grid', value: 'grid', ariaLabel: 'Grid' }
```

## Props

| Prop        | Type                   | Default | Description                                       |
| ----------- | ---------------------- | ------- | ------------------------------------------------- |
| `options`   | `SegmentedOption[]`    | `[]`    | `{ label?, value, icon?, disabled?, ariaLabel? }` |
| `size`      | `'sm' \| 'md' \| 'lg'` | `'md'`  | Height and text size                              |
| `block`     | `boolean`              | `false` | Fills its width, segments sharing it              |
| `disabled`  | `boolean`              | `false` | The whole group                                   |
| `ariaLabel` | `string`               | —       | Accessible name for the group                     |

**Models:** `v-model` (`string \| number`).

**Events:** `change` (`value`) — only when the choice actually changes.

**Slots:** `option` — a segment's contents, with `{ option, selected }`.

## Accessibility

`role="radiogroup"` with a `radio` per segment, `aria-checked` on each, and one tab stop: Tab
reaches the chosen segment, and the arrow keys move within the group. Give the group an
`ariaLabel`, or the reader hears four options and no question.
