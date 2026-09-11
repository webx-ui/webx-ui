<script setup>
import ActionsDemo from '../components/demos/ActionsDemo.vue'
</script>

# Actions

`WxAction` is the square icon button at the end of a row — edit, duplicate, delete. `WxActions` is
the row they sit in, and it can fold itself into a dropdown when the column gets too narrow.

<ActionsDemo />

## Usage

```vue
<template>
  <wx-actions aria-label="Row actions">
    <wx-action type="edit" :href="`/admin/pages/${page.id}/edit`" />
    <wx-action type="copy" @click="duplicate(page)" />
    <wx-action type="remove" @click="confirmRemove(page)" />
  </wx-actions>
</template>
```

`type` is what the action does, and it picks three things at once: the icon, the colour and the
accessible name. That is what keeps a delete red and an edit blue on every screen without anyone
having to remember.

| `type`    | Icon          | Colour  | `type`     | Icon            | Colour  |
| --------- | ------------- | ------- | ---------- | --------------- | ------- |
| `add`     | plus          | primary | `upload`   | upload          | primary |
| `edit`    | edit          | primary | `download` | download        | primary |
| `remove`  | trash         | danger  | `sort`     | drag            | neutral |
| `copy`    | copy          | primary | `search`   | search          | primary |
| `link`    | link          | primary | `restore`  | refresh         | success |
| `goto`    | external-link | primary | `settings` | settings        | neutral |
| `details` | info          | primary | `send`     | mail            | primary |
| `view`    | eye           | primary | `more`     | more-horizontal | neutral |
| `hide`    | eye-off       | neutral |            |                 |         |

Any of the three can be overridden — `icon` takes any name from the [icon set](/components/icon),
including one you registered yourself:

```vue
<template>
  <wx-action type="add" icon="folder" title="New folder" />
  <wx-action type="edit" tone="warning" />
</template>
```

## Links and routes

An action with `href` renders an `<a>`; `as` renders it through another component, which is how it
becomes a router link:

```vue
<template>
  <wx-action type="edit" :as="RouterLink" :to="{ name: 'pages.edit', params: { id: page.id } }" />
</template>
```

## Keeping the row aligned

`hidden` draws nothing but keeps the square. A list where one record may not be deleted still lines
up with the rows where it may — which is the whole point of a column of actions:

```vue
<template>
  <wx-actions>
    <wx-action type="edit" />
    <wx-action type="remove" :hidden="!page.can_delete" />
  </wx-actions>
</template>
```

`disabled` is the other half of that choice: the action is visible, greyed and inert, which says
"not now" rather than "not for this record".

## Accessibility

Every action has an accessible name — `label` if given, otherwise `title`, otherwise the English
name of the type. In a localised admin panel, pass `title`: it is both the tooltip and the name a
screen reader reads.

## Collapsing

`collapse` measures the row against its container and folds it into a dropdown as soon as it no
longer fits. The `collapsed` slot is what the menu shows:

```vue
<template>
  <wx-actions collapse align="end" aria-label="Page actions">
    <wx-action type="edit" title="Edit" />
    <wx-action type="copy" title="Duplicate" />
    <wx-action type="remove" title="Delete" />

    <template #collapsed>
      <wx-dropdown-item icon="edit">Edit</wx-dropdown-item>
      <wx-dropdown-item icon="copy">Duplicate</wx-dropdown-item>
      <wx-dropdown-item icon="trash" tone="danger">Delete</wx-dropdown-item>
    </template>
  </wx-actions>
</template>
```

Leave `#collapsed` out and the menu shows the same actions the row holds. `#trigger` replaces the
`…` button.

The row is never unmounted, only taken out of the flow — that is how the component knows there is
room for it again. The container has to have a width of its own (a table cell, a card, a grid
column); a container that shrinks to fit its content has nothing to measure against.

## WxAction props

| Prop       | Type                                                           | Default  | Description                                |
| ---------- | -------------------------------------------------------------- | -------- | ------------------------------------------ |
| `type`     | `ActionType`                                                   | `'edit'` | What the action does — see the table above |
| `icon`     | `string`                                                       | by type  | Icon to draw instead                       |
| `tone`     | `'primary' \| 'danger' \| 'success' \| 'warning' \| 'neutral'` | by type  | Colour to use instead                      |
| `title`    | `string`                                                       | —        | Tooltip, and the accessible name           |
| `label`    | `string`                                                       | by type  | Accessible name on its own                 |
| `href`     | `string`                                                       | —        | Renders an `<a>`                           |
| `target`   | `string`                                                       | —        | Target of that link                        |
| `as`       | `string \| Component`                                          | —        | Render through another component           |
| `disabled` | `boolean`                                                      | `false`  | Visible, greyed and inert                  |
| `hidden`   | `boolean`                                                      | `false`  | Draws nothing, keeps the square            |
| `size`     | `'sm' \| 'md' \| 'lg'`                                         | group's  | 30, 36 or 42 pixels                        |

**Events:** `click` (`MouseEvent`). **Slot:** `default` — replaces the icon.

## WxActions props

| Prop        | Type                           | Default   | Description                               |
| ----------- | ------------------------------ | --------- | ----------------------------------------- |
| `align`     | `'start' \| 'center' \| 'end'` | `'start'` | Where the row sits in its space           |
| `size`      | `'sm' \| 'md' \| 'lg'`         | `'md'`    | Size for actions that set none            |
| `collapse`  | `boolean`                      | `false`   | Fold into a dropdown when it does not fit |
| `ariaLabel` | `string`                       | —         | Accessible name of the group              |

**Events:** `collapse` (`boolean`) — the row folded, or came back out.

**Slots:** `default` — the actions; `collapsed` — what the menu shows; `trigger` — the button that
opens it.

The gap between actions is a variable:

```vue
<template>
  <wx-actions style="--wx-actions-gap: 2px">…</wx-actions>
</template>
```
