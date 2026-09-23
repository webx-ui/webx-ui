<script setup>
import IconPickerDemo from '../components/demos/IconPickerDemo.vue'
</script>

# IconPicker

`WxIconPicker` is a field that holds the name of an icon and shows the icon itself. The box is
the search: typing filters the set, clicking an icon chooses it.

<IconPickerDemo />

## Usage

```vue
<script setup lang="ts">
import { ref } from 'vue'

const icon = ref<string | null>('grid')
</script>

<template>
  <wx-icon-picker v-model="icon" clearable />
</template>
```

The model is an icon name, or `null` when the field is empty — the same string `<wx-icon>` takes.
Icons a site registered with `registerIcons` are in the panel too: the list is `iconNames()`,
built-ins and registered ones alike.

## Why not a text field

An unknown name draws nothing at all. `WxIcon` has no placeholder and no warning for a name the
set does not have, so `file-text` where the set says `file-txt` is a label that has quietly moved
to where the picture should have been — and nothing in the interface says so. This field cannot
hold a name like that: what is typed is a filter, and the value only ever becomes a name that was
picked out of the panel. Typing a name in full and pressing <kbd>Enter</kbd> picks it, since by
then it is the only match left.

A name can still arrive from elsewhere — a type imported from a file, a field that was a text box
last year, an icon a site registered and then stopped registering. The field marks it: the
preview shows a warning glyph and says so in its tooltip, rather than the same emptiness as "no
icon chosen".

## Props

| Prop          | Type                   | Default                  | Description                                  |
| ------------- | ---------------------- | ------------------------ | -------------------------------------------- |
| `modelValue`  | `string \| null`       | `null`                   | Icon name                                    |
| `clearable`   | `boolean`              | `false`                  | Show a button that empties the field         |
| `placeholder` | `string`               | `'Search icons'`         | Placeholder of the search box                |
| `emptyText`   | `string`               | `'No icon of that name'` | Shown when nothing matches                   |
| `unknownText` | `string`               | see below                | Tooltip when the held name is not in the set |
| `clearLabel`  | `string`               | `'Clear'`                | Accessible name of the clear button          |
| `teleport`    | `boolean`              | `true`                   | Render the panel in a portal                 |
| `disabled`    | `boolean`              | —                        | Inherited from the form field when unset     |
| `size`        | `'sm' \| 'md' \| 'lg'` | —                        | Inherited from the form field when unset     |
| `status`      | `'default' \| 'error'` | —                        | Inherited from the form field when unset     |
| `ariaLabel`   | `string`               | —                        | Accessible name when there is no `<label>`   |

## Events

| Event               | Payload          | When                                    |
| ------------------- | ---------------- | --------------------------------------- |
| `update:modelValue` | `string \| null` | An icon is picked, or the field emptied |
| `change`            | `string \| null` | The same, for forms that listen for it  |
