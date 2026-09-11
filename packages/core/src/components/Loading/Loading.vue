<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import type { LoadingProps } from './types'

defineOptions({ name: 'WxLoading' })

/*
 * A veil over what is already there, rather than a spinner in place of it. The rows
 * of a table being refreshed are still the rows you were reading, and taking them
 * away to say so loses your place — twice, since they come back.
 */
const props = withDefaults(defineProps<LoadingProps>(), {
  loading: false,
  text: undefined,
  size: 'md',
  fullscreen: false,
  delay: 0,
  blur: false,
  ariaLabel: 'Loading',
})

defineSlots<{
  /** What is being covered. Left out for a spinner on its own. */
  default?: () => unknown
  /** Replaces the spinner. */
  spinner?: () => unknown
}>()

const showing = ref(props.delay === 0 && props.loading)

let timer: ReturnType<typeof setTimeout> | undefined

watch(
  [() => props.loading, () => props.delay],
  ([loading, delay]) => {
    clearTimeout(timer)

    if (!loading) {
      showing.value = false
      return
    }

    if (delay === 0) {
      showing.value = true
      return
    }

    timer = setTimeout(() => {
      showing.value = true
    }, delay)
  },
  { immediate: true },
)

onBeforeUnmount(() => clearTimeout(timer))

const classes = computed(() => [
  'wx-loading',
  `wx-loading--${props.size}`,
  {
    'wx-loading--fullscreen': props.fullscreen,
    'wx-loading--blur': props.blur,
    'is-loading': showing.value,
  },
])
</script>

<template>
  <div :class="classes">
    <div class="wx-loading__content" :inert="showing || undefined">
      <slot />
    </div>

    <div v-if="showing" class="wx-loading__veil" role="status" :aria-label="ariaLabel">
      <slot name="spinner">
        <span class="wx-loading__spinner" aria-hidden="true" />
      </slot>
      <span v-if="text" class="wx-loading__text">{{ text }}</span>
    </div>
  </div>
</template>

<style scoped>
.wx-loading {
  position: relative;
  box-sizing: border-box;
  font-family: var(--wx-font-family-sans);
}

.wx-loading__content {
  /*
   * `inert` while the veil is up, so nothing underneath can be tabbed into or
   * clicked. A veil that only looks like it blocks is worse than none: it invites
   * the second click that sends the request twice.
   */
  transition: filter var(--wx-duration-normal) var(--wx-easing-standard);
}

.wx-loading--blur.is-loading .wx-loading__content {
  filter: blur(2px);
}

.wx-loading__veil {
  position: absolute;
  inset: 0;
  z-index: var(--wx-z-index-overlay);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--wx-space-10);
  /*
   * The surface at three quarters, not the dialog scrim: this covers a panel while
   * it refreshes, and a scrim that dark says the page has been taken away.
   */
  background: color-mix(in srgb, var(--wx-bg-surface) 76%, transparent);
  color: var(--wx-text-muted);
}

.wx-loading--fullscreen .wx-loading__veil {
  position: fixed;
}

.wx-loading__text {
  font-size: var(--wx-font-size-sm);
}

.wx-loading__spinner {
  display: block;
  width: var(--wx-loading-size);
  height: var(--wx-loading-size);
  border: var(--wx-loading-stroke) solid var(--wx-border-default);
  /* One quarter in the accent colour is what makes the rotation visible. */
  border-top-color: var(--wx-color-primary);
  border-radius: var(--wx-radius-full);
  animation: wx-loading-spin 0.7s linear infinite;
}

.wx-loading--sm {
  --wx-loading-size: 18px;
  --wx-loading-stroke: 2px;
}

.wx-loading--md {
  --wx-loading-size: 28px;
  --wx-loading-stroke: 3px;
}

.wx-loading--lg {
  --wx-loading-size: 40px;
  --wx-loading-stroke: 4px;
}

@keyframes wx-loading-spin {
  to {
    transform: rotate(360deg);
  }
}

/*
 * Reduced motion stops the spin but keeps the ring: it is the only thing on screen
 * saying that anything is happening at all.
 */
@media (prefers-reduced-motion: reduce) {
  .wx-loading__spinner {
    animation-duration: 2.4s;
  }

  .wx-loading__content {
    transition: none;
  }
}
</style>
