# @webx-ui/module-media

The file manager of a [WebX UI](https://github.com/webx-ui/webx-ui) admin panel: folders, files,
uploads, previews and an image editor. Pairs with
[`webx-ui/module-media`](https://packagist.org/packages/webx-ui/module-media) on the server.

```bash
pnpm add @webx-ui/module-media
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { auth } from '@webx-ui/module-auth'
import { media } from '@webx-ui/module-media'

import '@webx-ui/core/style.css'
import '@webx-ui/module-admin/style.css'
import '@webx-ui/module-media/style.css'

createAdmin({
  basePath: '/cms',
  modules: [media()],
  plugins: [auth()],
}).mount()
```

The section appears once both halves are installed — the server reports the module, and this
lists its front end.

## Picking a file from a form

```ts
import { openMediaPicker } from '@webx-ui/module-media'

const file = await openMediaPicker({ accept: 'image' })
// `undefined` when the dialog was closed.
```

Or as a field, which keeps the words this entity uses for the picture beside the reference to it:

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

`MediaValue` is `{ path, alt, title }`. **`alt` and `title` belong to the entity, not to the
file** — one picture used by two articles needs two captions. The address is worked out from
`path` when the page is drawn, so the library can move to another disk without touching a single
article.

## What it draws

`WxMediaManager` is the section itself: a folder tree, a grid of cards with rubber-band
selection, upload, search, a type filter and paging. `WxMediaPicker` is the same manager in a
dialog — deliberately the same, because a picker that browses differently is a second thing to
learn.

Previews come from the server's thumbnail endpoint rather than from the files, so a grid of forty
photographs is forty thumbnails.

Editing a picture sends **operations** — crop, rotate, flip, resize — rather than the canvas's
own result: the canvas works on a preview, and the server applies them to the original at full
size, over the same key, so links already written into content keep working. Filters and
adjustments are switched off until the server can be told about them.

## API client

```ts
import { createMediaApi } from '@webx-ui/module-media'
import { useAdmin } from '@webx-ui/module-admin'

const api = createMediaApi(useAdmin())
const page = await api.files({ q: 'sofa', type: 'image' })
```

## Licence

MIT.
