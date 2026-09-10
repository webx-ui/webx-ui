<script setup>
import RichTextDemo from '../components/demos/RichTextDemo.vue'
</script>

# RichText

`WxRichText` is a WYSIWYG editor for content fields: formatting, lists, tables, links, images and
YouTube embeds. It is built on [Tiptap](https://tiptap.dev/) — MIT, no paid extensions — and the
model is an HTML string, which is what a CMS column holds anyway.

<RichTextDemo />

## Usage

```vue
<script setup lang="ts">
import { ref } from 'vue'

const body = ref('')
</script>

<template>
  <wx-form-item label="Body" name="body">
    <wx-rich-text v-model="body" placeholder="Write something…" />
  </wx-form-item>
</template>
```

An empty editor writes an empty string, not `<p></p>` — so "has the user written anything" stays a
plain truthiness check on the backend.

## Images

The editor never uploads anything itself. Give it a function and it calls that:

```vue
<script setup lang="ts">
async function upload(file: File) {
  const body = new FormData()
  body.append('file', file)
  const response = await fetch('/admin/media', { method: 'POST', body })
  if (!response.ok) throw new Error('Upload failed')
  const { url } = await response.json()
  return { url, alt: file.name }
}
</script>

<template>
  <wx-rich-text v-model="body" :upload="upload" @upload-error="notify" />
</template>
```

With `upload` set, three things start working: pasting an image from the clipboard, dropping a file
onto the editor, and the toolbar's image button. Each file is uploaded on its own and inserted only
once the promise resolves — a failed upload emits `upload-error` and leaves the document untouched,
with nothing half-inserted to clean up.

`pickImage` is the other half, and the seam a media library plugs into later:

```vue
<template>
  <wx-rich-text v-model="body" :upload="upload" :pick-image="openMediaLibrary" />
</template>
```

When `pickImage` is given, the toolbar button opens it instead of a file dialog; pasting and
dropping still go through `upload`. Give neither and the image button is not rendered at all — the
editor does not offer what it cannot do.

## Tables and videos

The table button inserts a 3×3 table with a header row. While the caret is inside a table, a second
toolbar row appears with add and delete for rows and columns, merge and split, and delete table.
Columns are resizable by dragging.

The YouTube button asks for a URL and embeds it through `youtube-nocookie.com`.

## Props

| Prop          | Type                                             | Default     | Description                                |
| ------------- | ------------------------------------------------ | ----------- | ------------------------------------------ |
| `modelValue`  | `string`                                         | `''`        | HTML content                               |
| `placeholder` | `string`                                         | —           | Shown while the document is empty          |
| `tools`       | `RichTextTool[]`                                 | all         | Which buttons appear, in order             |
| `upload`      | `(file: File) => Promise<{ url, alt? }>`         | —           | Handles pasted, dropped and picked files   |
| `pickImage`   | `() => Promise<string \| null>`                  | —           | Opens a media library; `null` cancels      |
| `accept`      | `string[]`                                       | image types | MIME types accepted for upload             |
| `minHeight`   | `string`                                         | `'220px'`   | Height before the editor starts growing    |
| `size`        | `'sm' \| 'md' \| 'lg'`                           | `'md'`      | Control size                               |
| `status`      | `'default' \| 'success' \| 'warning' \| 'error'` | `'default'` | Validation state                           |
| `disabled`    | `boolean`                                        | `false`     | Disables the editor and its toolbar        |
| `readonly`    | `boolean`                                        | `false`     | Content stays visible but cannot be edited |
| `ariaLabel`   | `string`                                         | —           | Label when there is no visible one         |

**Events:** `update:modelValue` (`string`), `change` (`string`), `focus`, `blur`,
`upload-error` (`error`, `file`).

**Exposed:** `editor` — the Tiptap instance, for commands this component does not wrap — plus
`focus()` and `clear()`.

Tool keys: `bold`, `italic`, `strike`, `code`, `h2`, `h3`, `h4`, `bulletList`, `orderedList`,
`blockquote`, `hr`, `link`, `table`, `image`, `youtube`, `undo`, `redo`, and `divider` for a
separator.

```vue
<template>
  <wx-rich-text v-model="excerpt" :tools="['bold', 'italic', 'divider', 'link']" />
</template>
```

## Sanitise on the server anyway

ProseMirror parses pasted content against a schema and drops everything the schema does not know, so
a `<script>` pasted into the editor never becomes part of the document. That is a useful property,
but it is **not** a security boundary: the same field can be POSTed to directly, without the editor
ever running. Sanitise the HTML on the server on save — the editor makes the common path clean, the
server makes it safe.

## Bundle size

Tiptap and ProseMirror are dependencies of `@webx-ui/core` and are installed with it. They are not
bundled into our output, so an app that never imports `WxRichText` will not ship them.
