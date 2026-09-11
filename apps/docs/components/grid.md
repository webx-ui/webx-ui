<script setup>
import GridDemo from '../components/demos/GridDemo.vue'
</script>

# Grid

`WxRow` and `WxCol` are the 24-column grid a screen is laid out on: a form in two columns, a row of
statistic cards, a filter bar that becomes a stack on a phone.

For the shell around the screen, see [Layout](/components/layout); for a single row or stack of
controls, [Space](/components/space) is less machinery.

<GridDemo />

## Usage

```vue
<template>
  <wx-row :gutter="16">
    <wx-col :span="12">Left half</wx-col>
    <wx-col :span="12">Right half</wx-col>
  </wx-row>
</template>
```

`span` counts columns out of 24, so twelve is a half, eight a third, six a quarter.

## Gutters

`gutter` is the gap between columns, in pixels or as any CSS length. Once the columns wrap, the
gap between lines is the same — unless `gutter-y` says otherwise:

```vue
<template>
  <wx-row :gutter="24" :gutter-y="12">…</wx-row>
</template>
```

The gutter is half a gutter of padding on each column, pulled back by a negative margin on the
row, rather than a `column-gap`. That is what keeps `span="12"` an honest half: a gap subtracted
from the track would leave two halves wider than the line they sit on.

## Responsive

`span` is the base and holds at every width. `sm`, `md`, `lg` and `xl` override it from 640, 768,
1024 and 1280px up — mobile first, so a column that is full width on a phone and a quarter on a
desktop reads in that order:

```vue
<template>
  <wx-col :span="24" :md="12" :lg="6">…</wx-col>
</template>
```

Each breakpoint also takes an offset of its own:

```vue
<template>
  <wx-col :span="24" :lg="{ span: 16, offset: 4 }">…</wx-col>
</template>
```

## Arranging the row

```vue
<template>
  <wx-row justify="between" align="center" :wrap="false">…</wx-row>
</template>
```

`order` moves a column without moving it in the markup — the summary panel that belongs first on a
phone and last on a desktop:

```vue
<template>
  <wx-row>
    <wx-col :span="24" :lg="16" :order="2">The form</wx-col>
    <wx-col :span="24" :lg="8" :order="1">The summary</wx-col>
  </wx-row>
</template>
```

## Row

| Prop      | Type                                                                | Default  | Description                |
| --------- | ------------------------------------------------------------------- | -------- | -------------------------- |
| `gutter`  | `number \| string`                                                  | `16`     | Gap between columns        |
| `gutterY` | `number \| string`                                                  | `gutter` | Gap between wrapped lines  |
| `justify` | `'start' \| 'center' \| 'end' \| 'between' \| 'around' \| 'evenly'` | —        | Distribution along the row |
| `align`   | `'start' \| 'center' \| 'end' \| 'stretch' \| 'baseline'`           | —        | Alignment across it        |
| `wrap`    | `boolean`                                                           | `true`   | Lets columns wrap          |
| `as`      | `string \| Component`                                               | `'div'`  | The element to render      |

## Col

| Prop     | Type                                           | Default | Description                       |
| -------- | ---------------------------------------------- | ------- | --------------------------------- |
| `span`   | `number`                                       | `24`    | Columns out of 24, at every width |
| `offset` | `number`                                       | —       | Empty columns before the cell     |
| `sm`     | `number \| { span?: number; offset?: number }` | —       | From 640px up                     |
| `md`     | `number \| { span?: number; offset?: number }` | —       | From 768px up                     |
| `lg`     | `number \| { span?: number; offset?: number }` | —       | From 1024px up                    |
| `xl`     | `number \| { span?: number; offset?: number }` | —       | From 1280px up                    |
| `order`  | `number`                                       | —       | Visual order within the row       |
| `as`     | `string \| Component`                          | `'div'` | The element to render             |

Both take a `default` slot and nothing else.

## How the breakpoints work

Every width a column is given is written out as a CSS variable — `--wx-col-span`,
`--wx-col-span-md` and so on — and the stylesheet holds one rule per breakpoint, each falling back
to the one below it. Nothing is generated per span, so the grid costs four rules rather than the
several hundred a class-per-span grid ships, and a column can be adjusted from the outside:

```vue
<template>
  <wx-col style="--wx-col-span: 10">Ten columns, set by hand</wx-col>
</template>
```
