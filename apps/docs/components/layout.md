<script setup>
import LayoutDemo from '../components/demos/LayoutDemo.vue'
import LayoutTopbarDemo from '../components/demos/LayoutTopbarDemo.vue'
</script>

# Layout

Five components make the shell an admin panel sits in: `WxContainer` stacks the parts,
`WxHeader`, `WxAside`, `WxMain` and `WxFooter` are the parts. Each renders the element it is named
after — `<header>`, `<aside>`, `<main>`, `<footer>` — so the page has real landmarks, and a screen
reader can jump to the content without being told how.

For the grid inside a screen, see [Row and Col](/components/grid).

<LayoutDemo />

## Usage

A container is either a column or a row, and the shell is the two nested:

```vue
<template>
  <wx-container full-height>
    <wx-header>Admin</wx-header>

    <wx-container direction="horizontal">
      <wx-aside>
        <wx-menu v-model="section">…</wx-menu>
      </wx-aside>

      <wx-main>
        <router-view />
      </wx-main>
    </wx-container>

    <wx-footer>WebX UI</wx-footer>
  </wx-container>
</template>
```

`full-height` belongs on the outermost container only: it is `min-height: 100dvh`, the dynamic
viewport unit, so the shell is not cut off by the browser chrome on a phone.

## Scrolling

By default the page scrolls. For the admin shape where the chrome stays put and only the content
moves, give the main column its own scroll and let the sidebar stick:

```vue
<template>
  <wx-container full-height>
    <wx-header sticky>Admin</wx-header>

    <wx-container direction="horizontal">
      <wx-aside scroll>…</wx-aside>
      <wx-main scroll>…</wx-main>
    </wx-container>
  </wx-container>
</template>
```

For a scrolling panel inside a screen — a log, a list of comments — use
[Scrollbar](/components/scrollbar) rather than a container.

## Collapsing the sidebar

`collapsed` narrows the column to `collapsed-width`, 56px by default. Pair it with a collapsed
[Menu](/components/menu) and the sidebar becomes an icon rail whose submenus open as flyouts:

```vue
<script setup lang="ts">
import { ref } from 'vue'

const collapsed = ref(false)
</script>

<template>
  <wx-aside :collapsed="collapsed" :width="240" :collapsed-width="56">
    <wx-menu v-model="section" :collapsed="collapsed">…</wx-menu>
  </wx-aside>
</template>
```

## A responsive sidebar

A 240px column beside a 375px screen leaves nothing to read, and an admin panel is expected to do
something about it: the full sidebar on a desktop, an icon rail on a tablet, and a burger on a
phone. Three shapes with two thresholds — `useResponsiveShell` is that rule, and nothing else.

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { useResponsiveShell } from '@webx-ui/core'

const shell = ref<HTMLElement | null>(null)
const { layout, collapsed, showAside, drawerOpen, toggle, close } = useResponsiveShell(shell)

const section = ref('pages')
</script>

<template>
  <div ref="shell">
    <wx-container full-height>
      <wx-header>
        <!-- A burger belongs to the drawer; while the sidebar is on the page it is a toggle. -->
        <wx-action
          :icon="layout === 'drawer' ? 'menu' : 'sidebar'"
          :label="layout === 'drawer' ? 'Menu' : 'Toggle the sidebar'"
          @click="toggle"
        />
        <strong>Admin</strong>
      </wx-header>

      <wx-container direction="horizontal">
        <wx-aside v-if="showAside" :collapsed="collapsed">
          <wx-menu v-model="section" :collapsed="collapsed">…</wx-menu>
        </wx-aside>

        <wx-main>
          <router-view />
        </wx-main>
      </wx-container>
    </wx-container>

    <wx-drawer v-model:open="drawerOpen" title="Menu" side="left" :size="260" closable>
      <wx-menu v-model="section" @select="close">…</wx-menu>
    </wx-drawer>
  </div>
</template>
```

One button does both jobs, and its icon says which: while the sidebar is on the page it collapses
and expands it, and a burger appears only once the menu has left the page altogether. Anything else
is a promise the button does not keep — a burger beside a visible menu offers to bring back
something that never went away.

The width chooses the shape, it does not hold it: on a tablet the sidebar starts as a rail, and the
button still expands it in place, because the reader can see what they are expanding. Only the
phone breakpoint is absolute — a 240px column beside a 375px screen leaves nothing to read, so
there the menu goes to the drawer and the preference is forgotten until it comes back.

| Returns      | Type                                           | Description                                    |
| ------------ | ---------------------------------------------- | ---------------------------------------------- |
| `width`      | `Ref<number>`                                  | Measured width; `0` until it has been measured |
| `layout`     | `ComputedRef<'sidebar' \| 'rail' \| 'drawer'>` | The shape at that width                        |
| `collapsed`  | `ComputedRef<boolean>`                         | For `WxAside` and `WxMenu`                     |
| `showAside`  | `ComputedRef<boolean>`                         | Whether there is room for a column at all      |
| `drawerOpen` | `Ref<boolean>`                                 | For `v-model:open` on the drawer               |
| `toggle`     | `() => void`                                   | Toggles the sidebar, or opens the drawer       |
| `close`      | `() => void`                                   | Closes the drawer — call it on `select`        |

Options: `phone` (640) and `tablet` (1024) are the thresholds, `collapsed` starts the sidebar as a
rail whatever the width. Pass nothing at all to measure the page itself:

```ts
const shell = useResponsiveShell(undefined, { tablet: 1200 })
```

It measures an **element**, not the viewport, for the same reason the [grid](/components/grid) does:
a shell inside a preview, a split screen or the demo box above is narrow whatever the window says.
On a real page the two are the same number. `shellLayoutFor(width, collapsed, options)` is the rule
on its own, if you would rather drive the state yourself.

The padding of the bars and of the main column needs no help: under 640px the header, the footer
and `WxMain` drop to 12px on their own — 24px of margin around a form is air on a desktop and a
third of the line on a phone.

## A bar in the header

Not every admin panel wants a sidebar. A screen that is mostly one wide table reads better with the
navigation across the top and the whole width left for the content — the same shell with a
horizontal [Menu](/components/menu) in the header and no `WxAside` at all.

<LayoutTopbarDemo />

```vue
<template>
  <wx-container full-height>
    <wx-header>
      <strong>Admin</strong>

      <wx-menu v-if="showBar" v-model="section" mode="horizontal" label="Main navigation">
        <wx-menu-item value="dashboard" icon="grid" label="Dashboard" />
        <wx-submenu value="content" icon="file" title="Content">
          <wx-menu-item value="pages" icon="file" label="Pages" />
        </wx-submenu>
      </wx-menu>

      <wx-action v-else icon="menu" label="Menu" @click="toggle" />
    </wx-header>

    <wx-main :max-width="1200">
      <router-view />
    </wx-main>
  </wx-container>
</template>
```

The same composable drives it — a bar has no rail, so only `showAside` is read from it, under the
name that fits: there is either room for the bar or there is the burger. Branches in a bar open as
flyouts, and in the drawer the very same menu opens them inline, because it is vertical there.

## Reading width

A form is unreadable at 1600px wide. `max-width` caps the content and centres it, while the
padding and the background still run the full width of the column:

```vue
<template>
  <wx-main :max-width="720">
    <wx-card title="Page settings">…</wx-card>
  </wx-main>
</template>
```

## Container

| Prop         | Type                         | Default      | Description                      |
| ------------ | ---------------------------- | ------------ | -------------------------------- |
| `direction`  | `'vertical' \| 'horizontal'` | `'vertical'` | How the children stack           |
| `fullHeight` | `boolean`                    | `false`      | At least as tall as the viewport |
| `as`         | `string \| Component`        | `'div'`      | The element to render            |

## Header

| Prop       | Type                             | Default | Description                      |
| ---------- | -------------------------------- | ------- | -------------------------------- |
| `height`   | `number \| string`               | `56px`  | Height of the bar                |
| `bordered` | `boolean`                        | `true`  | Rule along the bottom edge       |
| `sticky`   | `boolean`                        | `false` | Stays put while the page scrolls |
| `padding`  | `'none' \| 'sm' \| 'md' \| 'lg'` | `'md'`  | Inner padding                    |

## Aside

| Prop             | Type               | Default   | Description                              |
| ---------------- | ------------------ | --------- | ---------------------------------------- |
| `width`          | `number \| string` | `240px`   | Width of the column                      |
| `collapsedWidth` | `number \| string` | `56px`    | Width once collapsed                     |
| `collapsed`      | `boolean`          | `false`   | Narrows the column to the rail           |
| `side`           | `'start' \| 'end'` | `'start'` | Which edge carries the rule              |
| `bordered`       | `boolean`          | `true`    | Rule between the column and the page     |
| `scroll`         | `boolean`          | `false`   | Sticks to the viewport and scrolls alone |

## Main

| Prop       | Type                             | Default | Description                           |
| ---------- | -------------------------------- | ------- | ------------------------------------- |
| `padding`  | `'none' \| 'sm' \| 'md' \| 'lg'` | `'md'`  | Inner padding                         |
| `scroll`   | `boolean`                        | `false` | Scrolls on its own                    |
| `maxWidth` | `number \| string`               | —       | Caps the content width and centres it |

## Footer

| Prop       | Type                             | Default | Description               |
| ---------- | -------------------------------- | ------- | ------------------------- |
| `height`   | `number \| string`               | `48px`  | Minimum height of the bar |
| `bordered` | `boolean`                        | `true`  | Rule along the top edge   |
| `padding`  | `'none' \| 'sm' \| 'md' \| 'lg'` | `'md'`  | Inner padding             |

Every one of them takes a `default` slot and nothing else.
