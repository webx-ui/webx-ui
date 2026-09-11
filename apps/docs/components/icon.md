<script setup>
import IconDemo from '../components/demos/IconDemo.vue'
</script>

# Icon

`WxIcon` draws one icon from the built-in set. The icons are 24×24, stroke-based and drawn in
`currentColor`, so an icon takes the colour and the size of the text it sits in — no wrapper, no
per-icon import, nothing to keep in sync.

<IconDemo />

## Usage

```vue
<template>
  <wx-button>
    <template #icon><wx-icon name="trash" /></template>
    Delete
  </wx-button>
</template>
```

## Size and colour

The default size is `1em` — the icon matches the surrounding text. A number is pixels, a string is
any CSS length, and the keywords `sm`, `md`, `lg` follow the type scale. Colour comes from `color`
on the icon or on anything above it:

```vue
<template>
  <wx-icon name="check" />
  <wx-icon name="check" :size="20" />
  <wx-icon name="check" size="lg" />
  <wx-icon name="check" style="color: var(--wx-color-danger)" />
</template>
```

`stroke-width` thins or thickens the drawing; `spin` rotates it, which is what `loader` and
`refresh` are for.

## Accessibility

An icon is decoration by default: it renders `aria-hidden`, because the text beside it already
says what the control does. When the icon _is_ the label — an icon-only button — give it one:

```vue
<template>
  <wx-icon name="trash" label="Delete" />
</template>
```

## Your own icons

`registerIcons` adds to the set; after that the name works anywhere, including in the JSON schema
renderer. Pass the inner markup of a 24×24 `<svg>` — paths, circles, rects:

```ts
import { registerIcons } from '@webx-ui/core'

registerIcons({
  'brand-logo': '<path d="M4 12 12 4l8 8-8 8z" />',
})
```

The markup is injected as-is, so register only icons you author or control — never a string that
arrived from a user or an API.

`iconNames()` lists everything currently registered, built-in first — that is what the gallery
above is built from.

## Props

| Prop          | Type                                       | Default | Description                                    |
| ------------- | ------------------------------------------ | ------- | ---------------------------------------------- |
| `name`        | `string`                                   | —       | Name in the set; unknown names render nothing  |
| `size`        | `'sm' \| 'md' \| 'lg' \| number \| string` | `'1em'` | Box size                                       |
| `strokeWidth` | `number \| string`                         | `1.7`   | Stroke width in the 24×24 grid                 |
| `spin`        | `boolean`                                  | `false` | Rotates the icon                               |
| `label`       | `string`                                   | —       | Accessible name; without it the icon is hidden |

**Exports:** `registerIcons`, `iconNames`, `resolveIcon`, `builtinIcons`.
