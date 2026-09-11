<script setup>
import DialogDemo from '../components/demos/DialogDemo.vue'
</script>

# Dialog

`WxDialog` is a window over the page: a heading with its actions, a body that scrolls, a row of
buttons under it. Use it when the page has to wait — a form to fill in, a choice to make, a record
to edit before anything else happens.

<DialogDemo />

## Usage

```vue
<template>
  <wx-dialog title="Edit page" :width="520">
    <template #trigger>
      <wx-button type="primary">Edit</wx-button>
    </template>

    <template #extra>
      <wx-badge type="warning">Draft</wx-badge>
    </template>

    <wx-form>
      <wx-form-item label="Title">
        <wx-input v-model="draft" />
      </wx-form-item>
    </wx-form>

    <template #footer="{ close }">
      <wx-button @click="close">Cancel</wx-button>
      <wx-button type="primary" @click="save">Save</wx-button>
    </template>
  </wx-dialog>
</template>
```

The `trigger` slot must hold exactly one element — the dialog takes over its click and its
`aria-expanded`. Anything already a button works; nothing of ours is wrapped around it.

Leave the slot out and drive the dialog from outside with `v-model:open`, which is what you want
when opening it also has to prepare something:

```vue
<script setup>
const open = ref(false)

watch(open, (value) => {
  if (value) draft.value = page.title
})
</script>

<template>
  <wx-button @click="open = true">Edit</wx-button>
  <wx-dialog v-model:open="open" title="Edit page">…</wx-dialog>
</template>
```

## The three parts

| Slot      | What goes in it                                                         |
| --------- | ----------------------------------------------------------------------- |
| `title`   | The heading. `title` as a prop does the same for plain text             |
| `extra`   | Next to the heading — badges, or actions that belong to the whole panel |
| `default` | The body. The one part that scrolls                                     |
| `sidebar` | A column beside the body; its presence is what splits the body in two   |
| `footer`  | A row under the body — where the Save and Cancel go                     |

The heading and the footer stay where they are however long the content is: only the body scrolls.
With a sidebar, the two columns scroll on their own instead.

## Size

`width` and `height` take a number, which is pixels, or any CSS length — `520`, `'60%'`, `'40rem'`.
Left without a `height` the panel grows with its content and stops at the height of the screen.

```vue
<wx-dialog title="Settings" width="70%" height="60%" />
```

## Moving and resizing

`draggable` lets the panel be moved by its heading; `resizable` adds a grip in the bottom-right
corner. Both are pointer-only — a finger dragging a heading is a finger not scrolling — and on a
small screen the panel fills it anyway.

`persist` gives the size and position a key in `localStorage`, so the panel opens where it was last
left:

```vue
<wx-dialog title="Media" draggable resizable persist="media" />
```

Without `persist`, a dialog forgets where it was dragged as soon as it closes. `reset()` on the
component puts it back in the middle at its declared size and clears what was stored.

## On a phone

Under 640px wide the panel takes the width of the screen, a remembered size and position are
ignored rather than reopening the dialog half off the screen, and a sidebar stacks above the body
instead of standing beside it.

## Focus and the page behind it

The panel takes focus when it opens and gives it back to the trigger when it closes, focus is
trapped inside it, and the page behind it stops scrolling. `:modal="false"` lifts all three —
reach for it only when the page is meant to stay usable, and for that `WxDrawer` is usually the
better answer.

`close-on-overlay` and `close-on-escape` govern the two ways out that are not the × or a footer
button; turn them off for a form where a stray click would lose what was typed.

## Props

| Prop             | Type               | Default   | Description                              |
| ---------------- | ------------------ | --------- | ---------------------------------------- |
| `open`           | `boolean`          | `false`   | Use with `v-model:open`                  |
| `title`          | `string`           | —         | Heading of the panel                     |
| `width`          | `number \| string` | `520`     | A number means pixels                    |
| `height`         | `number \| string` | —         | Grows with the content when left out     |
| `minWidth`       | `number`           | `320`     | Smallest width a resize may reach, px    |
| `minHeight`      | `number`           | `200`     | Smallest height a resize may reach, px   |
| `sidebarWidth`   | `number \| string` | `200`     | Width of the `sidebar` column            |
| `closable`       | `boolean`          | `true`    | Adds a × to the heading                  |
| `closeLabel`     | `string`           | `'Close'` | Label of that ×, for screen readers      |
| `closeOnOverlay` | `boolean`          | `true`    | A click outside closes it                |
| `closeOnEscape`  | `boolean`          | `true`    | <kbd>Esc</kbd> closes it                 |
| `overlay`        | `boolean`          | `true`    | Dim the page behind the panel            |
| `modal`          | `boolean`          | `true`    | Trap focus and block the page behind     |
| `draggable`      | `boolean`          | `false`   | The panel can be moved by its heading    |
| `resizable`      | `boolean`          | `false`   | Adds the grip in the bottom-right corner |
| `persist`        | `string`           | —         | Key under which the layout is remembered |
| `ariaLabel`      | `string`           | —         | Accessible name when there is no heading |

**Events:** `open`, `close`, `layout` (`{ width, height, x, y }` — what `persist` would store).
**Slots:** `trigger` (`{ open }`) — one element; `default`, `extra`, `sidebar`, `footer`
(all `{ close }`); `title`.
**Exposed:** `close()`, `reset()`.

## Dialog or drawer

They are the same panel and share their props. A dialog sits in the middle of the screen and asks
to be answered; a [drawer](./drawer) is anchored to an edge and leaves the page behind it visible,
which is what you want when the panel is about something on that page.
