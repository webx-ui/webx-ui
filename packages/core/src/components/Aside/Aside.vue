<script setup lang="ts">
import { computed, useSlots } from 'vue'
import type { AsideProps } from './types'

defineOptions({ name: 'WxAside' })

const props = withDefaults(defineProps<AsideProps>(), {
  width: undefined,
  collapsedWidth: undefined,
  collapsed: false,
  side: 'start',
  bordered: true,
  scroll: false,
  sticky: false,
  floating: false,
})

const slots = useSlots()

/*
 * A sidebar with zones is a different column from a sidebar with a menu in it: the
 * middle one grows and scrolls, and the two around it stay where they are. Whether
 * it is one or the other is not a prop — it is whether the caller filled the zones.
 */
const zoned = computed(() => slots.top !== undefined || slots.bottom !== undefined)

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
    'wx-aside--sticky': props.sticky,
    'wx-aside--floating': props.floating,
    'wx-aside--zoned': zoned.value,
  },
])
</script>

<template>
  <aside :class="classes" :style="style">
    <div v-if="$slots.top" class="wx-aside__top">
      <slot name="top" />
    </div>

    <!--
      Wrapped only where there is something to wrap it against. An element between
      the column and its menu is one more thing for a caller's own layout to reckon
      with, and a sidebar that is nothing but a menu has no use for it.
    -->
    <div v-if="zoned" class="wx-aside__body">
      <slot />
    </div>

    <slot v-else />

    <div v-if="$slots.bottom" class="wx-aside__bottom">
      <slot name="bottom" />
    </div>
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
.wx-aside--scroll:not(.wx-aside--zoned) {
  position: sticky;
  top: 0;
  max-height: 100dvh;
  overflow-y: auto;
  overscroll-behavior: contain;
}

/* The zones stay; only what is between them moves. */
.wx-aside__top,
.wx-aside__bottom {
  flex: 0 0 auto;
}

.wx-aside__body {
  display: flex;
  flex-direction: column;
  flex: 1 1 auto;
  /* Without this the menu is as tall as its content and nothing ever scrolls. */
  min-height: 0;
}

.wx-aside--zoned.wx-aside--scroll .wx-aside__body {
  overflow-y: auto;
  overscroll-behavior: contain;
}

/*
 * The column stands still and the page moves past it. Its height is its own — it is
 * not the height of a row it shares with the content any more, so it has to be told
 * one, and `100dvh` is the answer unless a shell insets it.
 */
.wx-aside--sticky {
  position: sticky;
  top: var(--wx-aside-top, 0px);
  height: var(--wx-aside-height, 100dvh);
}

/*
 * A card rather than a wall. No `overflow` of its own: a rail opens its section
 * names in flyouts beside itself, and a box that clips is a box they cannot leave.
 */
.wx-aside--floating {
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  box-shadow: var(--wx-shadow-card);
}

@media (prefers-reduced-motion: reduce) {
  .wx-aside {
    transition: none;
  }
}
</style>
