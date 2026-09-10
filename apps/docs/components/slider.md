<script setup>
import SliderDemo from '../components/demos/SliderDemo.vue'
</script>

# Slider

`WxSlider` covers one thumb or two. Two is what a shop filter needs — a price band with both ends
draggable.

<SliderDemo />

## Usage

```vue
<script setup lang="ts">
import { ref } from 'vue'

const volume = ref(40)
const price = ref([200, 700])
</script>

<template>
  <wx-slider v-model="volume" show-value />
  <wx-slider v-model="price" range :min="0" :max="1000" :step="50" />
</template>
```

A single slider keeps a plain number in the model, not a one-element array — that is what a caller
stores. With `range` the model is a pair.

## Props

| Prop                    | Type                     | Default | Description                        |
| ----------------------- | ------------------------ | ------- | ---------------------------------- |
| `modelValue`            | `number \| number[]`     | `null`  | Value, or a pair when `range`      |
| `range`                 | `boolean`                | `false` | Two thumbs                         |
| `min`                   | `number`                 | `0`     | Lower bound                        |
| `max`                   | `number`                 | `100`   | Upper bound                        |
| `step`                  | `number`                 | `1`     | Stepping interval                  |
| `minStepsBetweenThumbs` | `number`                 | `0`     | Smallest gap between the thumbs    |
| `marks`                 | `Record<number, string>` | —       | Ticks under the track              |
| `showValue`             | `boolean`                | `false` | Print the value beside the track   |
| `disabled`              | `boolean`                | `false` | Disables dragging                  |
| `size`                  | `'sm' \| 'md' \| 'lg'`   | `'md'`  | Row height                         |
| `ariaLabel`             | `string`                 | —       | Label when there is no visible one |

**Events:** `update:modelValue`, `change`.

An empty model starts a single slider at `min` and a range at `[min, max]`, so the thumbs always
have somewhere to be.

## Accessibility

Built on Reka UI's slider: each thumb is a `role="slider"` with its own value, arrow keys step, Home
and End jump to the bounds, and Page Up / Page Down move in larger jumps.
