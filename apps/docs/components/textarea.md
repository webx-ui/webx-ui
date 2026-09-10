<script setup>
import TextareaDemo from '../components/demos/TextareaDemo.vue'
</script>

# Textarea

`WxTextarea` is the multi-line sibling of [`WxInput`](/components/input): same states, same form
integration, plus autosizing and a character counter.

<TextareaDemo />

## Usage

```vue
<script setup lang="ts">
import { ref } from 'vue'

const summary = ref('')
</script>

<template>
  <wx-textarea v-model="summary" :rows="4" :maxlength="200" show-count />
</template>
```

## Autosize

`autosize` grows the field with its content. Bound it to keep a long text from taking over the page —
past `maxRows` the field scrolls instead of growing:

```vue
<template>
  <wx-textarea v-model="notes" :autosize="{ minRows: 2, maxRows: 6 }" />
</template>
```

While autosizing, manual resizing is switched off — the two fight each other otherwise.

## Props

| Prop          | Type                                                | Default      | Description                                 |
| ------------- | --------------------------------------------------- | ------------ | ------------------------------------------- |
| `modelValue`  | `string`                                            | `''`         | `v-model` value                             |
| `rows`        | `number`                                            | `3`          | Visible rows when autosize is off           |
| `autosize`    | `boolean \| { minRows?: number; maxRows?: number }` | `false`      | Grows with the content                      |
| `maxlength`   | `number`                                            | —            | Native `maxlength`                          |
| `showCount`   | `boolean`                                           | `false`      | Renders `current / max` (needs `maxlength`) |
| `resize`      | `'none' \| 'vertical' \| 'both'`                    | `'vertical'` | Manual resize handle                        |
| `size`        | `'sm' \| 'md' \| 'lg'`                              | `'md'`       | Font size                                   |
| `status`      | `'default' \| 'success' \| 'warning' \| 'error'`    | `'default'`  | Validation state                            |
| `placeholder` | `string`                                            | —            | Placeholder text                            |
| `disabled`    | `boolean`                                           | `false`      | Disables the field                          |
| `readonly`    | `boolean`                                           | `false`      | Read-only field                             |
| `ariaLabel`   | `string`                                            | —            | Label when there is no visible one          |

**Events:** `update:modelValue` (`string`), `input` (`string`), `change` (`string`), `focus`, `blur`.

**Exposed:** `focus()`, `blur()`, `select()`, and `textarea` — a ref to the native element.

Unknown attributes fall through to the `<textarea>`, not the wrapper.

## Accessibility

Inside a [`WxFormItem`](/components/form) the id, error state and `aria-describedby` are wired
automatically. Standalone, give it an `ariaLabel`.
