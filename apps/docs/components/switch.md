<script setup>
import SwitchDemo from '../components/demos/SwitchDemo.vue'
</script>

# Switch

`WxSwitch` is for settings that take effect immediately — published, indexing allowed, notifications
on. For a value the user confirms with a Save button, a checkbox reads better.

<SwitchDemo />

## Usage

```vue
<script setup lang="ts">
import { ref } from 'vue'

const published = ref(false)
</script>

<template>
  <wx-switch v-model="published" label="Published" />
</template>
```

The model does not have to be boolean. A backend that stores `"yes"` / `"no"` needs no mapping layer:

```vue
<template>
  <wx-switch v-model="indexing" active-value="yes" inactive-value="no" label="Allow indexing" />
</template>
```

## Props

| Prop            | Type                                  | Default | Description                        |
| --------------- | ------------------------------------- | ------- | ---------------------------------- |
| `modelValue`    | `ChoiceValue`                         | `false` | Current value                      |
| `activeValue`   | `string \| number \| boolean \| null` | `true`  | Written when switched on           |
| `inactiveValue` | `string \| number \| boolean \| null` | `false` | Written when switched off          |
| `label`         | `string`                              | —       | Label text; the default slot wins  |
| `disabled`      | `boolean`                             | `false` | Disables the switch                |
| `size`          | `'sm' \| 'md' \| 'lg'`                | `'md'`  | Track size                         |
| `name`          | `string`                              | —       | `name` of the underlying input     |
| `ariaLabel`     | `string`                              | —       | Label when there is no visible one |

**Events:** `update:modelValue` (`ChoiceValue`), `change` (`ChoiceValue`).

## Accessibility

- Built on a native checkbox with `role="switch"`, so Space toggles it and assistive tech announces
  it as a switch rather than a checkbox.
- The track is `aria-hidden`; focus is shown on it through `:focus-visible`.
- Give it a `label`, or an `ariaLabel` when the meaning comes from a nearby column header.
