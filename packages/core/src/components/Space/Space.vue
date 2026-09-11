<script setup lang="ts">
import { computed } from 'vue'
import type { SpaceProps } from './types'

defineOptions({ name: 'WxSpace' })

const props = withDefaults(defineProps<SpaceProps>(), {
  direction: 'horizontal',
  size: 'md',
  align: undefined,
  justify: undefined,
  wrap: true,
  fill: false,
  inline: false,
  as: 'div',
})

const sizes: Record<string, string> = {
  xs: 'var(--wx-space-4)',
  sm: 'var(--wx-space-8)',
  md: 'var(--wx-space-12)',
  lg: 'var(--wx-space-16)',
  xl: 'var(--wx-space-24)',
}

const gap = computed(() => {
  const { size } = props
  if (typeof size === 'number') return `${size}px`
  return sizes[size] ?? size
})

const classes = computed(() => [
  'wx-space',
  `wx-space--${props.direction}`,
  {
    'wx-space--inline': props.inline,
    'wx-space--wrap': props.wrap && props.direction === 'horizontal',
    'wx-space--fill': props.fill,
    [`wx-space--align-${props.align}`]: Boolean(props.align),
    [`wx-space--justify-${props.justify}`]: Boolean(props.justify),
  },
])
</script>

<template>
  <component :is="as" :class="classes" :style="{ '--wx-space-gap': gap }">
    <slot />
  </component>
</template>

<style scoped>
.wx-space {
  display: flex;
  gap: var(--wx-space-gap, var(--wx-space-12));
  /* Nothing may spill sideways out of a row that is told not to wrap. */
  min-width: 0;
}

.wx-space--inline {
  display: inline-flex;
  vertical-align: middle;
}

.wx-space--horizontal {
  flex-direction: row;
  align-items: center;
}

.wx-space--vertical {
  flex-direction: column;
  align-items: stretch;
}

.wx-space--wrap {
  flex-wrap: wrap;
}

/* alignment across the line */
.wx-space--align-start {
  align-items: flex-start;
}

.wx-space--align-center {
  align-items: center;
}

.wx-space--align-end {
  align-items: flex-end;
}

.wx-space--align-baseline {
  align-items: baseline;
}

.wx-space--align-stretch {
  align-items: stretch;
}

/* distribution along it */
.wx-space--justify-start {
  justify-content: flex-start;
}

.wx-space--justify-center {
  justify-content: center;
}

.wx-space--justify-end {
  justify-content: flex-end;
}

.wx-space--justify-between {
  justify-content: space-between;
}

.wx-space--justify-around {
  justify-content: space-around;
}

.wx-space--justify-evenly {
  justify-content: space-evenly;
}

/*
 * `fill` divides the line evenly. `min-width: 0` on the children matters more than
 * it looks: without it a long label makes its child refuse to shrink, and the row
 * overflows instead of sharing the space.
 */
.wx-space--fill > :deep(*) {
  flex: 1 1 0;
  min-width: 0;
}
</style>
