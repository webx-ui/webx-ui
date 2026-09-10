<script setup>
import FormDemo from '../components/demos/FormDemo.vue'
</script>

# Form

`WxForm` and `WxFormItem` carry three things down to the controls inside them: **size**, **disabled**
and **validation state**. Nothing else — there is no rules engine here, because in a Laravel admin
the rules already live on the server.

<FormDemo />

## The validation contract

Hand `WxForm` the `errors` object from a 422 response, as-is:

```vue
<script setup lang="ts">
import { ref } from 'vue'
import type { ValidationErrors } from '@webx-ui/core'

const errors = ref<ValidationErrors>({})

async function save() {
  const response = await fetch('/admin/pages', { method: 'POST', body })
  if (response.status === 422) errors.value = (await response.json()).errors
}
</script>

<template>
  <wx-form :errors="errors" @submit="save">
    <wx-form-item label="Title" name="title" required>
      <wx-input v-model="form.title" />
    </wx-form-item>
  </wx-form>
</template>
```

Each `WxFormItem` looks up its own `name` and, when it finds messages:

- shows the first one under the control;
- switches the control to its error styling;
- sets `aria-invalid` on the control and points `aria-describedby` at the message.

The control needs no props for any of this. A single `error` prop on the item overrides the lookup
when the message comes from somewhere else.

Native submit is always prevented — these forms talk to an API, not to a URL — and `submit` is
emitted instead.

## Cascade

`disabled` and `size` are resolved most-specific-first: the control's own prop, then `WxFormItem`,
then `WxForm`.

```vue
<template>
  <wx-form disabled size="lg">
    <wx-form-item>
      <wx-input />
      <!-- large, disabled -->
    </wx-form-item>
    <wx-form-item size="sm">
      <wx-input :disabled="false" />
      <!-- small, enabled -->
    </wx-form-item>
  </wx-form>
</template>
```

## WxForm props

| Prop            | Type                       | Default   | Description                                    |
| --------------- | -------------------------- | --------- | ---------------------------------------------- |
| `errors`        | `Record<string, string[]>` | `{}`      | Server errors, keyed by field name             |
| `disabled`      | `boolean`                  | `false`   | Disables every control inside                  |
| `size`          | `'sm' \| 'md' \| 'lg'`     | `'md'`    | Default control size                           |
| `labelPosition` | `'top' \| 'left'`          | `'top'`   | Where labels sit                               |
| `labelWidth`    | `string`                   | `'160px'` | Label column width when `labelPosition="left"` |
| `gap`           | `'sm' \| 'md' \| 'lg'`     | `'md'`    | Space between form items                       |

**Events:** `submit` (`SubmitEvent`, already prevented), `reset` (`Event`).

## WxFormItem props

| Prop                | Type                   | Default | Description                                        |
| ------------------- | ---------------------- | ------- | -------------------------------------------------- |
| `label`             | `string`               | —       | Label text; the `label` slot takes anything richer |
| `name`              | `string`               | —       | Field name used to look up form errors             |
| `required`          | `boolean`              | `false` | Adds an asterisk; validates nothing by itself      |
| `error`             | `string \| string[]`   | —       | Message(s); overrides the form's errors            |
| `help`              | `string`               | —       | Hint shown while there is no error                 |
| `disabled`          | `boolean`              | `false` | Disables controls inside this item                 |
| `size`              | `'sm' \| 'md' \| 'lg'` | —       | Size for controls inside this item                 |
| `labelWidth`        | `string`               | —       | Overrides the form's label width                   |
| `reserveErrorSpace` | `boolean`              | `false` | Keeps room for a message so rows do not jump       |

**Slots:** `default` (the control), `label`.

## Accessibility

- The label is a real `<label for>` pointing at the control's generated id, so clicking it focuses
  the field.
- `WxCheckboxGroup` and `WxRadioGroup` cannot be the target of `for`. They register themselves with
  the item, which then renders the label as a `<span>` and links it with `aria-labelledby` instead.
- Error messages carry `role="alert"`, so a screen reader announces them when they appear.
