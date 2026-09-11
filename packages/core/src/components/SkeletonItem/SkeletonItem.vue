<script setup lang="ts">
import { computed } from 'vue'
import type { SkeletonItemProps } from '../Skeleton/types'

defineOptions({ name: 'WxSkeletonItem' })

const props = withDefaults(defineProps<SkeletonItemProps>(), {
  variant: 'text',
  width: undefined,
  height: undefined,
  animated: true,
})

function length(value: string | number | undefined) {
  if (value === undefined) return undefined
  return typeof value === 'number' ? `${value}px` : value
}

const classes = computed(() => [
  'wx-skeleton-item',
  `wx-skeleton-item--${props.variant}`,
  { 'wx-skeleton-item--animated': props.animated },
])

const style = computed(() => ({
  width: length(props.width),
  height: length(props.height),
}))
</script>

<template>
  <span :class="classes" :style="style" aria-hidden="true" />
</template>

<style scoped>
.wx-skeleton-item {
  display: block;
  box-sizing: border-box;
  background: var(--wx-bg-fill);
  border-radius: var(--wx-radius-xs);
}

.wx-skeleton-item--text {
  width: 100%;
  height: 0.85em;
  border-radius: var(--wx-radius-xs);
}

.wx-skeleton-item--title {
  width: 40%;
  height: 1.25em;
}

.wx-skeleton-item--circle {
  width: 32px;
  height: 32px;
  flex: 0 0 auto;
  border-radius: var(--wx-radius-full);
}

.wx-skeleton-item--square {
  width: 32px;
  height: 32px;
  flex: 0 0 auto;
}

.wx-skeleton-item--image {
  width: 100%;
  height: 140px;
  border-radius: var(--wx-radius-sm);
}

.wx-skeleton-item--button {
  width: 96px;
  height: var(--wx-size-control-md);
  border-radius: var(--wx-radius-control);
}

/*
 * A sheen travelling across the fill rather than the whole block pulsing. A pulse
 * reads as something blinking at you; a sheen reads as work in progress, which is
 * what it is.
 */
.wx-skeleton-item--animated {
  background-image: linear-gradient(
    90deg,
    transparent 0%,
    var(--wx-bg-surface) 40%,
    var(--wx-bg-surface) 60%,
    transparent 100%
  );
  background-repeat: no-repeat;
  background-size: 240px 100%;
  animation: wx-skeleton-sheen 1.4s ease-in-out infinite;
}

@keyframes wx-skeleton-sheen {
  from {
    background-position: -240px 0;
  }

  to {
    background-position: calc(100% + 240px) 0;
  }
}

/*
 * Reduced motion means the sheen stops; the placeholder stays, because it is the
 * message. A still block still says "not yet".
 */
@media (prefers-reduced-motion: reduce) {
  .wx-skeleton-item--animated {
    background-image: none;
    animation: none;
  }
}
</style>
