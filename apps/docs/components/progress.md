<script setup>
import ProgressDemo from '../components/demos/ProgressDemo.vue'
</script>

# Progress

`WxProgress` is how far along something is — a bar across, or a ring.

<ProgressDemo />

## Usage

```vue
<template>
  <wx-progress :value="percent" show-value aria-label="Import" />
</template>
```

## Count the thing, not the percentage

`max` means the bar can measure whatever you actually have, and `formatter` says it in those terms:

```vue
<wx-progress
  :value="done"
  :max="files.length"
  show-value
  :formatter="(v, m) => `${v} of ${m} files`"
/>
```

`3 of 8 files` tells a reader more than `38%`, and it does not round two files into one number.

## When there is no number

`indeterminate` is for work whose end is unknown — a request that has been sent, a job that has
been queued. The bar travels instead of filling, and it reports **no number at all** to a screen
reader: a progressbar without `aria-valuenow` is exactly how ARIA says _busy, and I cannot tell you
how far_. Faking a number there is worse than admitting it.

It is the same element either way, so nothing changes shape when a real figure arrives.

## Status is an outcome

`success` and `danger` are for a job that finished or failed, not for decoration. A bar that is
green all the way up has spent the one colour that means _done_.

## Props

| Prop            | Type                                              | Default     | Description                          |
| --------------- | ------------------------------------------------- | ----------- | ------------------------------------ |
| `value`         | `number`                                          | `0`         | How far along                        |
| `max`           | `number`                                          | `100`       | What finished looks like             |
| `type`          | `'line' \| 'circle'`                              | `'line'`    | A bar or a ring                      |
| `status`        | `'default' \| 'success' \| 'warning' \| 'danger'` | `'default'` | Colour of the fill                   |
| `size`          | `'sm' \| 'md' \| 'lg'`                            | `'md'`      | Thickness, and the ring's diameter   |
| `thickness`     | `number`                                          | —           | Overrides what `size` would give it  |
| `showValue`     | `boolean`                                         | `false`     | Shows the figure                     |
| `formatter`     | `(value, max) => string`                          | —           | What the figure says                 |
| `indeterminate` | `boolean`                                         | `false`     | Travels instead of filling           |
| `ariaLabel`     | `string`                                          | —           | Name it, when nothing beside it does |

**Slots:** `default` — replaces the figure, with `{ value, max, percent }`.

## Accessibility

The track is a `progressbar` carrying `aria-valuemin`, `aria-valuemax` and — when there is one —
`aria-valuenow` and an `aria-valuetext` in the reader's own words. Give it an `ariaLabel` unless
there is a visible label beside it. Reduced motion stops the travelling and the spin.
