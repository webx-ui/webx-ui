<script setup>
import LayoutDemo from '../components/demos/LayoutDemo.vue'
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

`collapsed` narrows the column to `collapsed-width`, 64px by default. Pair it with a collapsed
[Menu](/components/menu) and the sidebar becomes an icon rail whose submenus open as flyouts:

```vue
<script setup lang="ts">
import { ref } from 'vue'

const collapsed = ref(false)
</script>

<template>
  <wx-aside :collapsed="collapsed" :width="240" :collapsed-width="64">
    <wx-menu v-model="section" :collapsed="collapsed">…</wx-menu>
  </wx-aside>
</template>
```

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
| `collapsedWidth` | `number \| string` | `64px`    | Width once collapsed                     |
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
