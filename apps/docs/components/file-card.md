<script setup>
import FileCardDemo from '../components/demos/FileCardDemo.vue'
</script>

# FileCard

`WxFileCard` is one file in a media library: a preview where there is a picture to show, a glyph
where there is not, the name it goes by, and the few things that can be done to it.

<FileCardDemo />

## Usage

```vue
<script setup lang="ts">
import { WxFileCard } from '@webx-ui/core'
</script>

<template>
  <wx-file-card
    :name="file.name"
    :url="file.url"
    :thumbnail="file.thumbnail"
    renamable
    removable
    copyable
    @rename="save(file, $event)"
    @remove="destroy(file)"
  />
</template>
```

## It does none of it

Renaming reports a name, deleting reports a wish, the edit action reports that somebody wants an
editor. Nothing happens to the file until the screen holding the cards says so — the card has no
idea there is a server, which is what lets the same card sit in a library, in a picker, and in a
form field.

The one exception is the clipboard. Copying a URL is finished the moment it happens, so the card
does it and says what it did: `copy` with the URL, or `copy-error` when the browser refused —
which it does outside a secure context, where there is no clipboard at all.

## Picture or glyph

A picture is drawn from `thumbnail`, or from `url` when there is no thumbnail. What counts as a
picture is the MIME type where there is one and the extension where there is not, and only the
formats a browser will actually draw: a TIFF is an image and renders as a broken one, so it gets a
glyph like any other file. A picture that fails to load falls back to its glyph too.

Everything else is an icon and the extension written underneath. The icons go by family — a `.docx`
and an `.odt` are both a page of prose — and the line of text is what separates them, and what says
anything at all about a `.sketch`.

## An icon of your own

`file-<extension>` is the name for every one of them, so a `.dwg` gets its own drawing without a
release of this library:

```ts
import { registerIcons } from '@webx-ui/core'

registerIcons({ 'file-dwg': '<path d="…" />' })
```

The card asks the icon registry, so that is all it takes. `:icon="…"` overrides one card's glyph
where the name is not the whole truth.

## Renaming

`renamable` opens a small panel under the actions — from the rename action, or by double-clicking
the name. Enter or **Save** commits; Escape, **Cancel** and a click on the page behind it all leave
the name alone. The name is selected whole, extension and all: a rename is usually a new name.

It is a panel and not a field in place of the name for two reasons. Swapping a line of text for an
input changes the height of the card, and a card in a grid changes the height of its row, so
renaming one file made the whole library jump. And it drops from the button that was pressed, which
is where the reader is already looking — not at the other end of the card.

A plain click on the name is not a rename — it belongs to whatever is choosing the file. That is
also why the card is dismissed rather than saved when the panel is clicked away from: a panel
dismissed is a panel dismissed.

Nothing is renamed by the card. `@rename` carries the new name, and the card goes on showing the
old one until `name` is given the new one.

## The name that does not fit

The name is one line, cut off with an ellipsis, and the full name is in a tooltip. The tooltip is
only armed when the name is really cut off, which is measured rather than assumed — a tip that
repeats a name already fully visible is noise, and how wide the card is decides that, not how long
the name is.

## The actions

| Prop        | What it offers                                                                                  |
| ----------- | ----------------------------------------------------------------------------------------------- |
| `renamable` | The name, in a panel under the button                                                           |
| `editable`  | `@edit` — "open this picture in an editor". Pictures only; there is nothing to crop in a `.zip` |
| `copyable`  | `url` to the clipboard, with a tick for a moment afterwards                                     |
| `removable` | `@remove`, after a question unless `:confirm-remove="false"`                                    |

They sit over the preview, and where the pointer can hover they wait until it does. A touch screen
never hovers, so there they simply stand — actions that wait for a hover that cannot happen are
actions nobody can reach.

They also stay while any of their panels is up. The menu, the rename field and the question before
a deletion are all teleported, so neither the pointer nor the focus is on the card while one of
them is open, and the buttons would otherwise fade out from under it.

`disabled` takes all four away and stops the name being edited: a file a reader may look at and not
touch.

## Before deleting

`confirmRemove` is on: the delete action asks first, in a panel under the button, and `@remove`
comes only when the question is answered. A file deleted from a grid of thumbnails is a file
deleted by a misplaced click, and the thumbnails all look much alike — which is why the question
names the file. `removeConfirmText` replaces it; `:confirm-remove="false"` skips it, for a screen
that asks its own question or where the deletion can be undone.

## Props

| Prop                | Type                   | Default          | Description                                                   |
| ------------------- | ---------------------- | ---------------- | ------------------------------------------------------------- |
| `name`              | `string`               | —                | The file name, extension and all                              |
| `url`               | `string`               | —                | Where the file is; what the copy action copies                |
| `thumbnail`         | `string`               | —                | A smaller picture to draw instead of `url`                    |
| `type`              | `string`               | —                | MIME type; decides picture or glyph before the extension does |
| `icon`              | `IconName`             | by extension     | Glyph to draw instead                                         |
| `selected`          | `boolean`              | `false`          | Draws the card as chosen                                      |
| `size`              | `'sm' \| 'md' \| 'lg'` | `'md'`           | Glyph and text size                                           |
| `disabled`          | `boolean`              | `false`          | No actions, no renaming                                       |
| `renamable`         | `boolean`              | `false`          | Offers renaming                                               |
| `editable`          | `boolean`              | `false`          | Offers the edit action, for pictures                          |
| `removable`         | `boolean`              | `false`          | Offers deleting                                               |
| `confirmRemove`     | `boolean`              | `true`           | Asks before deleting                                          |
| `removeConfirmText` | `string`               | `Delete <name>?` | The question it asks                                          |
| `copyable`          | `boolean`              | `false`          | Offers copying the link                                       |
| `renameLabel`       | `string`               | `'Rename'`       | Tooltip and accessible name                                   |
| `saveLabel`         | `string`               | `'Save'`         | The two buttons under the rename field                        |
| `cancelLabel`       | `string`               | `'Cancel'`       | —                                                             |
| `editLabel`         | `string`               | `'Edit picture'` | —                                                             |
| `removeLabel`       | `string`               | `'Delete'`       | —                                                             |
| `copyLabel`         | `string`               | `'Copy link'`    | —                                                             |
| `copiedLabel`       | `string`               | `'Copied'`       | Shown for a moment after a copy worked                        |

**Events:** `rename` (`string`); `edit`; `remove`; `copy` (`string`); `copy-error` (`unknown`).

**Slots:** `preview` — with `{ picture }`, for a video still or a player; `actions` — your own, after
the card's; `meta` — under the name, for a size or a date.

## Helpers

The three the card uses are exported, since a table of files wants the same answers:

```ts
import { extensionOf, fileIconName, isPicture } from '@webx-ui/core'

extensionOf('photo.JPG?v=2') // 'jpg'
isPicture('report.pdf') // false
fileIconName('spare-parts.xlsx') // 'file-xlsx'
```

## In a library

The card is an element like any other, so `v-wx-select` goes on it and
[SelectionArea](/components/selection-area) does the rest — the box, the taps, the run with shift.
The buttons on the card are buttons, so a drag that starts on one is the button's, not the box's.

## Accessibility

Every action is a real button with an accessible name, whether or not a tooltip is showing. The
preview is decorative when there is a glyph and carries the file name as its `alt` when there is a
picture. Renaming is an input with the rename label on it, inside a panel that takes focus when it
opens and gives it back when it closes, and the keys are the ones a file manager has taught
everybody: Enter to save, Escape to leave it alone.
