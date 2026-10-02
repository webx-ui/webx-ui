<script setup>
import LightboxDemo from '../components/demos/LightboxDemo.vue'
</script>

# Lightbox

`WxLightbox` is a gallery over the whole screen: one picture at a time, arrows and keys between
them, a strip of the rest, zoom and pan, and a player behind a video's poster.

<LightboxDemo />

## Three ways to open it

**A group of pictures.** Put `<wx-image preview>` inside a `<wx-image-group>`, and any of them
opens one gallery of all of them, in the order they stand on the page:

```vue
<template>
  <wx-image-group class="thumbs">
    <wx-image v-for="image in images" :key="image.id" :src="image.url" :alt="image.alt" preview />
  </wx-image-group>
</template>
```

The group is a component, not a shared name on each picture: two cards on one screen that both
called their pictures "photos" would otherwise become one gallery. The group renders a `div`
(`tag` changes that) and keeps `class` and `style`; every other attribute — `loop`,
`thumbnails`, the labels — goes to its lightbox.

**A cover with a gallery behind it.** One picture shows, all of them open:

```vue
<template>
  <wx-image :src="product.cover" preview :preview-list="product.images" />
</template>
```

It opens on the item whose address is the cover's `src`, or on `preview-start`.

**From code**, the way [`openModal`](/guide/modals) and
[`openImageEditor`](/components/image-editor#from-code) work:

```ts
import { openLightbox } from '@webx-ui/core'

openLightbox(product.images, 2)
openLightbox(product.images, { start: 2, loop: true, nextLabel: t('Next') })
```

It answers nothing: the promise settles with `undefined` when the gallery is closed, and carries
`close()` for one that should not outlive a route.

And the component itself, for a screen that holds the state:

```vue
<template>
  <wx-lightbox v-model:open="open" v-model:index="index" :items="images" />
</template>
```

## Items

An item is the address of a picture, or an object:

```ts
interface LightboxItem {
  src?: string // the picture; for a video, its poster
  alt?: string // and the caption, when there is no caption
  caption?: string
  original?: string // where "Open the original" goes; default: src
  thumb?: string // for the strip, and for the moment before src arrives
  video?: string | { src?: string; embed?: string }
}
```

A small `thumb` matters twice: the strip loads it instead of every full picture, and the stage
shows it blurred underneath while the full one is on its way.

## Video

An item with a `video` is shown as its picture with a play button on it. Nothing is fetched from
the video's host until that button is pressed — a gallery of ten YouTube videos is ten pictures,
not ten players.

- A string is a YouTube address in any of its forms (`watch?v=`, `youtu.be/`, `shorts/`, `embed/`)
  or a file — MP4 or WebM, played by the browser's own `<video>`.
- `{ embed }` is any address for an iframe; a YouTube one is still recognised.
- `{ src }` is a file, whatever its address looks like.

YouTube plays from `youtube-nocookie.com`. Moving to another item stops the video, and zoom is not
offered on one.

## Getting around

| On a computer                      | On a phone           |
| ---------------------------------- | -------------------- |
| ← → between items, Home / End      | swipe left or right  |
| the arrows at the sides            | —                    |
| the strip of thumbnails            | —                    |
| double click: fit ↔ close up       | double tap           |
| the wheel, + and −, 0 to fit again | pinch                |
| drag a picture that is zoomed in   | drag                 |
| Esc, or the ✕                      | swipe down, or the ✕ |

A double click goes to actual pixels — or to twice the fit, for a picture smaller than the screen —
and back. `max-zoom` is how far the rest of the ways go, as a multiple of the size on screen; a
picture whose actual pixels are further than that can still be seen at them. Zoom stays where it
was put under the cursor or between the fingers.

The arrows and the strip are left out on a narrow screen: a phone swipes, and they would only
cover the picture. `loop` makes the last item lead to the first.

## Looks

The dimming is its own token, `--wx-bg-lightbox`: nearly opaque, and dark in both themes,
because a photograph judged on a page that shows through it is judged on the wrong colours.
Everything with words or buttons on it — the counter, the tools, the caption, the arrows — sits on
`--wx-bg-surface`. The layer is `--wx-z-index-overlay` and the gallery `--wx-z-index-dialog`,
like a [Dialog](/components/dialog).

## Props

| Prop            | Type                                                   | Default                | Description                             |
| --------------- | ------------------------------------------------------ | ---------------------- | --------------------------------------- |
| `items`         | `(string \| LightboxItem)[]`                           | —                      | What is shown                           |
| `open`          | `boolean`                                              | `false`                | `v-model:open`                          |
| `index`         | `number`                                               | `0`                    | `v-model:index` — the item on screen    |
| `loop`          | `boolean`                                              | `false`                | Past the last comes the first           |
| `thumbnails`    | `boolean`                                              | more than one item     | The strip along the bottom              |
| `maxZoom`       | `number`                                               | `4`                    | How far zoom goes, × the size on screen |
| `original`      | `boolean`                                              | `true`                 | The "Open the original" link            |
| `ariaLabel`     | `string`                                               | `'Gallery'`            | Name of the gallery                     |
| `counterText`   | `string \| ((index: number, total: number) => string)` | `'{index} of {total}'` | The counter, and the strip's names      |
| `prevLabel`     | `string`                                               | `'Previous'`           |                                         |
| `nextLabel`     | `string`                                               | `'Next'`               |                                         |
| `closeLabel`    | `string`                                               | `'Close'`              |                                         |
| `zoomInLabel`   | `string`                                               | `'Zoom in'`            |                                         |
| `zoomOutLabel`  | `string`                                               | `'Zoom out'`           |                                         |
| `originalLabel` | `string`                                               | `'Open the original'`  |                                         |
| `playLabel`     | `string`                                               | `'Play'`               |                                         |

**Events:** `update:open`, `update:index`, `change` (`index`) — the item changed, by any means.

On `WxImage`: `preview`, `preview-list`, `preview-start`; see [Image](/components/image#preview).
On `WxImageGroup`: `tag` (`'div'`), and everything above but `items`, `open` and `index`.

## Accessibility

The gallery is a modal dialog: focus is held inside while it is open and goes back to the picture
that opened it. Focus starts on the gallery itself rather than on a button, so the arrow keys work
at once. The counter is announced as it changes, every button has a name, and the strip's buttons
say which item they are — with `aria-current` on the one on screen. The fades are off under
`prefers-reduced-motion`.
