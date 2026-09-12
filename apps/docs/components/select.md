<script setup>
import SelectDemo from '../components/demos/SelectDemo.vue'
</script>

# Select

`WxSelect` is the dropdown for picking one value or several. It wraps
[Reka UI](https://reka-ui.com/)'s Combobox, which brings the keyboard behaviour, the floating
positioning and the ARIA wiring — the parts that are easy to get subtly wrong.

<SelectDemo />

## Usage

```vue
<script setup lang="ts">
import { ref } from 'vue'

const status = ref('draft')
</script>

<template>
  <wx-select
    v-model="status"
    :options="[
      { label: 'Draft', value: 'draft' },
      { label: 'Published', value: 'published' },
      { label: 'Archived', value: 'archived', disabled: true },
    ]"
    placeholder="Pick a status"
    clearable
  />
</template>
```

The model holds the option's `value`, and `null` when nothing is picked. With `multiple` it holds an
array, empty when nothing is picked — never `null`, so the shape a backend receives does not change
with the selection.

## Searching

`filterable` turns the field into a search box that filters the options as you type.

```vue
<template>
  <wx-select v-model="author" :options="authors" filterable placeholder="Find an author" />
</template>
```

For a list that lives on the server, ignore the built-in filtering and feed `options` yourself —
the `search` event fires on every keystroke:

```vue
<template>
  <wx-select v-model="author" :options="found" filterable @search="load" />
</template>
```

## Props

| Prop          | Type                                             | Default           | Description                                                       |
| ------------- | ------------------------------------------------ | ----------------- | ----------------------------------------------------------------- |
| `modelValue`  | `string \| number \| array \| null`              | `null`            | Selected value, or values with `multiple`                         |
| `options`     | `SelectOption[]`                                 | `[]`              | `{ label, value, disabled? }`                                     |
| `multiple`    | `boolean`                                        | `false`           | Pick several; the model becomes an array                          |
| `filterable`  | `boolean`                                        | `false`           | Show a search field                                               |
| `clearable`   | `boolean`                                        | `false`           | Show a button that empties the selection                          |
| `placeholder` | `string`                                         | —                 | Shown while nothing is selected                                   |
| `emptyText`   | `string`                                         | `'Nothing found'` | Shown when filtering matches nothing                              |
| `teleport`    | `boolean`                                        | `true`            | Render the list in a portal                                       |
| `size`        | `'sm' \| 'md' \| 'lg'`                           | `'md'`            | Control height                                                    |
| `status`      | `'default' \| 'success' \| 'warning' \| 'error'` | `'default'`       | Validation state                                                  |
| `disabled`    | `boolean`                                        | `false`           | Disables the control                                              |
| `id`          | `string`                                         | generated         | Overrides the `id` the label points at; `WxFormItem` supplies one |
| `name`        | `string`                                         | —                 | `name` of the underlying input                                    |
| `ariaLabel`   | `string`                                         | —                 | Label when there is no visible one                                |

**Events:** `update:modelValue`, `change`, `clear`, `open`, `close`, `search` (`string`).

**Slot:** `default` — replaces the generated options when you need custom markup. Use Reka's
`ComboboxItem` inside it.

`teleport` is on by default for the same reason as in the pickers: a list inside a `WxCard` would
otherwise be clipped by `overflow: hidden`.

## Accessibility

The combobox roles, `aria-expanded`, the active-descendant wiring and the keyboard behaviour —
arrows to move, Enter to pick, Escape to close, typing to jump — all come from Reka. Inside a
[`WxFormItem`](/components/form) the generated id reaches the control, so the label and the error
message link up as usual.
