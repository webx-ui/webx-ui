<script setup lang="ts">
import { computed } from 'vue'
import type { HeaderProps } from './types'

defineOptions({ name: 'WxHeader' })

const props = withDefaults(defineProps<HeaderProps>(), {
  height: undefined,
  bordered: true,
  sticky: false,
  padding: 'md',
})

const height = computed(() => {
  if (props.height === undefined) return undefined
  return typeof props.height === 'number' ? `${props.height}px` : props.height
})

const classes = computed(() => [
  'wx-header',
  `wx-header--padding-${props.padding}`,
  {
    'wx-header--bordered': props.bordered,
    'wx-header--sticky': props.sticky,
  },
])
</script>

<template>
  <header :class="classes" :style="height ? { '--wx-header-height': height } : undefined">
    <slot />
  </header>
</template>

<style scoped>
.wx-header {
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
  box-sizing: border-box;
  flex: 0 0 auto;
  height: var(--wx-header-height, 56px);
  padding-inline: var(--wx-header-padding, var(--wx-space-16));
  background: var(--wx-bg-surface);
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
}

.wx-header--padding-none {
  --wx-header-padding: 0px;
}

.wx-header--padding-sm {
  --wx-header-padding: var(--wx-space-12);
}

.wx-header--padding-md {
  --wx-header-padding: var(--wx-space-16);
}

.wx-header--padding-lg {
  --wx-header-padding: var(--wx-space-24);
}

.wx-header--bordered {
  border-bottom: 1px solid var(--wx-border-default);
}

.wx-header--sticky {
  position: sticky;
  top: 0;
  z-index: var(--wx-z-index-sticky);
}

/* On a phone the bar is mostly the title and one button; the padding gives way. */
@media (max-width: 640px) {
  .wx-header--padding-lg,
  .wx-header--padding-md {
    --wx-header-padding: var(--wx-space-12);
  }

  .wx-header--padding-sm {
    --wx-header-padding: var(--wx-space-8);
  }
}
</style>
