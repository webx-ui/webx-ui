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

## More than fits on the screen

`scroll` decides what gives way when the content is longer than the screen:

- **`'body'`** (the default) keeps the panel inside the screen. The heading and the footer stay
  where they are and the body between them scrolls — the right shape for a form, where the Save is
  always one glance away.
- **`'panel'`** lets the panel grow as tall as its content and scrolls the whole of it inside the
  screen. The heading scrolls away with everything else — the right shape for a long list picked
  from, or a document read through.

```vue
<wx-dialog title="Pick a brand" scroll="panel" :width="440">…</wx-dialog>
```

In `panel` mode `sticky-footer` decides where the buttons end up. Left on, the footer rests against
the bottom of the screen while the list scrolls behind it; turned off, it sits at the end of the
content and is reached by scrolling to it:

```vue
<wx-dialog scroll="panel" :sticky-footer="false" />
```

A panel taller than the screen has nowhere to be dragged to and nothing to be stretched into, so
`draggable` and `resizable` are ignored while `scroll="panel"` — no need to unpick them when a
dialog turns out to be the long kind.

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

Under 640px wide the panel takes the width of the screen, the paddings of the heading, body and
footer tighten so that more of the content fits, a remembered size and position are ignored rather
than reopening the dialog half off the screen, and a sidebar stacks above the body instead of
standing beside it.

Those paddings are custom properties — `--wx-dialog-pad-x`, `--wx-dialog-pad-y` and
`--wx-dialog-body-pad` — so a panel that wants roomier or tighter chrome can set them itself.

## Focus and the page behind it

The panel takes focus when it opens and gives it back to the trigger when it closes, focus is
trapped inside it, and the page behind it stops scrolling. `:modal="false"` lifts all three —
reach for it only when the page is meant to stay usable, and for that `WxDrawer` is usually the
better answer.

`close-on-overlay` and `close-on-escape` govern the two ways out that are not the × or a footer
button; turn them off for a form where a stray click would lose what was typed.

## Props

| Prop             | Type                | Default   | Description                                       |
| ---------------- | ------------------- | --------- | ------------------------------------------------- |
| `open`           | `boolean`           | `false`   | Use with `v-model:open`                           |
| `title`          | `string`            | —         | Heading of the panel                              |
| `width`          | `number \| string`  | `520`     | A number means pixels                             |
| `height`         | `number \| string`  | —         | Grows with the content when left out              |
| `scroll`         | `'body' \| 'panel'` | `'body'`  | What scrolls when the content is long             |
| `stickyFooter`   | `boolean`           | `true`    | In `scroll="panel"`, pin the footer to the screen |
| `minWidth`       | `number`            | `320`     | Smallest width a resize may reach, px             |
| `minHeight`      | `number`            | `200`     | Smallest height a resize may reach, px            |
| `sidebarWidth`   | `number \| string`  | `200`     | Width of the `sidebar` column                     |
| `closable`       | `boolean`           | `true`    | Adds a × to the heading                           |
| `closeLabel`     | `string`            | `'Close'` | Label of that ×, for screen readers               |
| `closeOnOverlay` | `boolean`           | `true`    | A click outside closes it                         |
| `closeOnEscape`  | `boolean`           | `true`    | <kbd>Esc</kbd> closes it                          |
| `overlay`        | `boolean`           | `true`    | Dim the page behind the panel                     |
| `modal`          | `boolean`           | `true`    | Trap focus and block the page behind              |
| `draggable`      | `boolean`           | `false`   | Moved by its heading; off when `scroll="panel"`   |
| `resizable`      | `boolean`           | `false`   | Grip in the corner; off when `scroll="panel"`     |
| `persist`        | `string`            | —         | Key under which the layout is remembered          |
| `ariaLabel`      | `string`            | —         | Accessible name when there is no heading          |

**Events:** `open`, `close`, `layout` (`{ width, height, x, y }` — what `persist` would store).
**Slots:** `trigger` (`{ open }`) — one element; `default`, `extra`, `sidebar`, `footer`
(all `{ close }`); `title`.
**Exposed:** `close()`, `reset()`.

## Dialog or drawer

They are the same panel and share their props. A dialog sits in the middle of the screen and asks
to be answered; a [drawer](./drawer) is anchored to an edge and leaves the page behind it visible,
which is what you want when the panel is about something on that page.
