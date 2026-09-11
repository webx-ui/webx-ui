<script setup lang="ts">
import { computed } from 'vue'
import type { FooterProps } from './types'

defineOptions({ name: 'WxFooter' })

const props = withDefaults(defineProps<FooterProps>(), {
  height: undefined,
  bordered: true,
  padding: 'md',
})

const height = computed(() => {
  if (props.height === undefined) return undefined
  return typeof props.height === 'number' ? `${props.height}px` : props.height
})

const classes = computed(() => [
  'wx-footer',
  `wx-footer--padding-${props.padding}`,
  { 'wx-footer--bordered': props.bordered },
])
</script>

<template>
  <footer :class="classes" :style="height ? { '--wx-footer-height': height } : undefined">
    <slot />
  </footer>
</template>

<style scoped>
.wx-footer {
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
  box-sizing: border-box;
  flex: 0 0 auto;
  /* No fixed height by default: a footer of links grows with what it holds. */
  min-height: var(--wx-footer-height, 48px);
  padding-block: var(--wx-space-8);
  padding-inline: var(--wx-footer-padding, var(--wx-space-16));
  background: var(--wx-bg-surface);
  color: var(--wx-text-muted);
  font-family: var(--wx-font-family-sans);
  font-size: var(--wx-font-size-sm);
}

.wx-footer--padding-none {
  --wx-footer-padding: 0px;
}

.wx-footer--padding-sm {
  --wx-footer-padding: var(--wx-space-12);
}

.wx-footer--padding-md {
  --wx-footer-padding: var(--wx-space-16);
}

.wx-footer--padding-lg {
  --wx-footer-padding: var(--wx-space-24);
}

.wx-footer--bordered {
  border-top: 1px solid var(--wx-border-default);
}
</style>
