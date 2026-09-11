<script setup lang="ts">
import { computed } from 'vue'
import type { RowProps } from './types'

defineOptions({ name: 'WxRow' })

const props = withDefaults(defineProps<RowProps>(), {
  gutter: 16,
  gutterY: undefined,
  justify: undefined,
  align: undefined,
  wrap: true,
  as: 'div',
})

function toLength(value: number | string) {
  return typeof value === 'number' ? `${value}px` : value
}

/**
 * The gutter is published as a variable rather than passed down through a provide:
 * a custom property inherits through the DOM, so `WxCol` picks it up wherever it
 * sits — even wrapped in a `<template v-for>` or a component of your own.
 */
const style = computed(() => ({
  '--wx-row-gutter': toLength(props.gutter),
  '--wx-row-gutter-y': toLength(props.gutterY ?? props.gutter),
}))

const classes = computed(() => [
  'wx-row',
  {
    'wx-row--nowrap': !props.wrap,
    [`wx-row--justify-${props.justify}`]: Boolean(props.justify),
    [`wx-row--align-${props.align}`]: Boolean(props.align),
  },
])
</script>

<template>
  <component :is="as" :class="classes" :style="style">
    <slot />
  </component>
</template>

<style scoped>
.wx-row {
  display: flex;
  flex-wrap: wrap;
  box-sizing: border-box;
  /*
   * The gutter is padding on the columns, pulled back by this negative margin, so a
   * column's width stays an honest percentage of the row. A `column-gap` would be
   * subtracted from the track and leave `span="12"` twice over a line too wide.
   */
  margin-inline: calc(var(--wx-row-gutter, 0px) / -2);
  row-gap: var(--wx-row-gutter-y, 0px);
}

.wx-row--nowrap {
  flex-wrap: nowrap;
}

.wx-row--justify-start {
  justify-content: flex-start;
}

.wx-row--justify-center {
  justify-content: center;
}

.wx-row--justify-end {
  justify-content: flex-end;
}

.wx-row--justify-between {
  justify-content: space-between;
}

.wx-row--justify-around {
  justify-content: space-around;
}

.wx-row--justify-evenly {
  justify-content: space-evenly;
}

.wx-row--align-start {
  align-items: flex-start;
}

.wx-row--align-center {
  align-items: center;
}

.wx-row--align-end {
  align-items: flex-end;
}

.wx-row--align-stretch {
  align-items: stretch;
}

.wx-row--align-baseline {
  align-items: baseline;
}
</style>
