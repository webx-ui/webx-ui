<script setup lang="ts">
import { computed } from 'vue'
import { resolveIcon } from './icons'
import type { IconProps } from './types'

defineOptions({ name: 'WxIcon' })

const props = withDefaults(defineProps<IconProps>(), {
  size: undefined,
  strokeWidth: 1.7,
  spin: false,
  label: undefined,
})

const keywordSizes: Record<string, string> = {
  sm: 'var(--wx-font-size-sm)',
  md: 'var(--wx-font-size-lg)',
  lg: 'var(--wx-font-size-2xl)',
}

/**
 * The markup is authored in `icons.ts` or handed to `registerIcons` by the app —
 * both are developer-controlled, which is what makes `v-html` safe here.
 */
const content = computed(() => resolveIcon(props.name))

const boxSize = computed(() => {
  const { size } = props
  if (size === undefined) return '1em'
  if (typeof size === 'number') return `${size}px`
  return keywordSizes[size] ?? size
})

const classes = computed(() => ['wx-icon', { 'wx-icon--spin': props.spin }])
</script>

<template>
  <svg
    v-if="content"
    :class="classes"
    :style="{ '--wx-icon-size': boxSize }"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    :stroke-width="strokeWidth"
    stroke-linecap="round"
    stroke-linejoin="round"
    :role="label ? 'img' : undefined"
    :aria-label="label"
    :aria-hidden="label ? undefined : 'true'"
    focusable="false"
    v-html="content"
  />
</template>

<style scoped>
.wx-icon {
  display: inline-block;
  flex: 0 0 auto;
  width: var(--wx-icon-size, 1em);
  height: var(--wx-icon-size, 1em);
  /* Sits on the text baseline rather than hanging below it. */
  vertical-align: -0.125em;
  overflow: visible;
}

/*
 * A turn a second. The duration tokens size transitions, not loops: even the slow one
 * is 320ms, which spins an icon three times a second and reads as a blur.
 */
.wx-icon--spin {
  animation: wx-icon-spin var(--wx-icon-spin-duration, 1s) linear infinite;
}

@keyframes wx-icon-spin {
  to {
    transform: rotate(360deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  .wx-icon--spin {
    animation-duration: 2s;
  }
}
</style>
