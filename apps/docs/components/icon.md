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

## Icons from another set

A set like [Bootstrap Icons](https://icons.getbootstrap.com/) is around two thousand drawings, and
the ones an admin panel actually shows are a dozen. The library does not ship them: bundling all of
them to use twelve is 1.4 MB nobody reads, and an icon that lives in your app is one you can add
today instead of after a release of `@webx-ui/core`.

The two sets do not agree on a grid — Bootstrap's are 16×16 and filled, the built-ins 24×24 and
stroked — and reconciling them is one line of SVG. Nest the whole thing, `viewBox` and all: an
inner `<svg>` with no width or height fills its parent, and scales its own art to do it.

```ts
import { registerIcons } from '@webx-ui/core'

registerIcons({
  // Straight out of bootstrap-icons/icons/gear-fill.svg, with its own viewBox kept.
  'bi-gear':
    '<svg viewBox="0 0 16 16" fill="currentColor" stroke="none"><path d="M9.405 1.05c-.413-1.4-2.397-1.4-2.81 0…" /></svg>',
})
```

`<wx-icon name="bi-gear" />` from then on, in the colour and the size of whatever it sits in. For a
whole folder of them at once, let the bundler read the files:

```ts
/* Vite. Every name comes out as `bi-<file>`: `bi-gear-fill`, `bi-trash`, … */
const files = import.meta.glob('/node_modules/bootstrap-icons/icons/*.svg', {
  query: '?raw',
  import: 'default',
  eager: true,
}) as Record<string, string>

registerIcons(
  Object.fromEntries(
    Object.entries(files).map(([path, svg]) => [
      `bi-${path.split('/').pop()!.replace('.svg', '')}`,
      /*
       * The file's own `width="16" height="16"` is what has to go: left on, the nested
       * drawing keeps sixteen of the outer twenty-four units and sits in the corner.
       */
      svg.replace(/\s(?:width|height)="[^"]*"/g, '').replace('<svg', '<svg stroke="none"'),
    ]),
  ),
)
```

Both forms end up as a nested `<svg>`, which is the whole trick: an inner `<svg>` with no width or
height fills its parent and scales its own `viewBox` to do it. The same works for any set that
draws on a grid of its own.

## Props

| Prop          | Type                                       | Default | Description                                    |
| ------------- | ------------------------------------------ | ------- | ---------------------------------------------- |
| `name`        | `string`                                   | —       | Name in the set; unknown names render nothing  |
| `size`        | `'sm' \| 'md' \| 'lg' \| number \| string` | `'1em'` | Box size                                       |
| `strokeWidth` | `number \| string`                         | `1.7`   | Stroke width in the 24×24 grid                 |
| `spin`        | `boolean`                                  | `false` | Rotates the icon                               |
| `label`       | `string`                                   | —       | Accessible name; without it the icon is hidden |

**Exports:** `registerIcons`, `iconNames`, `resolveIcon`, `builtinIcons`.
