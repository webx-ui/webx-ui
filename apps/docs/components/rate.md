<script setup>
import RateDemo from '../components/demos/RateDemo.vue'
</script>

# Rate

`WxRate` is the star rating a product review shows: whole stars by default, halves when asked.

<RateDemo />

## Usage

```vue
<script setup lang="ts">
import { ref } from 'vue'

const rating = ref(0)
</script>

<template>
  <wx-rate v-model="rating" />
  <wx-rate v-model="rating" allow-half show-value />
  <wx-rate :model-value="4" readonly />
</template>
```

## Props

| Prop         | Type                   | Default   | Description                                                       |
| ------------ | ---------------------- | --------- | ----------------------------------------------------------------- |
| `modelValue` | `number`               | `0`       | Current rating                                                    |
| `max`        | `number`               | `5`       | How many stars to draw                                            |
| `allowHalf`  | `boolean`              | `false`   | Halves, set from the left of a star                               |
| `clearable`  | `boolean`              | `true`    | Clicking the current value resets to 0                            |
| `showValue`  | `boolean`              | `false`   | Print the number beside the stars                                 |
| `readonly`   | `boolean`              | `false`   | Display only                                                      |
| `disabled`   | `boolean`              | `false`   | Display only, and dimmed                                          |
| `size`       | `'sm' \| 'md' \| 'lg'` | `'md'`    | Star size                                                         |
| `id`         | `string`               | generated | Overrides the `id` the label points at; `WxFormItem` supplies one |
| `name`       | `string`               | —         | `name` of the underlying input                                    |
| `ariaLabel`  | `string`               | —         | Label when there is no visible one                                |

**Events:** `update:modelValue` (`number`), `change` (`number`).

## Keyboard

Arrow keys move by one step — half a star when `allowHalf` is on — Home clears the rating and End
maxes it out.

Clearing is a **pointer** gesture only: clicking the star that is already selected resets to zero,
but pressing the right arrow at the maximum does nothing. A keyboard user pressing further right
expects to stay put, not to lose the value.

## Accessibility

The control is a single `role="slider"` with `aria-valuenow` and `aria-valuemax`, focusable as one
stop rather than as five separate stars. `readonly` sets `aria-readonly`, `disabled` sets
`aria-disabled`.
