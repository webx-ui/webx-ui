<script setup>
import InputNumberDemo from '../components/demos/InputNumberDemo.vue'
</script>

# InputNumber

`WxInputNumber` is a numeric field with increment and decrement buttons. The model is a `number` or
`null` — never a string, and never `NaN`.

<InputNumberDemo />

## Usage

```vue
<script setup lang="ts">
import { ref } from 'vue'

const quantity = ref(1)
</script>

<template>
  <wx-input-number v-model="quantity" :min="0" :max="99" />
  <wx-input-number v-model="price" :step="0.1" :precision="2" />
  <wx-input-number v-model="weight" controls-position="right" />
  <wx-input-number v-model="weight" :controls="false" />
</template>
```

## How values are handled

Three details that make the difference between a usable field and an annoying one:

- **Clamping happens on blur, not on every keystroke.** In a field with `min="10"`, typing `5` as
  the first digit of `50` is allowed; it becomes `10` only if you leave it that way.
- **Stepping rounds through a scaled integer**, so `0.1 + 0.2` writes `0.3` to the model rather than
  `0.30000000000000004`.
- **Emptying the field writes `null`**, not `0` — "no value" and "zero" are different answers, and a
  backend usually treats them differently.

`precision` defaults to whatever `step` implies: `step="0.5"` formats to one decimal.

## The mouse wheel

`wheel` is off by default, and that is deliberate. With it on, scrolling a long form past a field
the user happens to have focused edits that field silently — no click, no keystroke, no sign that
anything changed. It is the classic way a numeric field loses data, and the reason the same
behaviour in a native `<input type="number">` is widely switched off.

Turn it on where scrolling is unlikely to overlap editing — a compact toolbar, a short dialog:

```vue
<template>
  <wx-input-number v-model="zoom" wheel :min="10" :max="400" :step="10" />
</template>
```

Even then it only fires while the field is focused, and it swallows the scroll event so a merely
hovered field never steals the page scroll.

## Props

| Prop               | Type                                             | Default     | Description                                     |
| ------------------ | ------------------------------------------------ | ----------- | ----------------------------------------------- |
| `modelValue`       | `number \| null`                                 | `null`      | Current value                                   |
| `min`              | `number`                                         | —           | Lower bound                                     |
| `max`              | `number`                                         | —           | Upper bound                                     |
| `step`             | `number`                                         | `1`         | Amount added by buttons and arrow keys          |
| `precision`        | `number`                                         | from `step` | Decimal places kept                             |
| `controls`         | `boolean`                                        | `true`      | Show the buttons                                |
| `controlsPosition` | `'sides' \| 'right'`                             | `'sides'`   | Buttons around the field, or stacked at the end |
| `size`             | `'sm' \| 'md' \| 'lg'`                           | `'md'`      | Control height                                  |
| `status`           | `'default' \| 'success' \| 'warning' \| 'error'` | `'default'` | Validation state                                |
| `placeholder`      | `string`                                         | —           | Placeholder text                                |
| `disabled`         | `boolean`                                        | `false`     | Disables the field                              |
| `readonly`         | `boolean`                                        | `false`     | Read-only field                                 |
| `ariaLabel`        | `string`                                         | —           | Label when there is no visible one              |

**Events:** `update:modelValue` (`number | null`), `change` (`number | null`), `focus`, `blur`.

**Exposed:** `focus()`, `blur()`, `increase()`, `decrease()`, and `input` — a ref to the native
element.

## Accessibility

- The field is a `role="spinbutton"` carrying `aria-valuenow`, `aria-valuemin` and `aria-valuemax`.
- ArrowUp and ArrowDown step the value; Enter normalises what was typed.
- The buttons are `tabindex="-1"` — they would otherwise sit between the field and the next control
  for no benefit, since the arrow keys already do the job. They disable themselves at the bounds.
