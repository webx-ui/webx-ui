<script setup lang="ts">
import { computed } from 'vue'
import type { ProgressProps } from './types'

defineOptions({ name: 'WxProgress' })

const props = withDefaults(defineProps<ProgressProps>(), {
  value: 0,
  max: 100,
  type: 'line',
  status: 'default',
  size: 'md',
  thickness: undefined,
  showValue: false,
  formatter: undefined,
  indeterminate: false,
  ariaLabel: undefined,
})

defineSlots<{
  /** Replaces the figure — a count, a size, a time remaining. */
  default?: (props: { value: number; max: number; percent: number }) => unknown
}>()

const max = computed(() => (props.max > 0 ? props.max : 100))

const value = computed(() => Math.min(Math.max(props.value, 0), max.value))

const percent = computed(() => Math.round((value.value / max.value) * 100))

const text = computed(() =>
  props.formatter ? props.formatter(value.value, max.value) : `${percent.value}%`,
)

/* The ring is drawn as one stroked circle, dashed to the share that is done. */
const RADIUS = 44

const circumference = computed(() => 2 * Math.PI * RADIUS)

const thickness = computed(() => {
  if (props.thickness !== undefined) return props.thickness
  if (props.type === 'circle') return { sm: 8, md: 10, lg: 12 }[props.size]
  return { sm: 4, md: 6, lg: 10 }[props.size]
})

const classes = computed(() => [
  'wx-progress',
  `wx-progress--${props.type}`,
  `wx-progress--${props.status}`,
  `wx-progress--${props.size}`,
  { 'wx-progress--indeterminate': props.indeterminate },
])

/*
 * An indeterminate bar reports no number: `aria-valuenow` left off is exactly how a
 * progressbar says "busy, and I cannot tell you how far".
 */
const aria = computed(() =>
  props.indeterminate
    ? { 'aria-valuemin': 0, 'aria-valuemax': max.value }
    : { 'aria-valuemin': 0, 'aria-valuemax': max.value, 'aria-valuenow': value.value },
)
</script>

<template>
  <div :class="classes" :style="{ '--wx-progress-thickness': `${thickness}px` }">
    <div
      class="wx-progress__track"
      role="progressbar"
      :aria-label="ariaLabel"
      :aria-valuetext="indeterminate ? undefined : text"
      v-bind="aria"
    >
      <template v-if="type === 'line'">
        <div class="wx-progress__fill" :style="{ width: `${percent}%` }" />
      </template>

      <svg v-else class="wx-progress__ring" viewBox="0 0 100 100" aria-hidden="true">
        <circle class="wx-progress__ring-track" cx="50" cy="50" :r="RADIUS" />
        <circle
          class="wx-progress__ring-fill"
          cx="50"
          cy="50"
          :r="RADIUS"
          :stroke-dasharray="circumference"
          :stroke-dashoffset="
            indeterminate ? circumference * 0.75 : circumference * (1 - percent / 100)
          "
        />
      </svg>
    </div>

    <div v-if="showValue || $slots.default" class="wx-progress__value">
      <slot :value="value" :max="max" :percent="percent">{{ text }}</slot>
    </div>
  </div>
</template>

<style scoped>
.wx-progress {
  --wx-progress-accent: var(--wx-color-primary);

  display: flex;
  align-items: center;
  box-sizing: border-box;
  gap: var(--wx-space-10);
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
}

.wx-progress--success {
  --wx-progress-accent: var(--wx-color-success);
}

.wx-progress--warning {
  --wx-progress-accent: var(--wx-color-warning);
}

.wx-progress--danger {
  --wx-progress-accent: var(--wx-color-danger);
}

.wx-progress__value {
  flex: 0 0 auto;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  font-variant-numeric: tabular-nums;
  line-height: 1;
}

/* --- line --- */

.wx-progress--line .wx-progress__track {
  position: relative;
  flex: 1 1 auto;
  height: var(--wx-progress-thickness, 6px);
  overflow: hidden;
  background: var(--wx-bg-fill);
  border-radius: var(--wx-radius-full);
}

.wx-progress__fill {
  height: 100%;
  background: var(--wx-progress-accent);
  border-radius: inherit;
  transition: width var(--wx-duration-normal) var(--wx-easing-standard);
}

/*
 * Indeterminate: a short bar travelling the length of the track. It is the same
 * element, so nothing switches shape when a real number finally arrives.
 */
.wx-progress--line.wx-progress--indeterminate .wx-progress__fill {
  width: 35% !important;
  animation: wx-progress-travel 1.3s ease-in-out infinite;
  transition: none;
}

@keyframes wx-progress-travel {
  from {
    transform: translateX(-100%);
  }

  to {
    transform: translateX(286%);
  }
}

/* --- circle --- */

.wx-progress--circle {
  display: inline-grid;
  place-items: center;
}

.wx-progress--circle .wx-progress__track,
.wx-progress--circle .wx-progress__value {
  grid-area: 1 / 1;
}

.wx-progress--circle .wx-progress__track {
  width: var(--wx-progress-ring, 96px);
  height: var(--wx-progress-ring, 96px);
}

.wx-progress--circle.wx-progress--sm {
  --wx-progress-ring: 56px;
}

.wx-progress--circle.wx-progress--md {
  --wx-progress-ring: 96px;
}

.wx-progress--circle.wx-progress--lg {
  --wx-progress-ring: 140px;
}

.wx-progress__ring {
  width: 100%;
  height: 100%;
  /* Twelve o'clock, clockwise — which is where a reader expects it to start. */
  transform: rotate(-90deg);
}

.wx-progress__ring-track,
.wx-progress__ring-fill {
  fill: none;
  stroke-width: var(--wx-progress-thickness, 10px);
}

.wx-progress__ring-track {
  stroke: var(--wx-bg-fill);
}

.wx-progress__ring-fill {
  stroke: var(--wx-progress-accent);
  stroke-linecap: round;
  transition: stroke-dashoffset var(--wx-duration-normal) var(--wx-easing-standard);
}

.wx-progress--circle.wx-progress--indeterminate .wx-progress__ring {
  animation: wx-progress-spin 1.1s linear infinite;
}

.wx-progress--circle.wx-progress--indeterminate .wx-progress__ring-fill {
  transition: none;
}

@keyframes wx-progress-spin {
  from {
    transform: rotate(-90deg);
  }

  to {
    transform: rotate(270deg);
  }
}

.wx-progress--circle .wx-progress__value {
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-semibold);
}

@media (prefers-reduced-motion: reduce) {
  .wx-progress__fill,
  .wx-progress__ring,
  .wx-progress__ring-fill {
    animation: none;
    transition: none;
  }
}
</style>
