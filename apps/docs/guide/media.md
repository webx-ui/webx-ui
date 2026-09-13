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

## Talking to it directly

```ts
import { createMediaApi } from '@webx-ui/module-media'
import { useAdmin } from '@webx-ui/admin'

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
