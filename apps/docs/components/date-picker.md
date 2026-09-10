<script setup>
import DatePickerDemo from '../components/demos/DatePickerDemo.vue'
</script>

# DatePicker

Three components over one implementation: `WxDatePicker` (a date), `WxDateTimePicker` (a date and a
time) and `WxTimePicker` (a time). They wrap
[`@vuepic/vue-datepicker`](https://vue3datepicker.com/) — calendars are a lot of behaviour to get
right, and that library already has.

<DatePickerDemo />

## The model is a string, in the backend's format

By default the model holds exactly what a Laravel column expects, so the value can go to the API and
come back without a conversion step anywhere:

| Component          | Model              | Field shows        |
| ------------------ | ------------------ | ------------------ |
| `WxDatePicker`     | `2026-03-14`       | `14.03.2026`       |
| `WxDateTimePicker` | `2026-03-14 09:30` | `14.03.2026 09:30` |
| `WxTimePicker`     | `09:30`            | `09:30`            |

Add `seconds` and both the stored and the shown value gain `:ss`. An empty field is `null`, never an
empty string.

```vue
<script setup lang="ts">
import { ref } from 'vue'

const publishedAt = ref('2026-03-14')
</script>

<template>
  <wx-date-picker v-model="publishedAt" placeholder="Pick a date" />
</template>
```

Override either side when the backend disagrees, or opt out of strings entirely:

```vue
<template>
  <!-- stored as 14/03/2026, shown as 2026.03.14 -->
  <wx-date-picker v-model="value" value-format="dd/MM/yyyy" format="yyyy.MM.dd" />

  <!-- the model holds a Date object -->
  <wx-date-picker v-model="date" value-format="date" />
</template>
```

## Props

All three take the same props; `WxDateTimePicker` and `WxTimePicker` simply fix `type`.

| Prop               | Type                                             | Default     | Description                                            |
| ------------------ | ------------------------------------------------ | ----------- | ------------------------------------------------------ |
| `modelValue`       | `string \| Date \| null`                         | `null`      | Current value                                          |
| `type`             | `'date' \| 'datetime' \| 'time'`                 | `'date'`    | What is being picked                                   |
| `valueFormat`      | `string`                                         | per type    | Format the value is stored in; `'date'` keeps a `Date` |
| `format`           | `string`                                         | per type    | Format the field shows                                 |
| `placeholder`      | `string`                                         | —           | Placeholder text                                       |
| `clearable`        | `boolean`                                        | `true`      | Show the clear button                                  |
| `minDate`          | `string \| Date`                                 | —           | Earliest selectable date                               |
| `maxDate`          | `string \| Date`                                 | —           | Latest selectable date                                 |
| `seconds`          | `boolean`                                        | `false`     | Include seconds                                        |
| `minutesIncrement` | `number`                                         | `1`         | Step of the minutes column                             |
| `is24`             | `boolean`                                        | `true`      | 24-hour clock                                          |
| `weekStart`        | `number`                                         | `1`         | 0 is Sunday, 1 is Monday                               |
| `autoApply`        | `boolean`                                        | `true`      | Apply on pick, with no confirm button                  |
| `textInput`        | `boolean`                                        | `false`     | Allow typing as well as picking                        |
| `teleport`         | `boolean \| string`                              | `true`      | Render the menu in a portal                            |
| `size`             | `'sm' \| 'md' \| 'lg'`                           | `'md'`      | Control height                                         |
| `status`           | `'default' \| 'success' \| 'warning' \| 'error'` | `'default'` | Validation state                                       |
| `disabled`         | `boolean`                                        | `false`     | Disables the field                                     |
| `readonly`         | `boolean`                                        | `false`     | Read-only field                                        |
| `ariaLabel`        | `string`                                         | —           | Label when there is no visible one                     |

**Events:** `update:modelValue`, `change`, `clear`, `open`, `close`.

`teleport` is on by default on purpose: a picker inside a `WxCard` or a scrolling table would
otherwise be clipped by `overflow: hidden`.

## Anything else the library takes

Unknown attributes are passed straight through to the underlying picker, so its whole prop surface
stays reachable without us re-declaring it:

```vue
<script setup lang="ts">
import { uk } from 'date-fns/locale'
</script>

<template>
  <wx-date-picker v-model="value" :locale="uk" :disabled-week-days="[6, 0]" range />
</template>
```

`locale` is a **date-fns locale object**, not a language tag — that is the library's own convention.
`date-fns` comes along with the picker, so it is importable without installing anything else.

## Theming

The library's `--dp-*` variables are mapped onto WebX tokens, so the calendar follows the theme,
dark mode included, without its `dark` prop. Restyling the picker means overriding the same tokens
as everything else — see [Theming](/guide/theming).

## Accessibility

- Inside a [`WxFormItem`](/components/form), the generated id reaches the real `<input>`, so the
  label's `for` works and the error message is linked by `aria-describedby`.
- The error state is passed to the library the way it expects, so the field is marked invalid
  rather than merely coloured.
- Keyboard navigation inside the calendar comes from the library: arrows move, Enter picks, Escape
  closes.

## Bundle size

`@vuepic/vue-datepicker` is a real dependency of `@webx-ui/core`, installed automatically. It is not
bundled into our output — your app resolves and de-duplicates it — but its stylesheet is part of
`@webx-ui/core/style.css`, about 4 kB gzipped, whether or not you use a picker.
