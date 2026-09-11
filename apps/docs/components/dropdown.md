<script setup>
import DropdownDemo from '../components/demos/DropdownDemo.vue'
</script>

# Dropdown

`WxDropdown` is a panel anchored to a trigger: a menu of actions, a filter panel, a user menu.
Two slots and nothing else — you supply the trigger, you supply the content. It wraps
[Reka UI](https://reka-ui.com/)'s Popover, which brings the positioning, the outside-click and
Escape handling, and the focus behaviour.

<DropdownDemo />

## Usage

```vue
<template>
  <wx-dropdown>
    <template #trigger>
      <wx-button type="primary">Actions</wx-button>
    </template>

    <wx-dropdown-item icon="edit" @click="edit">Edit</wx-dropdown-item>
    <wx-dropdown-item icon="copy" @click="duplicate">Duplicate</wx-dropdown-item>
    <hr />
    <wx-dropdown-item icon="trash" tone="danger" @click="remove">Delete</wx-dropdown-item>
  </wx-dropdown>
</template>
```

::: tip The trigger slot takes exactly one element
The trigger is rendered as the element you pass — a `WxButton`, a `WxAction`, your own component —
rather than being wrapped in a button of our own, because a button inside a button is invalid HTML.
Pass one element; for plain text, wrap it in a `wx-button`.
:::

The trigger slot is scoped with the open state, so it can react to it:

```vue
<template>
  <wx-dropdown>
    <template #trigger="{ open }">
      <wx-button>{{ open ? 'Close' : 'Open' }}</wx-button>
    </template>
    …
  </wx-dropdown>
</template>
```

## Content that is not a menu

The default slot takes any markup, and is scoped with `close` for the moment you want to dismiss the
panel yourself. Filters are the usual case — and they need `:close-on-click="false"`, otherwise the
first checkbox closes the panel:

```vue
<template>
  <wx-dropdown :close-on-click="false" match-trigger-width>
    <template #trigger>
      <wx-button variant="outline">Status</wx-button>
    </template>

    <template #default="{ close }">
      <wx-checkbox-group v-model="statuses" :options="statusOptions" />
      <wx-button size="sm" type="primary" block @click="close">Apply</wx-button>
    </template>
  </wx-dropdown>
</template>
```

## Position

`side` picks the edge of the trigger the panel opens from, `align` how it lines up along that edge,
and `offset` / `align-offset` nudge it. The panel flips and shifts on its own when there is no room
on screen.

```vue
<template>
  <wx-dropdown side="right" align="start" :offset="10">…</wx-dropdown>
</template>
```

## Controlling it

`v-model:open` opens and closes the panel from outside; `open` and `close` events report it:

```vue
<template>
  <wx-dropdown v-model:open="menuOpen" @close="onClose">…</wx-dropdown>
</template>
```

## Props

| Prop                | Type                                     | Default    | Description                              |
| ------------------- | ---------------------------------------- | ---------- | ---------------------------------------- |
| `open`              | `boolean`                                | `false`    | `v-model:open` — the panel's state       |
| `side`              | `'top' \| 'right' \| 'bottom' \| 'left'` | `'bottom'` | Edge the panel opens from                |
| `align`             | `'start' \| 'center' \| 'end'`           | `'start'`  | Alignment along that edge                |
| `offset`            | `number`                                 | `6`        | Gap from the trigger, in pixels          |
| `alignOffset`       | `number`                                 | `0`        | Shift along the trigger's edge           |
| `closeOnClick`      | `boolean`                                | `true`     | A click inside closes the panel          |
| `matchTriggerWidth` | `boolean`                                | `false`    | Panel is at least as wide as the trigger |
| `teleport`          | `boolean`                                | `true`     | Render the panel in a portal             |
| `modal`             | `boolean`                                | `false`    | Blocks the rest of the page while open   |
| `disabled`          | `boolean`                                | `false`    | The trigger does not open anything       |

**Events:** `update:open`, `open`, `close`.

**Slots:** `trigger` (`{ open }`), `default` (`{ close }`).

## DropdownItem

`WxDropdownItem` is one row of a menu. It closes the panel when clicked — through the same
`close-on-click` setting — and renders a `<button>`, an `<a>` with `href`, or any component through
`as`.

```vue
<template>
  <wx-dropdown-item icon="download" @click="exportCsv">
    Export
    <template #after>CSV</template>
  </wx-dropdown-item>

  <wx-dropdown-item icon="external-link" href="/admin/pages/12" target="_blank"
    >Open</wx-dropdown-item
  >
  <wx-dropdown-item :as="RouterLink" :to="{ name: 'pages.edit' }">Edit</wx-dropdown-item>
</template>
```

| Prop       | Type                                                           | Default     | Description                      |
| ---------- | -------------------------------------------------------------- | ----------- | -------------------------------- |
| `icon`     | `string`                                                       | —           | Icon before the label            |
| `href`     | `string`                                                       | —           | Renders an `<a>`                 |
| `target`   | `string`                                                       | —           | Target of that link              |
| `as`       | `string \| Component`                                          | —           | Render through another component |
| `tone`     | `'default' \| 'primary' \| 'danger' \| 'success' \| 'warning'` | `'default'` | Colour role                      |
| `active`   | `boolean`                                                      | `false`     | Marks the row as the current one |
| `disabled` | `boolean`                                                      | `false`     | Inert row                        |

**Events:** `click` (`MouseEvent`). **Slots:** `default`, `before`, `after`.

An `<hr />` between items renders as a divider.
