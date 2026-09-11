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
    <!-- The far end of the bar: the copyright, a version, a link to the status page. -->
    <div v-if="$slots.end" class="wx-footer__end">
      <slot name="end" />
    </div>
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

.wx-footer__end {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  /* Everything before it keeps its place; this group takes the far end. */
  margin-inline-start: auto;
  min-width: 0;
}

.wx-footer--padding-none {
  --wx-footer-padding: 0px;
}

.wx-footer--padding-sm {
  --wx-footer-padding: var(--wx-space-6);
}

/* The same gutter as the header, so the two bars stay a pair. */
.wx-footer--padding-md {
  --wx-footer-padding: var(--wx-space-10);
}

.wx-footer--padding-lg {
  --wx-footer-padding: var(--wx-space-16);
}

.wx-footer--bordered {
  border-top: 1px solid var(--wx-border-default);
}

/* The same give as the header on a small screen. */
@media (max-width: 640px) {
  .wx-footer--padding-lg {
    --wx-footer-padding: var(--wx-space-10);
  }
}
</style>
