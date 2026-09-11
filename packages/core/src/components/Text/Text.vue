<script setup lang="ts">
import { computed } from 'vue'
import type { TextProps } from './types'

defineOptions({ name: 'WxText' })

const props = withDefaults(defineProps<TextProps>(), {
  as: 'span',
  size: 'md',
  weight: 'regular',
  tone: 'default',
  align: undefined,
  truncate: false,
  italic: false,
  mono: false,
})

const clampLines = computed(() => (typeof props.truncate === 'number' ? props.truncate : undefined))

const classes = computed(() => [
  'wx-text',
  `wx-text--${props.size}`,
  `wx-text--${props.weight}`,
  `wx-text--tone-${props.tone}`,
  {
    [`wx-text--align-${props.align}`]: Boolean(props.align),
    'wx-text--italic': props.italic,
    'wx-text--mono': props.mono,
    'wx-text--truncate': props.truncate === true,
    'wx-text--clamp': clampLines.value !== undefined,
  },
])

const style = computed(() =>
  clampLines.value === undefined ? undefined : { '--wx-text-lines': clampLines.value },
)
</script>

<template>
  <component :is="as" :class="classes" :style="style">
    <slot />
  </component>
</template>

<style scoped>
.wx-text {
  margin: 0;
  font-family: var(--wx-font-family-sans);
  line-height: var(--wx-font-line-height-normal);
  color: var(--wx-text-color, var(--wx-text-default));
}

/* sizes */
.wx-text--xs {
  font-size: var(--wx-font-size-xs);
}

.wx-text--sm {
  font-size: var(--wx-font-size-sm);
}

.wx-text--md {
  font-size: var(--wx-font-size-md);
}

.wx-text--lg {
  font-size: var(--wx-font-size-lg);
}

.wx-text--xl {
  font-size: var(--wx-font-size-xl);
}

/* weights */
.wx-text--regular {
  font-weight: var(--wx-font-weight-regular);
}

.wx-text--medium {
  font-weight: var(--wx-font-weight-medium);
}

.wx-text--semibold {
  font-weight: var(--wx-font-weight-semibold);
}

.wx-text--bold {
  font-weight: var(--wx-font-weight-bold);
}

/* tones */
.wx-text--tone-default {
  --wx-text-color: var(--wx-text-default);
}

.wx-text--tone-strong {
  --wx-text-color: var(--wx-text-strong);
}

.wx-text--tone-muted {
  --wx-text-color: var(--wx-text-muted);
}

.wx-text--tone-placeholder {
  --wx-text-color: var(--wx-text-placeholder);
}

.wx-text--tone-primary {
  --wx-text-color: var(--wx-color-primary);
}

.wx-text--tone-success {
  --wx-text-color: var(--wx-color-success-active);
}

.wx-text--tone-warning {
  --wx-text-color: var(--wx-color-warning-active);
}

.wx-text--tone-danger {
  --wx-text-color: var(--wx-color-danger);
}

.wx-text--tone-info {
  --wx-text-color: var(--wx-color-info-active);
}

.wx-text--tone-inverse {
  --wx-text-color: var(--wx-text-inverse);
}

/* alignment */
.wx-text--align-start {
  text-align: start;
}

.wx-text--align-center {
  text-align: center;
}

.wx-text--align-end {
  text-align: end;
}

.wx-text--align-justify {
  text-align: justify;
}

.wx-text--italic {
  font-style: italic;
}

.wx-text--mono {
  font-family: var(--wx-font-family-mono);
  font-variant-numeric: tabular-nums;
}

/* Truncation needs a block box: an inline span ignores overflow. */
.wx-text--truncate {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-text--clamp {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: var(--wx-text-lines, 2);
  line-clamp: var(--wx-text-lines, 2);
  overflow: hidden;
}
</style>
