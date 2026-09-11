<script setup lang="ts">
import { computed } from 'vue'
import type { ContainerProps } from './types'

defineOptions({ name: 'WxContainer' })

const props = withDefaults(defineProps<ContainerProps>(), {
  direction: 'vertical',
  fullHeight: false,
  viewport: false,
  as: 'div',
})

const classes = computed(() => [
  'wx-container',
  `wx-container--${props.direction}`,
  {
    'wx-container--full-height': props.fullHeight,
    'wx-container--viewport': props.viewport,
  },
])
</script>

<template>
  <component :is="as" :class="classes">
    <slot />
  </component>
</template>

<style scoped>
.wx-container {
  display: flex;
  box-sizing: border-box;
  flex: 1 1 auto;
  /*
   * A flex item refuses to shrink below its content by default, which is what makes
   * a scrolling main column overflow the page instead of scrolling inside it.
   */
  min-width: 0;
  min-height: 0;
  background: var(--wx-bg-body);
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
}

.wx-container--vertical {
  flex-direction: column;
}

.wx-container--horizontal {
  flex-direction: row;
}

.wx-container--full-height {
  /* `dvh` rather than `vh`: on a phone the browser chrome eats the difference. */
  min-height: 100dvh;
}

/*
 * An application shell rather than a document: the container *is* the viewport, and
 * a column inside it scrolls instead of the page. Without the cap, a `WxMain` marked
 * `scroll` has nothing to scroll against — the container grows with the content, and
 * the pane never overflows, so it never scrolls and the page ends up clipped.
 */
.wx-container--viewport {
  height: 100dvh;
  max-height: 100dvh;
  overflow: hidden;
}

/* Nested: an inner container fills the shell it is in rather than the window again. */
.wx-container--viewport .wx-container--viewport {
  height: auto;
  max-height: none;
}
</style>
