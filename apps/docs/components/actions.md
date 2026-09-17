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

## The tooltip

`title` is drawn by `WxTooltip`, not by the browser: one shape across a panel, a delay of its own
and a side that can be turned away from the edge of a dialog with `tooltipSide`. The attribute is
gone from the markup, so an action is found by its name rather than by `[title=…]`.

A greyed action keeps its tooltip, which is when an icon needs it most. That is why `disabled`
puts `aria-disabled` on the control instead of the attribute: a disabled button receives no
pointer events at all, so nothing would ever open. Clicks and keys are turned away all the same,
and the control stays out of the tab order.

## Accessibility

Every action has an accessible name — `label` if given, otherwise `title`, otherwise the English
name of the type. In a localised admin panel, pass `title`: it is both the tooltip and the name a
screen reader reads.

The tooltip comes with a portal beside the control, so an action is not a single root node: in a
test, reach for the `button` inside the wrapper rather than for the wrapper itself.

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

`collapse="always"` skips the measuring: the actions are a menu at every width, and the row is
never built. That is what a list wants when every record in a panel has to open the same way —
one place to look, whether a row offers one action or seven — and it is why the container's width
stops mattering.

```vue
<wx-actions collapse="always" align="end" aria-label="Page actions">
  <template #collapsed>
    <wx-dropdown-item icon="edit">Open</wx-dropdown-item>
    <hr class="wx-dropdown__divider" />
    <wx-dropdown-item icon="trash" tone="danger">Delete</wx-dropdown-item>
  </template>
</wx-actions>
```

## WxAction props

| Prop          | Type                                                           | Default  | Description                                |
| ------------- | -------------------------------------------------------------- | -------- | ------------------------------------------ |
| `type`        | `ActionType`                                                   | `'edit'` | What the action does — see the table above |
| `icon`        | `string`                                                       | by type  | Icon to draw instead                       |
| `tone`        | `'primary' \| 'danger' \| 'success' \| 'warning' \| 'neutral'` | by type  | Colour to use instead                      |
| `title`       | `string`                                                       | —        | Tooltip, and the accessible name           |
| `label`       | `string`                                                       | by type  | Accessible name on its own                 |
| `href`        | `string`                                                       | —        | Renders an `<a>`                           |
| `target`      | `string`                                                       | —        | Target of that link                        |
| `as`          | `string \| Component`                                          | —        | Render through another component           |
| `disabled`    | `boolean`                                                      | `false`  | Visible, greyed and inert                  |
| `hidden`      | `boolean`                                                      | `false`  | Draws nothing, keeps the square            |
| `size`        | `'sm' \| 'md' \| 'lg'`                                         | group's  | 30, 36 or 42 pixels                        |
| `tooltipSide` | `'top'                                                         | 'right'  | 'bottom'                                   | 'left'` | `'top'` | Where the tooltip prefers to sit |

**Events:** `click` (`MouseEvent`). **Slot:** `default` — replaces the icon.

## WxActions props

| Prop        | Type                           | Default   | Description                                                               |
| ----------- | ------------------------------ | --------- | ------------------------------------------------------------------------- |
| `align`     | `'start' \| 'center' \| 'end'` | `'start'` | Where the row sits in its space                                           |
| `size`      | `'sm' \| 'md' \| 'lg'`         | `'md'`    | Size for actions that set none                                            |
| `collapse`  | `boolean \| 'always'`          | `false`   | Fold into a dropdown when it does not fit; `'always'` never draws the row |
| `ariaLabel` | `string`                       | —         | Accessible name of the group                                              |

**Models:** `v-model:menuOpen` — whether the folded-up menu is showing.

**Events:** `collapse` (`boolean`) — the row folded, or came back out.

**Slots:** `default` — the actions; `collapsed` — what the menu shows; `trigger` — the button that
opens it.

`menuOpen` is there because the menu's panel is teleported. A row that hides itself until the
pointer is over it — the buttons on a card, say — loses that pointer the moment the menu opens, and
would fade out from under the panel hanging off it. Reading the model is how it knows to stay:

```vue
<wx-actions v-model:menu-open="menuOpen" collapse :class="{ 'is-busy': menuOpen }">…</wx-actions>
```

The gap between actions is a variable:

```vue
<template>
  <wx-actions style="--wx-actions-gap: 2px">…</wx-actions>
</template>
```
