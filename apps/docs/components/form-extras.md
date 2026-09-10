<script setup>
import FormExtrasDemo from '../components/demos/FormExtrasDemo.vue'
</script>

# Secondary form controls

`WxRate`, `WxSlider`, `WxTagsInput`, `WxDateRangePicker` and `WxColorPicker` — the fields an admin
needs less often than an input, but needs badly when it needs them.

<FormExtrasDemo />

## WxRate

Stars, the way a product review shows them.

```vue
<template>
  <wx-rate v-model="rating" />
  <wx-rate v-model="rating" allow-half show-value />
  <wx-rate :model-value="4" readonly />
</template>
```

| Prop         | Type      | Default | Description                            |
| ------------ | --------- | ------- | -------------------------------------- |
| `modelValue` | `number`  | `0`     | Current rating                         |
| `max`        | `number`  | `5`     | How many stars                         |
| `allowHalf`  | `boolean` | `false` | Halves, set from the left of a star    |
| `clearable`  | `boolean` | `true`  | Clicking the current value resets to 0 |
| `showValue`  | `boolean` | `false` | Print the number beside the stars      |
| `readonly`   | `boolean` | `false` | Display only                           |

Arrow keys change the rating by one step, Home clears it and End maxes it out.

## WxSlider

```vue
<template>
  <wx-slider v-model="volume" :min="0" :max="100" show-value />
  <wx-slider v-model="price" range :min="0" :max="1000" :step="50" />
</template>
```

| Prop                    | Type                     | Default   | Description                          |
| ----------------------- | ------------------------ | --------- | ------------------------------------ |
| `modelValue`            | `number \| number[]`     | `null`    | Value, or a pair when `range`        |
| `range`                 | `boolean`                | `false`   | Two thumbs; the model becomes a pair |
| `min` / `max` / `step`  | `number`                 | `0/100/1` | Bounds and stepping                  |
| `minStepsBetweenThumbs` | `number`                 | `0`       | Smallest gap between the thumbs      |
| `marks`                 | `Record<number, string>` | —         | Ticks under the track                |
| `showValue`             | `boolean`                | `false`   | Print the value beside the track     |

A single slider keeps a plain number in the model rather than a one-element array — that is what a
caller wants to store.

## WxTagsInput

Free tags with suggestions. Enter adds what was typed, Backspace on an empty field highlights the
last tag and removes it on the second press.

```vue
<template>
  <wx-tags-input v-model="tags" :suggestions="found" @search="load" />
</template>
```

| Prop          | Type       | Default | Description                                  |
| ------------- | ---------- | ------- | -------------------------------------------- |
| `modelValue`  | `string[]` | `[]`    | The tags                                     |
| `suggestions` | `string[]` | `[]`    | Offered while typing — refresh from `search` |
| `allowCreate` | `boolean`  | `true`  | Enter adds a tag that is not in the list     |
| `duplicates`  | `boolean`  | `false` | Allow the same tag twice                     |
| `max`         | `number`   | —       | Largest number of tags                       |

**Events:** `update:modelValue`, `change`, `search` (`string`).

The `search` event carries every keystroke, so a backend lookup is a matter of refreshing
`suggestions` in the handler.

## WxDateRangePicker

```vue
<template>
  <wx-date-range-picker v-model="period" />
</template>
```

The model is `[start, end]` in `yyyy-MM-dd`, or `null` while nothing is picked. Two months are shown
side by side, so a period that crosses a month boundary takes one pass rather than two — `months`
changes that.

Everything else matches [DatePicker](/components/date-picker): `valueFormat`, `format`, `minDate`,
`maxDate`, `teleport`, `size`, `status`.

## WxColorPicker

A hex field with the colour in it; clicking opens the picker.

```vue
<template>
  <wx-color-picker v-model="brand" :presets="['#427edd', '#21c36d', '#f14646']" clearable />
</template>
```

| Prop         | Type             | Default | Description                   |
| ------------ | ---------------- | ------- | ----------------------------- |
| `modelValue` | `string \| null` | `null`  | Hex colour, lower case        |
| `presets`    | `string[]`       | `[]`    | Swatches under the picker     |
| `clearable`  | `boolean`        | `false` | Show a button that empties it |

Typing is free-form and only a complete hex reaches the model, so a half-typed `#42` does not wipe
the value. On blur the field either shows a valid colour or falls back to the last one; a missing
`#` is added.
