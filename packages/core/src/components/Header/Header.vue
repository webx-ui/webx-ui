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
    <!--
      The far end of the bar, where the user menu and the notifications live. It is a
      group of its own rather than a `margin-inline-start: auto` every admin panel
      would otherwise write for itself — and one everybody would spell differently.
    -->
    <div v-if="$slots.end" class="wx-header__end">
      <slot name="end" />
    </div>
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

.wx-header__end {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  /* Everything before it keeps its place; this group takes the far end. */
  margin-inline-start: auto;
  min-width: 0;
}

/*
 * A bar is chrome, not content: it is padded to the gutter the sidebar's icons stand
 * in — 10px, which puts the centre of a 36px control in the header exactly above the
 * centre of the rail below it. A header carrying nothing but a title can take `lg`.
 */
.wx-header--padding-none {
  --wx-header-padding: 0px;
}

.wx-header--padding-sm {
  --wx-header-padding: var(--wx-space-6);
}

.wx-header--padding-md {
  --wx-header-padding: var(--wx-space-10);
}

.wx-header--padding-lg {
  --wx-header-padding: var(--wx-space-16);
}

.wx-header--bordered {
  border-bottom: 1px solid var(--wx-border-default);
}

.wx-header--sticky {
  position: sticky;
  top: 0;
  z-index: var(--wx-z-index-sticky);
}

/* On a phone the bar is mostly the title and one button; the roomy option gives way. */
@media (max-width: 640px) {
  .wx-header--padding-lg {
    --wx-header-padding: var(--wx-space-10);
  }
}
</style>
