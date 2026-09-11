<script setup>
import IndicatorDemo from '../components/demos/IndicatorDemo.vue'
</script>

# Indicator

`WxIndicator` pins a count — or a plain dot — to whatever it wraps: a button, an icon, a link, a
menu row. It is Element Plus's `el-badge`; the label-shaped badge lives at
[Badge](/components/badge).

<IndicatorDemo />

## Usage

```vue
<template>
  <wx-indicator :value="2" label="2 items in cart">
    <wx-button round>
      <template #icon><wx-icon name="cart" /></template>
      Cart
    </wx-button>
  </wx-indicator>
</template>
```

The mark is positioned over the corner of the wrapped element and ignores pointer events, so the
button underneath stays fully clickable.

## Counts

A number above `max` is shown as `99+`. A zero hides the mark, unless `show-zero` says otherwise —
the usual thing for an inbox that should look empty when it is.

```vue
<template>
  <wx-indicator :value="128" :max="999">…</wx-indicator>
  <wx-indicator :value="0" show-zero>…</wx-indicator>
</template>
```

## Dots

Without a number, `dot` marks "something is new here" — the shape for a bell or a menu item:

```vue
<template>
  <wx-indicator dot>
    <wx-icon name="bell" :size="24" label="Notifications" />
  </wx-indicator>
</template>
```

## Standing alone

With no wrapped element, the mark is the whole component — a count at the end of a menu row:

```vue
<template>
  <span>Orders <wx-indicator :value="12" type="neutral" /></span>
</template>
```

## Placement and the ring

`placement` picks the corner, `offset` nudges it in pixels. The mark is drawn with a ring in the
colour of the surface behind it; on a coloured sidebar, tell it which colour that is:

```vue
<template>
  <wx-indicator
    :value="3"
    placement="top-left"
    :offset="[2, -2]"
    style="--wx-indicator-ring: #1f2937"
  >
    …
  </wx-indicator>
</template>
```

## Props

| Prop        | Type                                                                     | Default       | Description                               |
| ----------- | ------------------------------------------------------------------------ | ------------- | ----------------------------------------- |
| `value`     | `number \| string`                                                       | —             | The count or short text                   |
| `max`       | `number`                                                                 | `99`          | Numbers above it show as `99+`            |
| `dot`       | `boolean`                                                                | `false`       | Plain dot instead of a number             |
| `showZero`  | `boolean`                                                                | `false`       | Keep the mark at 0                        |
| `placement` | `'top-right' \| 'top-left' \| 'bottom-right' \| 'bottom-left'`           | `'top-right'` | Corner it sits in                         |
| `offset`    | `[number, number]`                                                       | —             | Pixel nudge, right and down               |
| `type`      | `'danger' \| 'primary' \| 'success' \| 'warning' \| 'info' \| 'neutral'` | `'danger'`    | Colour                                    |
| `hidden`    | `boolean`                                                                | `false`       | Hides the mark, keeps the wrapped element |
| `label`     | `string`                                                                 | —             | Accessible text for the mark              |

**Slots:** `default` — the element the mark is pinned to; `mark` — replaces the count.
