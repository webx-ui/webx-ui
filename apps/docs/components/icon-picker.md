<script setup>
import IconPickerDemo from '../components/demos/IconPickerDemo.vue'
import IconPickerSetDemo from '../components/demos/IconPickerSetDemo.vue'
</script>

# IconPicker

`WxIconPicker` is a field that holds the name of an icon and shows the icon itself. The box is
the search: typing filters the set, clicking an icon chooses it.

<IconPickerDemo />

## Usage

```vue
<script setup lang="ts">
import { ref } from 'vue'

const icon = ref<string | null>('grid')
</script>

<template>
  <wx-icon-picker v-model="icon" clearable />
</template>
```

The model is an icon name, or `null` when the field is empty — the same string `<wx-icon>` takes.
Icons a site registered with `registerIcons` are in the panel too: the list is `iconNames()`,
built-ins and registered ones alike.

## A set of its own

Without props the field picks from the panel's set. `icons` gives it another list, and `sprite`
says how to draw it: the address of an SVG sprite, each name a `<symbol id="<name>">` in it, drawn
with `<use href="<sprite>#<name>">` in the colour of the text around it. The grid, the search and
the preview are all the set's; a name outside it is marked unknown, the panel's icons included.

<IconPickerSetDemo />

```vue
<wx-icon-picker
  v-model="icon"
  :icons="['area', 'bath', 'bed']"
  sprite="/themes/icons/3f2a…/icons.svg"
/>
```

`icons` without `sprite` is a part of the panel's set, drawn by `WxIcon` — a field that offers
four icons rather than a hundred and fifty.

### The icons of the site's theme

What is picked in a panel field is often printed by the site's theme: `<x-theme::icon name="…">`
draws from the theme chain's sprite (`icons/<name>.svg` of each layer, published by
`webx:theme:sync`). That is a set of its own — other names, another drawing — and registering it
with `registerIcons` would not do: the theme's `close` and `check` would redraw every close and
check of the panel, and the panel's set would still be in the grid.

So a screen names the source rather than the icons, and `module-admin` spells it out when it
serves the screen — the names of `ThemeIcons::names()` and the address of the published sprite:

```json
{ "id": "icon", "type": "wx-icon-picker", "name": "icon", "props": { "source": "theme" } }
```

The same in a patch of a module's screen, inside a `wx-repeater` as well:
`{ "op": "set", "target": "feature-icon", "props": { "source": "theme" } }`.

On save the server holds a **new** name to the set — `There is no icon "search" in this set` —
and lets through a name the record already held, so an icon the theme has since dropped is marked
in the field and does not stop the record from saving. Without a set of its own the server checks
only that the name is a short string, as before: the panel's set lives in the browser. Before the
first `webx:theme:sync` there is no sprite to point at: the names are the theme's, drawn by the
panel's icons where it has them. More in [Screens](../guide/screens.md#icons-of-the-site-s-theme).

## Why not a text field

An unknown name draws nothing at all. `WxIcon` has no placeholder and no warning for a name the
set does not have, so `file-text` where the set says `file-txt` is a label that has quietly moved
to where the picture should have been — and nothing in the interface says so. This field cannot
hold a name like that: what is typed is a filter, and the value only ever becomes a name that was
picked out of the panel. Typing a name in full and pressing <kbd>Enter</kbd> picks it, since by
then it is the only match left.

A name can still arrive from elsewhere — a type imported from a file, a field that was a text box
last year, an icon a site registered and then stopped registering. The field marks it: the
preview shows a warning glyph and says so in its tooltip, rather than the same emptiness as "no
icon chosen".

## Props

| Prop          | Type                   | Default                  | Description                                  |
| ------------- | ---------------------- | ------------------------ | -------------------------------------------- |
| `modelValue`  | `string \| null`       | `null`                   | Icon name                                    |
| `icons`       | `string[]`             | the panel's set          | The names to pick from                       |
| `sprite`      | `string`               | —                        | SVG sprite that draws `icons`                |
| `clearable`   | `boolean`              | `false`                  | Show a button that empties the field         |
| `placeholder` | `string`               | `'Search icons'`         | Placeholder of the search box                |
| `emptyText`   | `string`               | `'No icon of that name'` | Shown when nothing matches                   |
| `unknownText` | `string`               | see below                | Tooltip when the held name is not in the set |
| `clearLabel`  | `string`               | `'Clear'`                | Accessible name of the clear button          |
| `teleport`    | `boolean`              | `true`                   | Render the panel in a portal                 |
| `disabled`    | `boolean`              | —                        | Inherited from the form field when unset     |
| `size`        | `'sm' \| 'md' \| 'lg'` | —                        | Inherited from the form field when unset     |
| `status`      | `'default' \| 'error'` | —                        | Inherited from the form field when unset     |
| `ariaLabel`   | `string`               | —                        | Accessible name when there is no `<label>`   |

## Events

| Event               | Payload          | When                                    |
| ------------------- | ---------------- | --------------------------------------- |
| `update:modelValue` | `string \| null` | An icon is picked, or the field emptied |
| `change`            | `string \| null` | The same, for forms that listen for it  |
