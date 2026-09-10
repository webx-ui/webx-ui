<script setup>
import ColorPickerDemo from '../components/demos/ColorPickerDemo.vue'
</script>

# ColorPicker

`WxColorPicker` is a hex field with the colour sitting inside it. Clicking opens a saturation
square, a hue strip and, if you supply them, preset swatches.

<ColorPickerDemo />

## Usage

```vue
<script setup lang="ts">
import { ref } from 'vue'

const brand = ref('#427edd')
</script>

<template>
  <wx-color-picker v-model="brand" clearable :presets="['#427edd', '#21c36d', '#f14646']" />
</template>
```

The model is a lower-case hex string, or `null` when the field is empty — an empty swatch shows a
chequerboard rather than pretending the colour is black.

## Typing

The text box is free-form and only a complete hex reaches the model, so a half-typed `#42` does not
wipe the value on its way to `#427edd`. On blur the field either keeps a valid colour or falls back
to the last one, and a missing `#` is added — pasting `21c36d` works.

## Props

| Prop          | Type                                             | Default     | Description                        |
| ------------- | ------------------------------------------------ | ----------- | ---------------------------------- |
| `modelValue`  | `string \| null`                                 | `null`      | Hex colour, lower case             |
| `presets`     | `string[]`                                       | `[]`        | Swatches under the picker          |
| `clearable`   | `boolean`                                        | `false`     | Show a button that empties it      |
| `placeholder` | `string`                                         | `'#000000'` | Placeholder text                   |
| `teleport`    | `boolean`                                        | `true`      | Render the panel in a portal       |
| `size`        | `'sm' \| 'md' \| 'lg'`                           | `'md'`      | Control height                     |
| `status`      | `'default' \| 'success' \| 'warning' \| 'error'` | `'default'` | Validation state                   |
| `disabled`    | `boolean`                                        | `false`     | Disables the field and the panel   |
| `readonly`    | `boolean`                                        | `false`     | Text stays, panel does not open    |
| `ariaLabel`   | `string`                                         | —           | Label when there is no visible one |

**Events:** `update:modelValue` (`string | null`), `change` (`string | null`).

## Under the hood

Reka UI tracks the colour and works out the geometry, but it draws neither the saturation square nor
the hue strip — it hands out the gradients and the wrapper paints them. The area is pinned to
saturation and brightness in HSB; left to its RGB defaults it would drift the hue as the pointer
moves horizontally, which is not what the gradient underneath promises.
