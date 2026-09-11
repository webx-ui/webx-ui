<script setup lang="ts">
import { computed } from 'vue'
import type { ContainerProps } from './types'

defineOptions({ name: 'WxContainer' })

const props = withDefaults(defineProps<ContainerProps>(), {
  direction: 'vertical',
  fullHeight: false,
  as: 'div',
})

const classes = computed(() => [
  'wx-container',
  `wx-container--${props.direction}`,
  { 'wx-container--full-height': props.fullHeight },
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
</style>
