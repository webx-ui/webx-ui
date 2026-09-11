<script setup>
import UploadDemo from '../components/demos/UploadDemo.vue'
</script>

# Upload

`WxUpload` collects files, checks them, and shows how each one is getting on.

<UploadDemo />

## It does not upload anything

That is deliberate, and it is the same rule the rest of the library follows: **components never
call an API.** A component that owned the request would own the URL, the headers, the CSRF token,
the retry policy and the shape of an error — none of which it can know, and every one of which
differs between two admin panels built on the same backend.

So it collects; you send; you write the progress back:

```vue
<script setup lang="ts">
import type { UploadFile } from '@webx-ui/core'

const files = ref<UploadFile[]>([])

async function send(added: UploadFile[]) {
  for (const file of added) {
    file.status = 'uploading'

    const body = new FormData()
    body.append('file', file.raw!)

    try {
      const response = await axios.post('/admin/media', body, {
        onUploadProgress: (event) => {
          file.progress = Math.round((event.loaded / (event.total ?? 1)) * 100)
        },
      })
      file.status = 'done'
      file.url = response.data.url
    } catch (error) {
      file.status = 'error'
      file.error = error.response?.data?.message ?? 'Upload failed'
    }
  }
}
</script>

<template>
  <wx-upload v-model="files" accept="image/*" :max-size="2 * 1024 * 1024" @add="send" />
</template>
```

`v-model` is the list, and every file in it is a plain object you can write to. Setting `progress`
moves the bar; setting `status` to `error` and `error` to a message shows it on the row.

## Refusing before adding

`accept`, `maxSize` and `max` are checked before a file joins the list, and anything refused is
reported through `@reject` with a reason — `type`, `size` or `count`. Nothing is added silently and
nothing is dropped silently.

`beforeAdd` is the hook for a check only you can make — image dimensions, a duplicate name, a quota:

```vue
<wx-upload :before-add="(file) => file.size > 0" />
```

Return `false`, or a promise resolving to `false`, and the file is refused with the reason
`rejected`.

## Say the limits out loud

Put them in `#footer`, in the reader's words. A drop zone that refuses a file without ever having
said what it accepts is a drop zone that gets tried three times.

```vue
<template #footer>Images or PDFs, up to 2 MB each, five at a time.</template>
```

## Props

| Prop         | Type                                          | Default                                 | Description                          |
| ------------ | --------------------------------------------- | --------------------------------------- | ------------------------------------ |
| `accept`     | `string`                                      | —                                       | What is allowed, e.g. `image/*,.pdf` |
| `multiple`   | `boolean`                                     | `true`                                  | More than one at a time              |
| `maxSize`    | `number`                                      | —                                       | Largest file, in bytes               |
| `max`        | `number`                                      | —                                       | Largest number of files              |
| `buttonOnly` | `boolean`                                     | `false`                                 | A button instead of a drop zone      |
| `buttonText` | `string`                                      | `'Choose files'`                        | Label of that button                 |
| `hint`       | `string`                                      | `'Drop files here, or click to choose'` | The line in the zone                 |
| `gallery`    | `boolean`                                     | `false`                                 | Files as a grid rather than rows     |
| `disabled`   | `boolean`                                     | `false`                                 | Nothing can be added                 |
| `removable`  | `boolean`                                     | `true`                                  | Files can be taken off the list      |
| `beforeAdd`  | `(file: File) => boolean \| Promise<boolean>` | —                                       | Asked about every file               |

**Models:** `v-model` (`UploadFile[]`).

**Events:** `add` (`UploadFile[]`), `remove` (`UploadFile`), `reject` (`File`, reason),
`select` (`UploadFile`).

**Slots:** `default` — replaces the inside of the zone, with `{ open, dragging }`; `file` — replaces
a row, with `{ file, remove }`; `footer`.

### UploadFile

```ts
interface UploadFile {
  id: string
  name: string
  size: number
  type: string
  status: 'ready' | 'uploading' | 'done' | 'error'
  progress: number
  error?: string
  url?: string
  raw?: File
}
```

A file that arrived from the server rather than from disk has a `url` and no `raw` — which is how
you show what is already attached to a record.

## Accessibility

The drop zone is a real `<button>`, so a keyboard reaches it and presses it like anything else; the
file input behind it is hidden from view but not from assistive technology. Every file's remove
button is named after the file, so a row of them is not four buttons all called Remove.
