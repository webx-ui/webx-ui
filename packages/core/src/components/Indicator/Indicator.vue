<script setup lang="ts">
import { computed, useSlots } from 'vue'
import type { IndicatorProps } from './types'

defineOptions({ name: 'WxIndicator' })

const props = withDefaults(defineProps<IndicatorProps>(), {
  value: undefined,
  max: 99,
  dot: false,
  showZero: false,
  placement: 'top-right',
  offset: undefined,
  type: 'danger',
  hidden: false,
  label: undefined,
})

const slots = useSlots()

/** Without a wrapped element the mark is the whole component — a count in a menu row. */
const isAttached = computed(() => Boolean(slots.default))

const text = computed(() => {
  if (props.dot) return ''
  if (typeof props.value === 'number') {
    return props.value > props.max ? `${props.max}+` : String(props.value)
  }
  return props.value ?? ''
})

const visible = computed(() => {
  if (props.hidden) return false
  if (props.dot) return true
  if (props.value === 0) return props.showZero
  return text.value !== ''
})

const markClasses = computed(() => [
  'wx-indicator__mark',
  `wx-indicator__mark--${props.type}`,
  {
    'wx-indicator__mark--dot': props.dot,
    'wx-indicator__mark--attached': isAttached.value,
    [`wx-indicator__mark--${props.placement}`]: isAttached.value,
  },
])

const markStyle = computed(() => {
  if (!props.offset) return undefined
  const [x, y] = props.offset
  return { '--wx-indicator-offset-x': `${x}px`, '--wx-indicator-offset-y': `${y}px` }
})
</script>

<template>
  <span class="wx-indicator" :class="{ 'wx-indicator--attached': isAttached }">
    <slot />
    <span v-if="visible" :class="markClasses" :style="markStyle" :aria-label="label">
      <slot name="mark">{{ text }}</slot>
    </span>
  </span>
</template>

<style scoped>
.wx-indicator {
  display: inline-flex;
  vertical-align: middle;
}

.wx-indicator--attached {
  position: relative;
}

.wx-indicator__mark {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: var(--wx-radius-full);
  color: var(--wx-indicator-fg);
  font-family: var(--wx-font-family-sans);
  font-size: var(--wx-font-size-xs);
  font-weight: var(--wx-font-weight-semibold);
  line-height: 1;
  white-space: nowrap;
  background: var(--wx-indicator-bg);
}

/*
 * The ring is the surface behind the mark, not a fixed white — a counter pinned to a
 * dark sidebar icon needs the sidebar's colour, which callers set on the wrapper.
 */
.wx-indicator__mark--attached {
  position: absolute;
  z-index: 1;
  border: 2px solid var(--wx-indicator-ring, var(--wx-bg-surface));
  height: 20px;
  min-width: 20px;
  pointer-events: none;
}

.wx-indicator__mark--top-right {
  top: 0;
  right: 0;
  transform: translate(
    calc(50% + var(--wx-indicator-offset-x, 0px)),
    calc(-50% + var(--wx-indicator-offset-y, 0px))
  );
}

.wx-indicator__mark--top-left {
  top: 0;
  left: 0;
  transform: translate(
    calc(-50% + var(--wx-indicator-offset-x, 0px)),
    calc(-50% + var(--wx-indicator-offset-y, 0px))
  );
}

.wx-indicator__mark--bottom-right {
  bottom: 0;
  right: 0;
  transform: translate(
    calc(50% + var(--wx-indicator-offset-x, 0px)),
    calc(50% + var(--wx-indicator-offset-y, 0px))
  );
}

.wx-indicator__mark--bottom-left {
  bottom: 0;
  left: 0;
  transform: translate(
    calc(-50% + var(--wx-indicator-offset-x, 0px)),
    calc(50% + var(--wx-indicator-offset-y, 0px))
  );
}

.wx-indicator__mark--dot {
  min-width: 10px;
  width: 10px;
  height: 10px;
  padding: 0;
}

.wx-indicator__mark--dot.wx-indicator__mark--attached {
  min-width: 12px;
  width: 12px;
  height: 12px;
}

/* colours */
.wx-indicator__mark--danger {
  --wx-indicator-bg: var(--wx-color-danger);
  --wx-indicator-fg: var(--wx-color-danger-contrast);
}

.wx-indicator__mark--primary {
  --wx-indicator-bg: var(--wx-color-primary);
  --wx-indicator-fg: var(--wx-color-primary-contrast);
}

.wx-indicator__mark--success {
  --wx-indicator-bg: var(--wx-color-success);
  --wx-indicator-fg: var(--wx-color-success-contrast);
}

.wx-indicator__mark--warning {
  --wx-indicator-bg: var(--wx-color-warning);
  --wx-indicator-fg: var(--wx-color-warning-contrast);
}

.wx-indicator__mark--info {
  --wx-indicator-bg: var(--wx-color-info);
  --wx-indicator-fg: var(--wx-color-info-contrast);
}

.wx-indicator__mark--neutral {
  --wx-indicator-bg: var(--wx-bg-fill-hover);
  --wx-indicator-fg: var(--wx-text-default);
}
</style>
