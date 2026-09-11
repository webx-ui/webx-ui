<script setup lang="ts">
import { computed } from 'vue'
import type { HeadingLevel, HeadingProps, HeadingSize } from './types'

defineOptions({ name: 'WxHeading' })

const props = withDefaults(defineProps<HeadingProps>(), {
  level: 2,
  size: undefined,
  tone: 'strong',
  align: undefined,
  truncate: false,
})

/** What each level looks like when `size` is left alone. */
const sizeForLevel: Record<HeadingLevel, HeadingSize> = {
  1: '3xl',
  2: '2xl',
  3: 'xl',
  4: 'lg',
  5: 'md',
  6: 'sm',
}

const size = computed(() => props.size ?? sizeForLevel[props.level])

const classes = computed(() => [
  'wx-heading',
  `wx-heading--${size.value}`,
  `wx-heading--tone-${props.tone}`,
  {
    [`wx-heading--align-${props.align}`]: Boolean(props.align),
    'wx-heading--truncate': props.truncate,
  },
])
</script>

<template>
  <component :is="`h${level}`" :class="classes">
    <slot />
  </component>
</template>

<style scoped>
/*
 * No margin: spacing belongs to the layout around the heading, not to the heading
 * itself. `WxProse` puts the rhythm back for free-flowing content.
 */
.wx-heading {
  margin: 0;
  color: var(--wx-heading-color, var(--wx-text-strong));
  font-family: var(--wx-font-family-sans);
  font-weight: var(--wx-font-weight-semibold);
  line-height: var(--wx-font-line-height-tight);
  text-wrap: balance;
}

.wx-heading--sm {
  font-size: var(--wx-font-size-sm);
}

.wx-heading--md {
  font-size: var(--wx-font-size-md);
}

.wx-heading--lg {
  font-size: var(--wx-font-size-lg);
}

.wx-heading--xl {
  font-size: var(--wx-font-size-xl);
}

.wx-heading--2xl {
  font-size: var(--wx-font-size-2xl);
}

.wx-heading--3xl {
  font-size: var(--wx-font-size-3xl);
  font-weight: var(--wx-font-weight-bold);
}

.wx-heading--tone-default {
  --wx-heading-color: var(--wx-text-default);
}

.wx-heading--tone-strong {
  --wx-heading-color: var(--wx-text-strong);
}

.wx-heading--tone-muted {
  --wx-heading-color: var(--wx-text-muted);
}

.wx-heading--tone-placeholder {
  --wx-heading-color: var(--wx-text-placeholder);
}

.wx-heading--tone-primary {
  --wx-heading-color: var(--wx-color-primary);
}

.wx-heading--tone-success {
  --wx-heading-color: var(--wx-color-success-active);
}

.wx-heading--tone-warning {
  --wx-heading-color: var(--wx-color-warning-active);
}

.wx-heading--tone-danger {
  --wx-heading-color: var(--wx-color-danger);
}

.wx-heading--tone-info {
  --wx-heading-color: var(--wx-color-info-active);
}

.wx-heading--tone-inverse {
  --wx-heading-color: var(--wx-text-inverse);
}

.wx-heading--align-start {
  text-align: start;
}

.wx-heading--align-center {
  text-align: center;
}

.wx-heading--align-end {
  text-align: end;
}

.wx-heading--align-justify {
  text-align: justify;
}

.wx-heading--truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
