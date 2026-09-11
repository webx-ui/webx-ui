<script setup lang="ts">
import { computed } from 'vue'
import type { AsideProps } from './types'

defineOptions({ name: 'WxAside' })

const props = withDefaults(defineProps<AsideProps>(), {
  width: undefined,
  collapsedWidth: undefined,
  collapsed: false,
  side: 'start',
  bordered: true,
  scroll: false,
})

function toLength(value: number | string | undefined) {
  if (value === undefined) return undefined
  return typeof value === 'number' ? `${value}px` : value
}

const style = computed(() => {
  const vars: Record<string, string> = {}
  const width = toLength(props.width)
  const collapsedWidth = toLength(props.collapsedWidth)
  if (width) vars['--wx-aside-width'] = width
  if (collapsedWidth) vars['--wx-aside-collapsed-width'] = collapsedWidth
  return vars
})

const classes = computed(() => [
  'wx-aside',
  `wx-aside--${props.side}`,
  {
    'wx-aside--bordered': props.bordered,
    'wx-aside--collapsed': props.collapsed,
    'wx-aside--scroll': props.scroll,
  },
])
</script>

<template>
  <aside :class="classes" :style="style">
    <slot />
  </aside>
</template>

<style scoped>
.wx-aside {
  display: flex;
  flex-direction: column;
  box-sizing: border-box;
  /* Fixed width, and it never shrinks when the main column is crowded. */
  flex: 0 0 var(--wx-aside-width, 240px);
  width: var(--wx-aside-width, 240px);
  min-height: 0;
  background: var(--wx-bg-surface);
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
  transition: flex-basis var(--wx-duration-normal) var(--wx-easing-standard);
}

/*
 * The rail is one icon wide plus the air around it — 56px, not the 64 it started
 * at, which left the icons floating in a column of their own.
 */
.wx-aside--collapsed {
  flex-basis: var(--wx-aside-collapsed-width, 56px);
  width: var(--wx-aside-collapsed-width, 56px);
}

.wx-aside--bordered.wx-aside--start {
  border-right: 1px solid var(--wx-border-default);
}

.wx-aside--bordered.wx-aside--end {
  border-left: 1px solid var(--wx-border-default);
}

/*
 * A sidebar that scrolls on its own sticks to the viewport, so a long menu can be
 * reached while a long page scrolls behind it.
 *
 * `max-height` rather than `height`: the column has to answer to both shapes it is
 * used in. Under a header in a shell that fills the screen it is already the height
 * of its row, and a hard `100dvh` there is a header taller than the window — the
 * whole layout then overflows by exactly the header. On a page that scrolls, the row
 * is as tall as the content, and the cap is what keeps the sticky column in view.
 */
.wx-aside--scroll {
  position: sticky;
  top: 0;
  max-height: 100dvh;
  overflow-y: auto;
  overscroll-behavior: contain;
}

@media (prefers-reduced-motion: reduce) {
  .wx-aside {
    transition: none;
  }
}
</style>
