<script setup>
import ImageEditorDemo from '../components/demos/ImageEditorDemo.vue'
</script>

# ImageEditor

`WxImageEditor` is a picture, a rectangle over it, and a blob at the end: a crop with the eight
grips everybody knows, quarter turns, mirrorings, and an output size.

<ImageEditorDemo />

## Usage

```vue
<script setup lang="ts">
import { WxImageEditor, type ImageEditorResult } from '@webx-ui/core'

async function save(result: ImageEditorResult) {
  await api.put(`/media/${file.id}`, { file: result.file })
}
</script>

<template>
  <wx-image-editor :src="file.url" :max-width="2000" @save="save" @cancel="back()" />
</template>
```

## From code

Most of the time the editor is not on a page — it is opened over one, from the edit action of a
[FileCard](/components/file-card) or from a form field. That is one `await`:

```ts
import { openImageEditor } from '@webx-ui/core'

const edited = await openImageEditor({ src: file.url, aspect: 16 / 9 })
if (!edited) return

await api.put(`/media/${file.id}`, { file: edited.file })
```

It is [`createModal`](/guide/modals) over the editor in a dialog, and the whole of it is four
lines — worth reading if you are about to write an opener of your own:

```ts
export const openImageEditor = createModal<ImageEditorResult, ImageEditorModalProps>(
  ImageEditorDialog,
  { resolveOn: 'save' },
)
```

It takes everything the component takes, plus `title` and `width` for the panel. Closed without
saving — the ✕, escape, **Cancel** — it answers `undefined`, which is not an error. The promise
carries a `close()`, so a route change can take the panel away.

## It uploads nothing

The answer is a blob and its measurements. Where that goes, what it replaces and whether it
replaces anything at all are the screen's business, which is what lets the same editor crop an
avatar before a form is even submitted and re-cut a file that is already in a library.

```ts
interface ImageEditorResult {
  blob: Blob
  file: File // the same bytes, named — ready for a FormData
  type: string
  width: number
  height: number
  crop: { x: number; y: number; width: number; height: number }
  rotation: number
  flipX: boolean
  flipY: boolean
}
```

The last four are there so a server can arrive at the same picture from the original: turn by
`rotation`, mirror where the flips say so, then cut `crop` out of what you have. That is also why
`crop` is in the pixels of the turned picture rather than the original's — it is the second step,
not the first.

## One resampling

Everything on screen is geometry. The `<img>` is the browser's own, turned by a CSS transform, and
the crop is a box over it; nothing is drawn until **Save**, and then the whole of it — the turn,
the mirroring, the crop and the scaling down — is a single `drawImage` onto a canvas the size of
the result. A chain of canvases would soften a photograph at every link.

## Ratios

`Free`, `Original`, `1:1`, `4:3`, `3:2` and `16:9` are offered; `ratios` replaces the list, and an
entry can be a number with a label of its own:

```vue
<wx-image-editor :src="src" :ratios="['free', 1, 21 / 9, { value: 2.35, label: 'Cinema' }]" />
```

A number on its own is named after itself — `1.7778` reads as `16:9`, and a ratio that is nobody's
convention keeps its decimals.

`aspect` is the other way round: it locks the crop to one ratio and takes the picker away. That is
the avatar case, and the one where a screen knows better than the reader what shape it needs.

```vue
<wx-image-editor :src="src" :aspect="1" :max-width="256" format="image/jpeg" />
```

A locked crop stays locked through a turn: the output has to keep its shape, so the rectangle is
laid out afresh in the picture's new orientation rather than coming out sideways.

## The size it is written at

**Output** is the size of the file you will get — not the crop on screen and not the picture
behind it, which is why it is named rather than left as two numbers to be guessed at. It starts as
the crop's own pixels and follows the crop as it is dragged; type into either field and the other
follows, since the shape is the crop's to decide.

It never scales up: a crop cannot be asked for more pixels than it has. `:resizable="false"` takes
the fields away and leaves the reading.

`maxWidth` and `maxHeight` cap it from the other side, and a bigger crop is scaled down to fit
inside them — the line to write on a media library, where what arrives from a phone is four
thousand pixels wide and what the site needs is twelve hundred.

## The format

`auto` keeps a PNG a PNG and a WebP a WebP, and writes everything else as a JPEG. It matters
because JPEG has no transparency: a PNG written as one comes back with black where it used to be
see-through. Where the format is forced to JPEG the canvas is painted with `background` first, so
that transparency turns into white rather than black.

The name follows the format. `hero.png` written as a JPEG is `hero.jpg`, since the extension is
part of what the file is.

## Cross-origin pictures

The `<img>` is `crossorigin="anonymous"`. A canvas that has drawn a picture from a host that sent
no CORS header cannot be read back — the export would fail at the very end, after the reader has
done the work. This way it fails at loading, where it can be seen and where the `error` event says
so. `:cross-origin="''"` turns it off for a host you know you will never export from.

## Keyboard

The crop is focusable. The arrow keys move it by ten pixels of the picture, `Alt` and an arrow by
one, and `Shift` and an arrow resize it from the bottom-right — which, under a locked ratio, keeps
the ratio.

## Props

| Prop          | Type                                                    | Default       | Description                                                |
| ------------- | ------------------------------------------------------- | ------------- | ---------------------------------------------------------- |
| `src`         | `string \| Blob`                                        | —             | A URL, or the `File` an upload field handed you            |
| `fileName`    | `string`                                                | from `src`    | Name for the result; its extension follows the format      |
| `aspect`      | `number`                                                | —             | Locks the crop and hides the picker                        |
| `ratios`      | `(ImageEditorRatio \| ImageEditorRatioOption)[]`        | six of them   | The ratios offered                                         |
| `ratio`       | `ImageEditorRatio`                                      | the first     | Which one it opens on                                      |
| `rotatable`   | `boolean`                                               | `true`        | Quarter turns, left and right                              |
| `flippable`   | `boolean`                                               | `true`        | Mirroring, across and down                                 |
| `resizable`   | `boolean`                                               | `true`        | The output-size fields                                     |
| `maxWidth`    | `number`                                                | —             | Largest output; a bigger crop is scaled down               |
| `maxHeight`   | `number`                                                | —             | The same, for the height                                   |
| `minSize`     | `number`                                                | `16`          | Smallest crop, in the picture's own pixels                 |
| `format`      | `'auto' \| 'image/jpeg' \| 'image/png' \| 'image/webp'` | `'auto'`      | What to write                                              |
| `quality`     | `number`                                                | `0.92`        | For the formats that have one                              |
| `background`  | `string`                                                | `'#ffffff'`   | Painted behind a picture written to a format with no alpha |
| `crossOrigin` | `'anonymous' \| 'use-credentials' \| ''`                | `'anonymous'` | `crossorigin` on the `<img>`                               |
| `footer`      | `boolean`                                               | `true`        | Its own Cancel and Save                                    |
| `disabled`    | `boolean`                                               | `false`       | Everything is shown and nothing can be done                |

The labels — `saveLabel`, `cancelLabel`, `resetLabel`, `rotateLeftLabel`, `rotateRightLabel`,
`flipHorizontalLabel`, `flipVerticalLabel`, `ratioLabel`, `cropLabel`, `outputLabel`, `outputHint`,
`widthLabel`, `heightLabel`, `freeLabel`, `originalLabel`, `errorText` — are all English by default
and all replaceable.

**Events:** `save` (`ImageEditorResult`); `cancel`; `load` (`{ width, height }`); `error`
(`unknown`); `crop` (the rectangle, as it is dragged).

**Exposed:** `apply()` draws the result, emits `save` and answers with it; `reset()` goes back to
the whole picture the right way up; `crop` is the rectangle as it stands. `apply()` is what a page
with a footer of its own calls, with `:footer="false"` on the editor — which is exactly what
`openImageEditor` does.

## Accessibility

The toolbar is buttons with names on them, the crop is a focusable group with a label and the
keyboard equivalent of the drag, and the picture itself is decorative: it is the thing being
edited, not content, and the name of the file is not a description of it.

There is no keyboard way to draw a new crop from nothing, because there is no need for one — the
crop is always there, and the arrow keys move and resize the one that is.
