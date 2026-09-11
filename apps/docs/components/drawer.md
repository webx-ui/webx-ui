<script setup>
import DrawerDemo from '../components/demos/DrawerDemo.vue'
</script>

# Drawer

`WxDrawer` is the same panel as [`WxDialog`](./dialog) — heading, body, footer — anchored to an edge
of the screen instead of floating in the middle of it. Use it when the page behind it is the point:
a record opened beside the table it came from, filters next to the list they filter.

<DrawerDemo />

## Usage

```vue
<template>
  <wx-drawer title="Order #1043" :size="420">
    <template #trigger>
      <wx-button type="primary">Open the order</wx-button>
    </template>

    <template #extra>
      <wx-badge type="success">Paid</wx-badge>
    </template>

    <wx-form>
      <wx-form-item label="Customer">
        <wx-input v-model="name" />
      </wx-form-item>
    </wx-form>

    <template #footer="{ close }">
      <wx-button @click="close">Cancel</wx-button>
      <wx-button type="primary" @click="save">Save</wx-button>
    </template>
  </wx-drawer>
</template>
```

The `trigger` slot must hold exactly one element. Leave it out and drive the panel with
`v-model:open` — the usual case for a table, where the row that was clicked decides what the panel
shows.

## The three parts

The heading and the footer are fixed; the body between them is the one part that scrolls, however
long it is. `title`, `extra`, `sidebar` and `footer` are the same slots as in the dialog, and a
`sidebar` splits the body into two columns that scroll on their own.

## Side and size

`side` is the edge it slides in from: `right` (the default), `left`, `top` or `bottom`.

`size` is how far it reaches into the screen — its width on the left and right, its height at the
top and bottom. A number means pixels; anything else is a CSS length:

```vue
<wx-drawer side="bottom" size="40%" title="Filters" />
```

## Resizing

`resizable` turns the edge the panel faces the page with into a handle. It is a `separator` for
screen readers, takes focus, and answers the arrow keys 24px at a time, so the panel can be resized
without a pointer. `persist` writes the size to `localStorage` under that key:

```vue
<wx-drawer title="Customer" :size="560" resizable persist="customer" />
```

Without `persist` the panel goes back to its declared size when it closes. `reset()` on the
component does the same and clears what was stored.

## On a phone

Under 640px wide the panel covers the screen whichever edge it came from — a strip of page left
visible beside it is a panel too narrow to work in — and the resize handle goes away with it. A
sidebar stacks above the body instead of standing beside it.

## Focus and the page behind it

Focus moves into the panel when it opens and back to the trigger when it closes, it is trapped
inside while it is open, and the page behind it stops scrolling. `:modal="false"` together with
`:overlay="false"` turns the drawer into a panel that sits beside a page that stays fully usable —
an inspector rather than a dialog.

## Props

| Prop             | Type                                     | Default              | Description                              |
| ---------------- | ---------------------------------------- | -------------------- | ---------------------------------------- |
| `open`           | `boolean`                                | `false`              | Use with `v-model:open`                  |
| `title`          | `string`                                 | —                    | Heading of the panel                     |
| `side`           | `'right' \| 'left' \| 'top' \| 'bottom'` | `'right'`            | Edge it slides in from                   |
| `size`           | `number \| string`                       | `380`                | Width, or height at top and bottom       |
| `minSize`        | `number`                                 | `280`                | Smallest size a resize may reach, px     |
| `sidebarWidth`   | `number \| string`                       | `200`                | Width of the `sidebar` column            |
| `closable`       | `boolean`                                | `true`               | Adds a × to the heading                  |
| `closeLabel`     | `string`                                 | `'Close'`            | Label of that ×, for screen readers      |
| `closeOnOverlay` | `boolean`                                | `true`               | A click outside closes it                |
| `closeOnEscape`  | `boolean`                                | `true`               | <kbd>Esc</kbd> closes it                 |
| `overlay`        | `boolean`                                | `true`               | Dim the page behind the panel            |
| `modal`          | `boolean`                                | `true`               | Trap focus and block the page behind     |
| `resizable`      | `boolean`                                | `false`              | Adds the handle on the inner edge        |
| `persist`        | `string`                                 | —                    | Key under which the size is remembered   |
| `ariaLabel`      | `string`                                 | —                    | Accessible name when there is no heading |
| `resizeLabel`    | `string`                                 | `'Resize the panel'` | Label of the handle                      |

**Events:** `open`, `close`, `layout` (`{ size }` — what `persist` would store).
**Slots:** `trigger` (`{ open }`) — one element; `default`, `extra`, `sidebar`, `footer`
(all `{ close }`); `title`.
**Exposed:** `close()`, `reset()`.
