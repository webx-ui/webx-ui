<script setup>
import ScrollbarDemo from '../components/demos/ScrollbarDemo.vue'
</script>

# Scrollbar

`WxScrollbar` is a box that scrolls with a bar that matches the rest of the admin panel — a log, a
list of comments, a tall filter panel, a table too wide for its column.

<ScrollbarDemo />

## Usage

```vue
<template>
  <wx-scrollbar :height="240">
    <p v-for="line in log" :key="line.id">{{ line.text }}</p>
  </wx-scrollbar>
</template>
```

`max-height` instead of `height` lets the box grow with its content and only start scrolling once
it would get too tall:

```vue
<template>
  <wx-scrollbar max-height="50vh">…</wx-scrollbar>
</template>
```

## Scrolling from code

The component exposes the scrolling element itself along with three helpers, so a log that follows
new lines needs no wrapper of its own:

```vue
<script setup lang="ts">
import { ref, nextTick } from 'vue'
import { WxScrollbar } from '@webx-ui/core'

const log = ref<InstanceType<typeof WxScrollbar> | null>(null)

async function append(line: string) {
  lines.value.push(line)
  await nextTick()
  log.value?.scrollToBottom('smooth')
}
</script>

<template>
  <wx-scrollbar ref="log" :height="240" @scroll="onScroll">…</wx-scrollbar>
</template>
```

| Exposed          | Signature                           | Description                         |
| ---------------- | ----------------------------------- | ----------------------------------- |
| `el`             | `HTMLElement \| null`               | The scrolling element               |
| `scrollTo`       | `(options: ScrollTarget) => void`   | Native `scrollTo`                   |
| `scrollToTop`    | `(behavior?: ScrollMotion) => void` | Back to the start                   |
| `scrollToBottom` | `(behavior?: ScrollMotion) => void` | To the end — a log following output |

## Sideways

```vue
<template>
  <wx-scrollbar axis="x" always>
    <div class="wide-row">…</div>
  </wx-scrollbar>
</template>
```

## Scroll chaining

A scroll that reaches the end of the box stops there rather than carrying on to the page behind
it — what you want for a panel inside a dialog. `chain-scroll` restores the browser default.

## Props

| Prop          | Type                   | Default | Description                                  |
| ------------- | ---------------------- | ------- | -------------------------------------------- |
| `height`      | `number \| string`     | —       | Fixed height                                 |
| `maxHeight`   | `number \| string`     | —       | Height it grows to before scrolling          |
| `axis`        | `'y' \| 'x' \| 'both'` | `'y'`   | Which way the content scrolls                |
| `size`        | `'sm' \| 'md'`         | `'md'`  | Thickness of the bar                         |
| `always`      | `boolean`              | `false` | Keeps the bar visible instead of on hover    |
| `chainScroll` | `boolean`              | `false` | Lets a scroll carry on to the page behind it |

**Events:** `scroll` (`Event`).

**Slots:** `default` — the content.

## What it is not

This is native scrolling, themed — not a pair of `<div>`s moved about in JavaScript. Keyboard
scrolling, momentum, the trackpad's own overlay bar and every accessibility setting the browser
has all keep working, and the component adds the colour and the thickness on top:
`scrollbar-width` and `scrollbar-color` where they are honoured, `::-webkit-scrollbar` where they
are not. The trade-off is that a browser which supports neither shows its own bar, which is a far
better failure than a panel that cannot be scrolled.

Both colours are variables, so a panel on a dark surface can correct them:

```vue
<template>
  <wx-scrollbar style="--wx-scrollbar-thumb: var(--wx-border-strong)">…</wx-scrollbar>
</template>
```
