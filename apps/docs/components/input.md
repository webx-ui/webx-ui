<script setup>
import InputDemo from '../components/demos/InputDemo.vue'
</script>

# Input

`WxInput` — a single-line text field with `v-model`, affix slots, a clear button and validation
states.

<InputDemo />

## Usage

```vue
<script setup lang="ts">
import { ref } from 'vue'

const title = ref('')
</script>

<template>
  <wx-input v-model="title" placeholder="Title" clearable />
  <wx-input v-model="email" type="email" status="error" />
  <wx-input v-model="slug" show-count :maxlength="60">
    <template #prefix>/</template>
  </wx-input>
</template>
```

## Props

| Prop           | Type                                                                        | Default     | Description                                                       |
| -------------- | --------------------------------------------------------------------------- | ----------- | ----------------------------------------------------------------- |
| `modelValue`   | `string \| number \| undefined`                                             | `''`        | `v-model` value                                                   |
| `type`         | `'text' \| 'password' \| 'email' \| 'number' \| 'search' \| 'tel' \| 'url'` | `'text'`    | Native input type                                                 |
| `size`         | `'sm' \| 'md' \| 'lg'`                                                      | `'md'`      | Control height and font size                                      |
| `status`       | `'default' \| 'success' \| 'warning' \| 'error'`                            | `'default'` | Validation state                                                  |
| `placeholder`  | `string`                                                                    | —           | Placeholder text                                                  |
| `disabled`     | `boolean`                                                                   | `false`     | Disables the field                                                |
| `readonly`     | `boolean`                                                                   | `false`     | Read-only field                                                   |
| `clearable`    | `boolean`                                                                   | `false`     | Shows a clear button when filled                                  |
| `maxlength`    | `number`                                                                    | —           | Native `maxlength`                                                |
| `showCount`    | `boolean`                                                                   | `false`     | Renders `current / max` (needs `maxlength`)                       |
| `autocomplete` | `string`                                                                    | —           | Native `autocomplete`                                             |
| `id`           | `string`                                                                    | generated   | Overrides the `id` the label points at; `WxFormItem` supplies one |
| `ariaLabel`    | `string`                                                                    | —           | Label when there is no visible `<label>`                          |

Unknown attributes (`name`, `id`, `required`, …) fall through to the inner `<input>`, not the
wrapper — with two exceptions. `class` and `style` stay on the wrapper, because that is the thing
the caller can see: `class="w-60"` on a `<wx-input>` is asking for a narrower input, and a class
that landed on the element inside it could not be reached from a parent's scoped CSS anyway.

## Events

| Event               | Payload      | Fires                            |
| ------------------- | ------------ | -------------------------------- |
| `update:modelValue` | `string`     | On every keystroke, and on clear |
| `input`             | `string`     | On every keystroke, and on clear |
| `change`            | `string`     | On the native `change` event     |
| `focus`             | `FocusEvent` | On focus                         |
| `blur`              | `FocusEvent` | On blur                          |
| `clear`             | —            | When the clear button is pressed |

## Slots

| Slot     | Description                          |
| -------- | ------------------------------------ |
| `prefix` | Content before the input (icon, `@`) |
| `suffix` | Content after the input              |

## Exposed methods

`focus()`, `blur()`, `select()`, and `input` — a ref to the native element.

```vue
<script setup lang="ts">
const field = ref()
onMounted(() => field.value?.focus())
</script>

<template>
  <wx-input ref="field" />
</template>
```

## Accessibility

- `status="error"` sets `aria-invalid="true"` on the input.
- The clear button is `tabindex="-1"` with `aria-label="Clear"`, so it never gets in the way of
  keyboard navigation; focus returns to the input after clearing.
- Use `ariaLabel` when the field has no visible label.
