<script setup>
import DateRangePickerDemo from '../components/demos/DateRangePickerDemo.vue'
</script>

# DateRangePicker

`WxDateRangePicker` picks a period. Two months are shown side by side, so a range that crosses a
month boundary takes one pass instead of two.

<DateRangePickerDemo />

## Usage

```vue
<script setup lang="ts">
import { ref } from 'vue'

const period = ref<string[] | null>(null)
</script>

<template>
  <wx-date-range-picker v-model="period" />
</template>
```

The model is `[start, end]` in `yyyy-MM-dd`, or `null` while nothing is picked — the same convention
as [DatePicker](/components/date-picker), so a period goes to a Laravel API without conversion.

A range of days, not of moments: there is no clock under the calendar, because the date-only format
would throw away whatever was set on it. Where the ends really are moments, use two
[`WxDateTimePicker`s](/components/date-picker).

```vue
<template>
  <!-- three months, bounded to one year -->
  <wx-date-range-picker v-model="quarter" :months="3" min-date="2026-01-01" max-date="2026-12-31" />

  <!-- Date objects instead of strings -->
  <wx-date-range-picker v-model="dates" value-format="date" />
</template>
```

## Props

| Prop          | Type                                             | Default      | Description                                                       |
| ------------- | ------------------------------------------------ | ------------ | ----------------------------------------------------------------- |
| `modelValue`  | `string[] \| Date[] \| null`                     | `null`       | `[start, end]`                                                    |
| `months`      | `number`                                         | `2`          | Months shown side by side                                         |
| `valueFormat` | `string`                                         | `yyyy-MM-dd` | Format each end is stored in; `'date'` keeps `Date`               |
| `format`      | `string`                                         | `dd.MM.yyyy` | Format the field shows                                            |
| `minDate`     | `string \| Date`                                 | —            | Earliest selectable date                                          |
| `maxDate`     | `string \| Date`                                 | —            | Latest selectable date                                            |
| `weekStart`   | `number`                                         | `1`          | 0 is Sunday, 1 is Monday                                          |
| `clearable`   | `boolean`                                        | `true`       | Show the clear button                                             |
| `autoApply`   | `boolean`                                        | `true`       | Apply on the second pick                                          |
| `textInput`   | `boolean`                                        | `false`      | Allow typing the range                                            |
| `teleport`    | `boolean`                                        | `true`       | Render the calendar in a portal                                   |
| `size`        | `'sm' \| 'md' \| 'lg'`                           | `'md'`       | Control height                                                    |
| `status`      | `'default' \| 'success' \| 'warning' \| 'error'` | `'default'`  | Validation state                                                  |
| `disabled`    | `boolean`                                        | `false`      | Disables the field                                                |
| `readonly`    | `boolean`                                        | `false`      | Read-only field                                                   |
| `placeholder` | `string`                                         | —            | Placeholder text                                                  |
| `id`          | `string`                                         | generated    | Overrides the `id` the label points at; `WxFormItem` supplies one |
| `name`        | `string`                                         | —            | `name` of the underlying input                                    |
| `ariaLabel`   | `string`                                         | —            | Label when there is no visible one                                |

**Events:** `update:modelValue`, `change`, `clear`, `open`, `close`.

Anything else the underlying picker accepts falls through as an attribute — see
[DatePicker](/components/date-picker#anything-else-the-library-takes).
