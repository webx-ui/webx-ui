<script setup>
import ImageDemo from '../components/demos/ImageDemo.vue'
</script>

# Image

`WxImage` is a picture that holds its place, waits for the screen, and says so when it is missing.

<ImageDemo />

## Usage

```vue
<template>
  <wx-image :src="product.photo" :alt="product.name" :width="200" :height="130" radius="12px" />
</template>
```

Give it a `width` and a `height`. A picture with no box reserved is a page that jumps when the
picture lands, and every list of them jumps once per row.

## Nothing moves

The placeholder is **underneath** the picture rather than instead of it, so when the picture
arrives it lands on top and the layout never changes. A `placeholder` — a tiny version of the same
picture, or a flat colour — is blurred and scaled up behind it, which is the cheapest way to make a
slow connection feel like it is arriving rather than missing.

A picture that fails is replaced by a glyph, and `@error` fires once. Changing `src` starts over:
the last picture's failure says nothing about this one.

## Waiting for the screen

`lazy` is on by default. A media library is a hundred pictures and a reader looks at four, so the
browser is told to fetch only what is near the viewport — native `loading="lazy"`, no observer of
ours, no library.

Turn it off for anything above the fold: a lazy hero image is a hero image that arrives late.

## Preview

`preview` puts the picture behind a click and opens it full size in a
[Dialog](/components/dialog).

## Props

| Prop           | Type                                                       | Default            | Description                      |
| -------------- | ---------------------------------------------------------- | ------------------ | -------------------------------- |
| `src`          | `string`                                                   | —                  | The picture                      |
| `alt`          | `string`                                                   | —                  | Alternative text                 |
| `fit`          | `'cover' \| 'contain' \| 'fill' \| 'none' \| 'scale-down'` | `'cover'`          | How it fills its box             |
| `width`        | `number \| string`                                         | —                  | Width of the box                 |
| `height`       | `number \| string`                                         | —                  | Height of the box                |
| `radius`       | `number \| string`                                         | —                  | Corner radius                    |
| `lazy`         | `boolean`                                                  | `true`             | Wait until it is near the screen |
| `placeholder`  | `string`                                                   | —                  | A tiny picture shown behind it   |
| `preview`      | `boolean`                                                  | `false`            | Opens full size when clicked     |
| `previewLabel` | `string`                                                   | `'View full size'` | Name of that button              |

**Events:** `load` (`Event`), `error` (`Event`).

**Slots:** `placeholder`; `error`.

## Accessibility

`alt` is the picture's job, not the component's: describe what it shows, or pass `alt=""` for
decoration so a screen reader skips it. A product photograph in a row that already names the
product is decoration.
