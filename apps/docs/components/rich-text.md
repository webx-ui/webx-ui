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

### The key, not the address

Both functions may answer with a `path` beside the `url`, and when they do the editor writes it
into the document as `data-wx-path`:

```html
<img src="https://cdn.example.com/m/hero.jpg?v=9f2a" data-wx-path="2026/09/hero.jpg" alt="…" />
```

The address is not the picture. It is different on every deployment of the same site — a bucket
on the developer's machine, the application's own disk in production — it may be signed and
about to expire, and it carries a version stamp that changes the moment somebody crops the
image. The key is none of those things, so the key is what a record should keep and the address
is what should be worked out again on the way out.

The editor only carries the key; working an address back out of it is the job of whatever stores
the document — in a WebX panel that is `wx-rich-text`, which does it on every read. A picture
with no `path` is an address and nothing more, and is never touched.

```ts
// The one thing a picker has to do differently.
const pickImage = async () => {
  const file = await openLibrary()

  return file && { url: file.url, path: file.key }
}
```

## Languages

`localized` turns the field into one editor with a language chip in its corner, exactly as
[`WxInput`](/components/input) and [`WxTextarea`](/components/textarea) do — and the model
becomes `{ en: '<p>…</p>', de: '<p>…</p>' }` rather than a string:

```vue
<template>
  <wx-rich-text v-model="body" localized />
</template>
```

One editor rather than one per language, and the chip swaps the document in it. Four of them
stacked would be four documents to scroll past to reach the next field, which is the difference
between a document and a line: a localized input can afford to show every language at once.

The languages come from `provideLocales()` — in a panel they are the site's content languages,
and the chips of every field on a screen move together.

## What the buttons are called

Every label is English by default. This component knows nothing about the application it is
opened in, so whoever opens it translates it:

```vue
<template>
  <wx-rich-text v-model="body" :labels="{ bold: 'Жирный', linkAddress: 'Адрес ссылки' }" />
</template>
```

The keys are the tool keys, the table tools (`addRowAfter`, `deleteTable`, …), and
`toolbar`, `linkAddress`, `youtubeAddress`, `apply`, `cancel` and `uploading`. A key left out
stays English rather than blank. In a WebX panel none of this is written by hand:
[`wx-rich-text`](/guide/screens) is the same editor with the panel's dictionary already behind
it.

## Tables and videos

The table button inserts a 3×3 table with a header row. While the caret is inside a table, a second
toolbar row appears with add and delete for rows and columns, merge and split, and delete table.
Columns are resizable by dragging.

The YouTube button asks for a URL and embeds it through `youtube-nocookie.com`.

## Props

| Prop          | Type                                             | Default                 | Description                                                       |
| ------------- | ------------------------------------------------ | ----------------------- | ----------------------------------------------------------------- |
| `modelValue`  | `string                                          | Record<string, string>` | `''`                                                              | HTML content; a map per language under `localized` |
| `placeholder` | `string`                                         | —                       | Shown while the document is empty                                 |
| `tools`       | `RichTextTool[]`                                 | all                     | Which buttons appear, in order                                    |
| `upload`      | `(file: File) => Promise<{ url, alt? }>`         | —                       | Handles pasted, dropped and picked files                          |
| `pickImage`   | `() => Promise<string \| null>`                  | —                       | Opens a media library; `null` cancels                             |
| `accept`      | `string[]`                                       | image types             | MIME types accepted for upload                                    |
| `localized`   | `boolean`                                        | `false`                 | Edits one language at a time; the model becomes a map             |
| `labels`      | `RichTextLabels`                                 | English                 | What the buttons and the link bar are called                      |
| `minHeight`   | `string`                                         | `'220px'`               | Height before the editor starts growing                           |
| `size`        | `'sm' \| 'md' \| 'lg'`                           | `'md'`                  | Control size                                                      |
| `status`      | `'default' \| 'success' \| 'warning' \| 'error'` | `'default'`             | Validation state                                                  |
| `disabled`    | `boolean`                                        | `false`                 | Disables the editor and its toolbar                               |
| `readonly`    | `boolean`                                        | `false`                 | Content stays visible but cannot be edited                        |
| `id`          | `string`                                         | generated               | Overrides the `id` the label points at; `WxFormItem` supplies one |
| `ariaLabel`   | `string`                                         | —                       | Label when there is no visible one                                |

**Events:** `update:modelValue` (`string`), `change` (`string`), `focus`, `blur`,
`upload-error` (`error`, `file`).

`RichTextImage` is `{ url, path?, alt? }` — see [the key, not the address](#the-key-not-the-address).

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

In a WebX panel that is done for you wherever a value is saved through a screen:
`wx-rich-text` stores what it is given through an allowlist, in `module-admin` — see
[Screens](/guide/screens), which also says where that does **not** apply. `data-wx-path` is on
the allowlist, which is what keeps the key in the document.

## Bundle size

Tiptap and ProseMirror are dependencies of `@webx-ui/core` and are installed with it. They are not
bundled into our output, so an app that never imports `WxRichText` will not ship them.
