<script setup>
import SpaceDemo from '../components/demos/SpaceDemo.vue'
</script>

# Space

`WxSpace` puts an even gap between the things inside it — a row of buttons, a toolbar, a stack of
fields. It is the answer to the margin that would otherwise be added to a button "just this once",
and it keeps that decision in one place.

<SpaceDemo />

## Usage

```vue
<template>
  <wx-space>
    <wx-button variant="outline">Cancel</wx-button>
    <wx-button type="primary">Save</wx-button>
  </wx-space>
</template>
```

## Size

A keyword follows the spacing scale; a number is pixels; anything else is used as a CSS length:

```vue
<template>
  <wx-space size="sm">…</wx-space>
  <wx-space :size="20">…</wx-space>
  <wx-space size="2rem">…</wx-space>
</template>
```

## Arranging the row

`justify` distributes along the row, `align` lines things up across it. A toolbar is usually both:

```vue
<template>
  <wx-space justify="between" align="center">
    <wx-input v-model="query" size="sm" placeholder="Search" />
    <wx-button type="primary">New page</wx-button>
  </wx-space>
</template>
```

`fill` divides the row evenly between its children — equal-width buttons in a mobile toolbar:

```vue
<template>
  <wx-space fill>
    <wx-button variant="outline">Reject</wx-button>
    <wx-button type="primary">Approve</wx-button>
  </wx-space>
</template>
```

## Stacking

`direction="vertical"` stacks instead. A column never wraps, so `wrap` is ignored there:

```vue
<template>
  <wx-space direction="vertical" size="lg">
    <wx-card title="Content">…</wx-card>
    <wx-card title="SEO">…</wx-card>
  </wx-space>
</template>
```

For a grid that reflows by breakpoint, reach for [Row and Col](/components/grid) instead — `Space`
is for one line or one stack, not for a layout.

## Props

| Prop        | Type                                                                | Default        | Description                     |
| ----------- | ------------------------------------------------------------------- | -------------- | ------------------------------- |
| `direction` | `'horizontal' \| 'vertical'`                                        | `'horizontal'` | Along the line or down the page |
| `size`      | `'xs' \| 'sm' \| 'md' \| 'lg' \| 'xl' \| number \| string`          | `'md'`         | Gap between children            |
| `align`     | `'start' \| 'center' \| 'end' \| 'baseline' \| 'stretch'`           | —              | Cross-axis alignment            |
| `justify`   | `'start' \| 'center' \| 'end' \| 'between' \| 'around' \| 'evenly'` | —              | Main-axis distribution          |
| `wrap`      | `boolean`                                                           | `true`         | Lets a horizontal row wrap      |
| `fill`      | `boolean`                                                           | `false`        | Children share the row evenly   |
| `inline`    | `boolean`                                                           | `false`        | Renders as `inline-flex`        |
| `as`        | `string \| Component`                                               | `'div'`        | The element to render           |

**Slots:** `default` — the children.
