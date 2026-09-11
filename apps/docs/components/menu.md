<script setup>
import MenuDemo from '../components/demos/MenuDemo.vue'
</script>

# Menu

`WxMenu` is the navigation of an admin panel: the sidebar down the left, or the bar across the top.
`WxMenuItem` is an entry, `WxSubmenu` a branch that opens, `WxMenuGroup` a heading over a few
entries that do not need one.

<MenuDemo />

## Usage

```vue
<script setup lang="ts">
import { ref } from 'vue'

const section = ref('pages')
</script>

<template>
  <wx-menu v-model="section" label="Main navigation" @select="onSelect">
    <wx-menu-item value="dashboard" icon="home" label="Dashboard" href="/admin" />
    <wx-submenu value="content" icon="file" title="Content">
      <wx-menu-item value="pages" label="Pages" href="/admin/pages" />
      <wx-menu-item value="media" label="Media" href="/admin/media" />
    </wx-submenu>
  </wx-menu>
</template>
```

`v-model` holds the value of the selected entry, and `select` fires with that value and the click
that caused it. The menu selects, it does not navigate: give an entry an `href`, or render it
through your router, and routing stays where it belongs.

## Entries, links and routes

An entry without `href` is a `<button>`; with one it is an `<a>`. To route through Vue Router,
hand `as` the component and pass its props straight through:

```vue
<template>
  <wx-menu-item value="pages" :as="RouterLink" to="/admin/pages" label="Pages" />
</template>
```

The active entry gets `aria-current="page"` when it is a link, and `aria-pressed` when it is a
button — the same highlight either way, described to a screen reader as what it actually is.

## Branches

A submenu opens inline in a sidebar. `v-model:open` holds the open branches if you want to control
them; `accordion` keeps one open at a time:

```vue
<template>
  <wx-menu v-model="section" v-model:open="openBranches" accordion>…</wx-menu>
</template>
```

Left alone, the branch holding the active entry opens itself — a sidebar rendered against a nested
route should show where you are without being told. `:auto-expand="false"` turns that off.

## Collapsed sidebar

`collapsed` hides the labels and leaves an icon rail. Branches there have nowhere to open, so they
open as flyouts beside the rail instead — the same happens in a horizontal bar, where they drop
below the trigger. Only the first branch becomes a panel: inside one there is room again, and the
branches below it open inline in the same panel, so a menu three levels deep stays one panel wide
instead of trailing a chain of them across the screen.

```vue
<template>
  <wx-aside :collapsed="collapsed">
    <wx-menu v-model="section" :collapsed="collapsed">…</wx-menu>
  </wx-aside>
</template>
```

Give every top-level entry an `icon` before collapsing a menu — the icon is all that is left of it.
The `label` prop doubles as the entry's title on hover, so the rail is not a row of guesses.

A panel closes when an entry in it is chosen, on Escape, and on a click outside it — but not on
just any click inside itself, since the branches in it are opened by clicking too. Its open state
is its own and never reaches `v-model:open`: a flyout belongs to the pointer, and collapsing a
sidebar should not throw open a panel for every branch that happened to be expanded.

## Horizontal

```vue
<template>
  <wx-header>
    <wx-menu v-model="section" mode="horizontal" label="Sections">
      <wx-menu-item value="overview" label="Overview" />
      <wx-submenu value="content" title="Content">
        <wx-menu-item value="pages" label="Pages" />
      </wx-submenu>
    </wx-menu>
  </wx-header>
</template>
```

`collapsed` is ignored in a bar — there is nothing to collapse.

## Menu

| Prop         | Type                         | Default      | Description                               |
| ------------ | ---------------------------- | ------------ | ----------------------------------------- |
| `mode`       | `'vertical' \| 'horizontal'` | `'vertical'` | A sidebar, or a bar                       |
| `size`       | `'sm' \| 'md'`               | `'md'`       | Row height and text size                  |
| `collapsed`  | `boolean`                    | `false`      | Icon rail; vertical menus only            |
| `accordion`  | `boolean`                    | `false`      | One open branch at a time                 |
| `autoExpand` | `boolean`                    | `true`       | Opens the branch holding the active entry |
| `label`      | `string`                     | —            | Accessible name of the menu               |

**Models:** `v-model` (`string \| number`) — the selected entry; `v-model:open`
(`Array<string \| number>`) — the open branches.

**Events:** `select` (`value`, `MouseEvent`).

## MenuItem

| Prop       | Type                  | Default | Description                              |
| ---------- | --------------------- | ------- | ---------------------------------------- |
| `value`    | `string \| number`    | —       | What the menu reports as selected        |
| `icon`     | `IconName`            | —       | Icon before the label; all a rail shows  |
| `label`    | `string`              | —       | The label, and the title shown on a rail |
| `href`     | `string`              | —       | Renders the entry as a link              |
| `target`   | `string`              | —       | Link target; `_blank` gets a safe `rel`  |
| `as`       | `string \| Component` | —       | Renders through another component        |
| `disabled` | `boolean`             | `false` | Blocks selection                         |

**Events:** `click` (`MouseEvent`).

**Slots:** `default` — the label; `icon`; `trailing` — a badge or count at the end of the row.

## Submenu

| Prop       | Type               | Default | Description                    |
| ---------- | ------------------ | ------- | ------------------------------ |
| `value`    | `string \| number` | —       | The key the branch opens under |
| `icon`     | `IconName`         | —       | Icon before the title          |
| `title`    | `string`           | —       | The title, instead of a slot   |
| `disabled` | `boolean`          | `false` | Blocks opening                 |

**Events:** `toggle` (`boolean`).

**Slots:** `default` — the entries; `title`; `icon`.

## MenuGroup

| Prop    | Type     | Default | Description                                       |
| ------- | -------- | ------- | ------------------------------------------------- |
| `title` | `string` | —       | Heading above the entries; a plain rule on a rail |

**Slots:** `default` — the entries; `title`.

A group labels entries, it does not contain them the way a branch does: nothing under it is
indented, and it never opens or closes.

## Why it is a list of links

The menu renders `<ul>` and `<li>` holding links and buttons, not `role="menu"` with
`role="menuitem"`. That role promises the keyboard model of a desktop application menu — arrow
keys, type-ahead, one tab stop for the whole menu — and navigation that is really a list of links
is easier both to use and to read out as exactly what it is. Tab moves between entries, Enter
follows one, and a screen reader announces the list and its length.
