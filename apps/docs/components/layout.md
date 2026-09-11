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
  <wx-container viewport>
    <wx-header>Admin</wx-header>

    <wx-container direction="horizontal">
      <wx-aside>
        <wx-menu v-model="section">…</wx-menu>
      </wx-aside>

      <wx-main scroll>
        <router-view />
      </wx-main>
    </wx-container>

    <wx-footer>WebX UI</wx-footer>
  </wx-container>
</template>
```

`viewport` belongs on the outermost container only. It makes the shell exactly `100dvh` — the
dynamic viewport unit, so the browser chrome on a phone does not cut it off — and the column
inside it is what scrolls. See [Scrolling](#scrolling) for the other half of that choice.

## Scrolling

By default the page scrolls. For the admin shape — chrome that stays put, content that moves —
the outermost container becomes `viewport` and the main column scrolls inside it:

```vue
<template>
  <wx-container viewport>
    <wx-header>Admin</wx-header>

    <wx-container direction="horizontal">
      <wx-aside scroll>…</wx-aside>
      <wx-main scroll>…</wx-main>
    </wx-container>
  </wx-container>
</template>
```

`viewport` is what makes `scroll` mean anything. It caps the shell at `100dvh` so the column
inside it overflows and scrolls; with `full-height` — `min-height`, no cap — the container simply
grows with its content, nothing ever overflows, and a `scroll` column never scrolls.

Pick by what the page is: `viewport` for an application, `full-height` for a document whose page
scrolls as a whole.

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
    <wx-container viewport>
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

        <wx-main scroll>
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
phone breakpoint is absolute — a 240px column beside a 375px screen leaves nothing to read.

### What is remembered, and what is not

The two answers the button can give are not worth the same:

- **Closed by hand** is a decision. It holds at every width that has room for a sidebar, and with
  `persist` it holds across reloads too.
- **Open** only says "not collapsed, here". It is let go the moment the screen changes size class,
  so the width decides again: a desktop opens the sidebar, a tablet still starts with the rail.

Without that second rule a sidebar opened on a desktop would be sitting there on a tablet, 240px
wide, over a screen with no room for it. With it, the reader who wants the rail keeps the rail, and
everybody else gets whatever the screen can take.

```ts
const shell = useResponsiveShell(el, { persist: 'admin-sidebar' })
```

`persist` is a key in `localStorage`, and only a closed sidebar is written under it — opening one
clears the key rather than storing the opposite, since an open sidebar is the default anyway.

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
rail whatever the width, and `persist` is the key above. Pass nothing at all to measure the page
itself:

```ts
const shell = useResponsiveShell(undefined, { tablet: 1200 })
```

It measures an **element**, not the viewport, for the same reason the [grid](/components/grid) does:
a shell inside a preview, a split screen or the demo box above is narrow whatever the window says.
On a real page the two are the same number. `shellLayoutFor(width, collapsed, options)` is the rule
on its own, if you would rather drive the state yourself.

### The gutter the bars stand in

`WxHeader` and `WxFooter` are chrome rather than content, and their `md` padding is 10px: that puts
the centre of a 36px control in the header exactly above the centre of the icons in the rail below
it, which is what makes a sidebar toggle look like it belongs to the sidebar. A header carrying
nothing but a title can take `padding="lg"`.

The main column keeps the roomier 24px, and gives some of it up on a small screen: under 640px it
drops to 16px and under 420px to 12px, because 24px of margin around a form is air on a desktop and
a third of the line on a phone.

## A bar in the header

Not every admin panel wants a sidebar. A screen that is mostly one wide table reads better with the
navigation across the top and the whole width left for the content — the same shell with a
horizontal [Menu](/components/menu) in the header and no `WxAside` at all.

<LayoutTopbarDemo />

```vue
<template>
  <wx-container viewport>
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

| Prop         | Type                         | Default      | Description                        |
| ------------ | ---------------------------- | ------------ | ---------------------------------- |
| `direction`  | `'vertical' \| 'horizontal'` | `'vertical'` | How the children stack             |
| `fullHeight` | `boolean`                    | `false`      | At least as tall as the viewport   |
| `viewport`   | `boolean`                    | `false`      | Exactly the viewport; panes scroll |
| `as`         | `string \| Component`        | `'div'`      | The element to render              |

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

## Slots

Every part takes a `default` slot, and the two bars take one more: `end` — a group pushed to the
far side of the bar. That is where the user menu goes, and the notifications beside it:

```vue
<template>
  <wx-header>
    <wx-action icon="sidebar" label="Toggle the sidebar" @click="toggle" />
    <strong>Admin</strong>

    <template #end>
      <wx-indicator :value="3">
        <wx-action icon="bell" title="Notifications" />
      </wx-indicator>

      <wx-dropdown align="end">
        <template #trigger>
          <wx-button variant="text" size="sm">
            <template #icon><wx-icon name="user" /></template>
            Alex <wx-icon name="chevron-down" size="0.85em" />
          </wx-button>
        </template>

        <wx-dropdown-item icon="user" :as="RouterLink" to="/admin/profile"
          >Profile</wx-dropdown-item
        >
        <wx-dropdown-item icon="settings">Preferences</wx-dropdown-item>
        <hr />
        <wx-dropdown-item icon="logout" tone="danger" @click="signOut">Sign out</wx-dropdown-item>
      </wx-dropdown>
    </template>
  </wx-header>
</template>
```

Whatever is in the `default` slot keeps its place at the start of the bar; `end` is a flex group of
its own with its own gap, so the account button and the bell sit together without a wrapper of
their own. `WxFooter` has the same pair — links on one side, the copyright or a version on the
other.
