<script setup>
import RadioDemo from '../components/demos/RadioDemo.vue'
</script>

# Radio

`WxRadioGroup` binds a set of `WxRadio`s to one value. The radios are native inputs sharing a `name`,
which is what makes arrow-key navigation work — the browser does it, not us.

<RadioDemo />

## Usage

```vue
<script setup lang="ts">
import { ref } from 'vue'

const state = ref('draft')
</script>

<template>
  <wx-radio-group
    v-model="state"
    :options="[
      { label: 'Draft', value: 'draft' },
      { label: 'Published', value: 'published' },
      { label: 'Archived', value: 'archived', disabled: true },
    ]"
  />
</template>
```

Pass the radios yourself when the labels need markup:

```vue
<template>
  <wx-radio-group v-model="state" inline>
    <wx-radio value="draft">Draft</wx-radio>
    <wx-radio value="published">Published <b>(live)</b></wx-radio>
  </wx-radio-group>
</template>
```

A standalone `WxRadio` also works — it writes its own `value` into the model when picked.

## WxRadio props

| Prop        | Type                                  | Default | Description                        |
| ----------- | ------------------------------------- | ------- | ---------------------------------- |
| `value`     | `string \| number \| boolean \| null` | —       | Value this radio stands for        |
| `label`     | `string`                              | —       | Label text; the default slot wins  |
| `disabled`  | `boolean`                             | `false` | Disables the radio                 |
| `size`      | `'sm' \| 'md' \| 'lg'`                | `'md'`  | Dot and label size                 |
| `name`      | `string`                              | —       | `name` of the underlying input     |
| `ariaLabel` | `string`                              | —       | Label when there is no visible one |

**Events:** `update:modelValue` (`ChoiceValue`), `change` (`ChoiceValue`).

## WxRadioGroup props

| Prop         | Type                   | Default     | Description                  |
| ------------ | ---------------------- | ----------- | ---------------------------- |
| `modelValue` | `ChoiceValue`          | `undefined` | Selected value               |
| `options`    | `RadioOption[]`        | —           | Renders the radios for you   |
| `disabled`   | `boolean`              | —           | Disables the whole group     |
| `size`       | `'sm' \| 'md' \| 'lg'` | —           | Size for every radio         |
| `name`       | `string`               | generated   | Shared `name` for the inputs |
| `inline`     | `boolean`              | `false`     | Lay the radios out in a row  |

**Events:** `update:modelValue` (`ChoiceValue`), `change` (`ChoiceValue`).

Picking the value that is already selected emits nothing.

## Accessibility

- The group is a `role="radiogroup"`; inside a `WxFormItem` it is labelled by the item's label.
- Arrow keys move between radios and select as they go — native behaviour of a shared `name`, which
  is why a name is always set even when you do not pass one.
- Focus is shown on the dot through `:focus-visible`.
