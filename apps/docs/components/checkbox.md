<script setup>
import CheckboxDemo from '../components/demos/CheckboxDemo.vue'
</script>

# Checkbox

`WxCheckbox` is a real `<input type="checkbox">` with a styled box next to it, so Space, form
submission and screen readers all behave natively. `WxCheckboxGroup` binds several of them to one
array.

<CheckboxDemo />

## Usage

```vue
<script setup lang="ts">
import { ref } from 'vue'

const agreed = ref(false)
const permissions = ref(['read'])
</script>

<template>
  <wx-checkbox v-model="agreed" label="I have read the guidelines" />

  <wx-checkbox-group
    v-model="permissions"
    :options="[
      { label: 'Read', value: 'read' },
      { label: 'Write', value: 'write' },
      { label: 'Delete', value: 'delete', disabled: true },
    ]"
  />
</template>
```

Pass the boxes yourself when the labels need markup:

```vue
<template>
  <wx-checkbox-group v-model="permissions">
    <wx-checkbox value="read">Read <code>pages</code></wx-checkbox>
    <wx-checkbox value="write">Write <code>pages</code></wx-checkbox>
  </wx-checkbox-group>
</template>
```

## Select-all

`indeterminate` is the third state for a box that governs others. It is a DOM property, not an
attribute, and it is dropped automatically once the box becomes checked.

```vue
<template>
  <wx-checkbox
    :model-value="allChecked"
    :indeterminate="someChecked"
    label="All permissions"
    @change="toggleAll"
  />
</template>
```

## WxCheckbox props

| Prop            | Type                                  | Default   | Description                                                       |
| --------------- | ------------------------------------- | --------- | ----------------------------------------------------------------- |
| `modelValue`    | `boolean`                             | `false`   | Checked state when used standalone                                |
| `value`         | `string \| number \| boolean \| null` | —         | Value contributed to a group                                      |
| `label`         | `string`                              | —         | Label text; the default slot wins                                 |
| `indeterminate` | `boolean`                             | `false`   | Third visual state                                                |
| `disabled`      | `boolean`                             | `false`   | Disables the box                                                  |
| `size`          | `'sm' \| 'md' \| 'lg'`                | `'md'`    | Box and label size                                                |
| `name`          | `string`                              | —         | `name` of the underlying input                                    |
| `id`            | `string`                              | generated | Overrides the `id` the label points at; `WxFormItem` supplies one |
| `ariaLabel`     | `string`                              | —         | Label when there is no visible one                                |

**Events:** `update:modelValue` (`boolean`), `change` (`boolean`).

## WxCheckboxGroup props

| Prop         | Type                   | Default | Description                               |
| ------------ | ---------------------- | ------- | ----------------------------------------- |
| `modelValue` | `ChoiceValue[]`        | `[]`    | Checked values                            |
| `options`    | `CheckboxOption[]`     | —       | Renders the boxes for you                 |
| `disabled`   | `boolean`              | —       | Disables the whole group                  |
| `size`       | `'sm' \| 'md' \| 'lg'` | —       | Size for every box                        |
| `name`       | `string`               | —       | Shared `name` for the inputs              |
| `inline`     | `boolean`              | `false` | Lay the boxes out in a row                |
| `min`        | `number`               | —       | Refuses to uncheck below this many values |
| `max`        | `number`               | —       | Refuses to check beyond this many values  |

**Events:** `update:modelValue` (`ChoiceValue[]`), `change` (`ChoiceValue[]`).

`min` and `max` block the change silently — the box simply does not toggle, so the model never holds
an invalid selection.

## Accessibility

- The visible box is `aria-hidden`; the real input stays in the tab order and toggles with Space.
- Focus is shown on the box through `:focus-visible`, so it appears for keyboard users only.
- Inside a `WxFormItem`, the group is a `role="group"` labelled by the item's label.
