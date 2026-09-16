# The file manager

`@webx-ui/module-media` is a section of the panel, and it is also three things a form can use
without ever navigating to that section. This page is about the second half — the first is
`media()` in `createAdmin({ modules: [...] })`, and everything it needs on the server lives in the
`webx-ui/module-media` Composer package.

Nothing here works without that server half. The picker is a view of a real library.

## A file, asked for

```ts
import { openMediaPicker } from '@webx-ui/module-media'

const file = await openMediaPicker({ accept: 'image' })

if (file) {
  // file.path is what to store, file.url is where to show it
}
```

It is one of the [dialogs from code](/guide/modals): the library is mounted outside the app and
the promise answers with the chosen `MediaFile`, or with `undefined` when the person closed it.
`accept` narrows what can be picked — `'image'`, `'video'`, `'audio'`, `'document'`, or `null`
for everything.

For more than one:

```ts
import { openMediaFiles } from '@webx-ui/module-media'

const files = await openMediaFiles({ accept: 'image', max: 10 })
```

The dialog turns on the same rubber-band selection the manager already uses for its batch
operations, and the button at the bottom counts what is chosen. A second function rather than a
flag on the first one, because the answer is a different shape — `MediaFile[]` against
`MediaFile` — and a signature says that more plainly than a conditional type does.

`max` is kept by the dialog, which says how many are left, and again by the server: the limit
belongs to the field's schema, and a request that never opened a dialog has to meet it too.

## The whole library, without leaving the screen

```ts
import { openMediaLibrary } from '@webx-ui/module-media'

await openMediaLibrary()
```

The same manager, with folders, uploads, renaming and the image editor — for a screen that needs
to put files in order without losing what it was doing.

## A picture on a form

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { WxMediaField, type MediaValue } from '@webx-ui/module-media'

const cover = ref<MediaValue | null>(null)
</script>

<template>
  <wx-media-field v-model="cover" label="Cover" />
</template>
```

Two states and nothing in between. Empty, the frame opens the library. Filled, it shows the
picture with two things you can do to it:

- **Captions** — a popover with `alt` and `title`, and a button to swap the picture. Both fields
  are [localized](/components/locales), because the site is.
- **Clear** — asks first, and then empties the field. It does **not** delete the file: the same
  picture is very likely used by another record, and this is the one place somebody would expect
  otherwise.

`MediaValue` is `{ path, url?, alt?, title? }`, and **`path` is the only part worth storing**.

The captions belong to the entity, not to the file: one picture used by two articles needs two
`alt`s, so the library does not carry them. The address is worked out from `path` when the page is
drawn, which is why moving the library to another disk — or putting a CDN in front of it — changes
nothing that was written before. A field given nothing but a key looks the file up to draw it.

Pass `:captions="false"` for a decorative picture, `height` to size the frame, and `accept` to
pick something other than an image.

## Several of them

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { WxGalleryField, type MediaValue } from '@webx-ui/module-media'

const shots = ref<MediaValue[]>([])
</script>

<template>
  <wx-gallery-field v-model="shots" label="Photographs" :max="12" />
</template>
```

A grid of thumbnails in the order somebody dragged them into, each with the captions popover the
single field has. **Add** opens the library with multiple selection on, so twelve pictures are one
trip rather than twelve.

`WxFilesField` is the same list laid out as rows of file cards — the glyph of the extension, the
name, the size — for the downloads hanging off a page. `WxFileField` is one of those cards on its
own: the same `MediaValue | null` as `WxMediaField`, for a document that has no `alt` worth asking
for and no preview worth framing.

| Export           | Screen type  | Value                | What it draws                    |
| ---------------- | ------------ | -------------------- | -------------------------------- |
| `WxMediaField`   | `wx-media`   | `MediaValue \| null` | one picture in a frame           |
| `WxGalleryField` | `wx-gallery` | `MediaValue[]`       | a grid of thumbnails             |
| `WxFileField`    | `wx-file`    | `MediaValue \| null` | one file card: glyph, name, size |
| `WxFilesField`   | `wx-files`   | `MediaValue[]`       | those cards a line each          |

The type names are what `media()` registers on [a described screen](/guide/screens), and what the
server half registers for storing the values — so a screen that writes `"type": "wx-gallery"` gets
this field and these rules without anyone wiring the two together.

**Three names rather than `wx-media` with `multiple`.** The type is what an author picks from a
list and what an agent reads in `blocks://nodes`. Nobody picks "media with `multiple: true` and
`accept: document`"; they pick "Files". Underneath there is one component and one set of rules —
the names exist for the person, not for the code.

Props: `max`, `min`, `captions`, `columns` and `aspect` for the grid, `accept` for the two file
types. The gallery's `accept` is fixed at `image`, because a gallery holding a `.zip` is a
template printing an `<img>` at a document.

Some things these deliberately do not do:

- **The thumbnails come from `thumb`**, not from `url`. A gallery of forty photographs drawn from
  the originals is forty originals over the wire; the manager's own grid has asked for previews
  for the same reason since it was written.
- **Editing a picture is not offered here.** The file is shared, and cropping it from one page's
  form crops it everywhere it stands. The card offers "open in the library", where the
  consequences are visible.
- **Uploading goes through the picker.** A drop zone on the field would first have to settle
  which folder the dropped file lands in, and that is a separate decision; in the picker the
  folder was chosen by eye.
- **A file deleted from the library does not invalidate the value.** `url` comes back `null`, the
  card draws as broken, and whoever is editing removes it. The alternative — a 422 under the
  field — means one missing picture out of twenty stops the page being saved at all.
- **`localized` is not supported** on these types, and says so rather than half working. The
  captions inside a value translate, as they do for `wx-media`; a translatable list would mean a
  different set of pictures per language, which is a decision about the entity, not the field.

## Talking to it directly

```ts
import { createMediaApi } from '@webx-ui/module-media'
import { useAdmin } from '@webx-ui/module-admin'

const api = createMediaApi(useAdmin())

const page = await api.files({ directory_id: 1, q: 'oslo', per_page: 40 })
```

Listing, upload, rename, move, delete, the image edit and the folder tree — the same calls the
manager itself is built on.

## What the addresses mean

A `MediaFile` carries three, and they are not interchangeable:

| Field    | For                                                                        |
| -------- | -------------------------------------------------------------------------- |
| `url`    | showing the file — the public address, served by the disk or a CDN         |
| `thumb`  | a grid: one cached variant, cut once and then served by the disk           |
| `source` | the image editor only — the same picture, served by the panel's own origin |

`source` exists because a canvas may not be written out once it has drawn an image from another
origin without CORS headers, which is exactly what a CDN in front of the library is — and a
private bucket cannot be given those headers for the panel at all. Everything that only looks at
a picture should use `url`.
