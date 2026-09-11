<script setup>
import PopoverDemo from '../components/demos/PopoverDemo.vue'
</script>

# Popover

`WxPopover` hangs a panel off a control: a small form, a confirmation, an explanation that is too
long for a tooltip. The panel stays until it is closed, so it can be worked in.

<PopoverDemo />

## Usage

```vue
<template>
  <wx-popover title="Tab" :width="260" closable>
    <template #trigger>
      <wx-button size="sm">
        <template #icon><wx-icon name="edit" /></template>
        Rename
      </wx-button>
    </template>

    <wx-form gap="sm">
      <wx-form-item label="Name">
        <wx-input v-model="draft" size="sm" />
      </wx-form-item>
    </wx-form>

    <template #footer="{ close }">
      <wx-button size="sm" @click="close">Cancel</wx-button>
      <wx-button size="sm" type="primary" @click="save">Save</wx-button>
    </template>
  </wx-popover>
</template>
```

The `trigger` slot must hold exactly one element — the panel is anchored to it and takes over its
`aria-expanded`. Anything already a button works; nothing of ours is wrapped around it.

`v-model:open` drives the panel from outside, which is what you want when opening it also has to
prepare something — copying the current value into a draft, say. Watch the state rather than
hanging the work off the trigger's own click: the trigger already toggles the panel, and a second
handler that sets `open` in the same click can toggle it straight back.

```vue
<script setup>
const open = ref(false)

watch(open, (value) => {
  if (value) draft.value = tab.label
})
</script>
```

## Popover or dropdown

They look alike and are built on the same primitive, so the difference is what happens to a click
inside:

- **`WxDropdown`** is a menu. A click on an item is the whole interaction, so the panel closes.
  Use it for lists of actions.
- **`WxPopover`** is a panel. A click inside is part of the work — typing in a field, ticking a
  box — so it stays open until the ×, a footer button, <kbd>Esc</kbd> or a click outside.

## Placement

`side` is the side of the trigger the panel prefers and `align` is how it lines up along it. The
panel flips and shifts on its own when the screen has no room where it was asked to go, so the
props are a preference, not a promise. `offset` and `align-offset` move it in pixels.

The panel is rendered in a portal, so it escapes `overflow: hidden` — a popover on a tab inside a
scrolling strip, or in a table cell, is not clipped. `:teleport="false"` keeps it in place if the
surrounding layout needs it there.

## Focus and the page behind it

The panel takes focus when it opens and gives it back to the trigger when it closes, so a form
inside is usable from the keyboard alone. `modal` additionally traps focus and blocks the page
behind the panel — reach for it when the panel must be answered before anything else.

## Props

| Prop          | Type                                     | Default    | Description                              |
| ------------- | ---------------------------------------- | ---------- | ---------------------------------------- |
| `open`        | `boolean`                                | `false`    | Use with `v-model:open`                  |
| `side`        | `'top' \| 'right' \| 'bottom' \| 'left'` | `'bottom'` | Preferred side of the trigger            |
| `align`       | `'start' \| 'center' \| 'end'`           | `'center'` | Alignment along that side                |
| `offset`      | `number`                                 | `8`        | Distance from the trigger, px            |
| `alignOffset` | `number`                                 | `0`        | Shift along the alignment axis, px       |
| `arrow`       | `boolean`                                | `true`     | Draws the pointer at the trigger         |
| `title`       | `string`                                 | —          | Heading of the panel                     |
| `width`       | `number \| string`                       | —          | Panel width; a number means pixels       |
| `teleport`    | `boolean`                                | `true`     | Render in a portal                       |
| `modal`       | `boolean`                                | `false`    | Trap focus and block the page behind     |
| `disabled`    | `boolean`                                | `false`    | The trigger opens nothing                |
| `closable`    | `boolean`                                | `false`    | Adds a × to the heading                  |
| `closeLabel`  | `string`                                 | `'Close'`  | Label of that ×, for screen readers      |
| `ariaLabel`   | `string`                                 | —          | Accessible name when there is no heading |

**Events:** `open`, `close`.
**Slots:** `trigger` (`{ open }`) — one element; `default` (`{ close }`) — the panel; `title`;
`footer` (`{ close }`).
**Exposed:** `close()`.
