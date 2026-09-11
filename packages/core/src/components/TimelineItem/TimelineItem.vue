<script setup lang="ts">
import { computed, useSlots } from 'vue'
import WxIcon from '../Icon/Icon.vue'
import { useTimeline } from '../../composables/useTimeline'
import type { TimelineItemProps } from './types'

defineOptions({ name: 'WxTimelineItem' })

const props = withDefaults(defineProps<TimelineItemProps>(), {
  timestamp: undefined,
  datetime: undefined,
  timestampPlacement: 'top',
  title: undefined,
  type: 'default',
  hollow: false,
  icon: undefined,
  hideLine: false,
})

const timeline = useTimeline()
const slots = useSlots()

const hasMark = computed(() => Boolean(props.icon || slots.dot))

const classes = computed(() => [
  'wx-timeline-item',
  `wx-timeline-item--${timeline?.size.value ?? 'md'}`,
  {
    'wx-timeline-item--no-line': props.hideLine,
    // A mark needs a bigger dot to sit in; the line and the indent follow it.
    'wx-timeline-item--mark': hasMark.value,
  },
])

const dotClasses = computed(() => [
  'wx-timeline-item__dot',
  `wx-timeline-item__dot--${props.type}`,
  { 'wx-timeline-item__dot--hollow': props.hollow },
])
</script>

<template>
  <li :class="classes">
    <span class="wx-timeline-item__line" aria-hidden="true" />

    <span :class="dotClasses" aria-hidden="true">
      <slot name="dot">
        <wx-icon v-if="icon" :name="icon" />
      </slot>
    </span>

    <div class="wx-timeline-item__content">
      <time
        v-if="timestamp && timestampPlacement === 'top'"
        class="wx-timeline-item__timestamp"
        :datetime="datetime"
      >
        {{ timestamp }}
      </time>

      <p v-if="title || $slots.title" class="wx-timeline-item__title">
        <slot name="title">{{ title }}</slot>
      </p>

      <div v-if="$slots.default" class="wx-timeline-item__body">
        <slot />
      </div>

      <time
        v-if="timestamp && timestampPlacement === 'bottom'"
        class="wx-timeline-item__timestamp wx-timeline-item__timestamp--bottom"
        :datetime="datetime"
      >
        {{ timestamp }}
      </time>
    </div>
  </li>
</template>

<style scoped>
/*
 * Everything hangs off one vertical axis: the dot is centred on it, the line runs
 * down it, and the text starts a fixed gap to its right. Changing the dot size —
 * a small marker, or a 24px circle holding an icon — never moves the text.
 */
.wx-timeline-item {
  --wx-timeline-axis: 12px;
  --wx-timeline-mark: var(--wx-timeline-dot, 12px);
  --wx-timeline-mark-top: 3px;

  position: relative;
  padding-left: calc(var(--wx-timeline-axis) + var(--wx-space-16));
  padding-bottom: var(--wx-timeline-gap, var(--wx-space-18));
}

.wx-timeline-item--sm {
  --wx-timeline-axis: 10px;
}

.wx-timeline-item--mark {
  --wx-timeline-mark: 24px;
  --wx-timeline-mark-top: 0px;
}

/* The last entry ends at its content: no trailing stub of line under it. */
.wx-timeline-item:last-child {
  padding-bottom: 0;
}

.wx-timeline-item__line {
  position: absolute;
  top: calc(var(--wx-timeline-mark-top) + var(--wx-timeline-mark) + 4px);
  bottom: 0;
  left: calc(var(--wx-timeline-axis) - 0.5px);
  width: 1px;
  background: var(--wx-border-default);
}

.wx-timeline-item:last-child .wx-timeline-item__line,
.wx-timeline-item--no-line .wx-timeline-item__line {
  display: none;
}

.wx-timeline-item__dot {
  position: absolute;
  top: var(--wx-timeline-mark-top);
  left: calc(var(--wx-timeline-axis) - var(--wx-timeline-mark) / 2);
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  width: var(--wx-timeline-mark);
  height: var(--wx-timeline-mark);
  background: var(--wx-timeline-dot-color, var(--wx-border-strong));
  border-radius: var(--wx-radius-full);
  color: var(--wx-timeline-dot-contrast, var(--wx-text-inverse));
  font-size: 14px;
}

.wx-timeline-item__dot--hollow {
  background: var(--wx-bg-surface);
  border: 2px solid var(--wx-timeline-dot-color, var(--wx-border-strong));
  color: var(--wx-timeline-dot-color, var(--wx-border-strong));
}

.wx-timeline-item__dot--default {
  --wx-timeline-dot-color: var(--wx-border-strong);
  --wx-timeline-dot-contrast: var(--wx-text-inverse);
}

.wx-timeline-item__dot--primary {
  --wx-timeline-dot-color: var(--wx-color-primary);
  --wx-timeline-dot-contrast: var(--wx-color-primary-contrast);
}

.wx-timeline-item__dot--success {
  --wx-timeline-dot-color: var(--wx-color-success);
  --wx-timeline-dot-contrast: var(--wx-color-success-contrast);
}

.wx-timeline-item__dot--warning {
  --wx-timeline-dot-color: var(--wx-color-warning);
  --wx-timeline-dot-contrast: var(--wx-color-warning-contrast);
}

.wx-timeline-item__dot--danger {
  --wx-timeline-dot-color: var(--wx-color-danger);
  --wx-timeline-dot-contrast: var(--wx-color-danger-contrast);
}

.wx-timeline-item__dot--info {
  --wx-timeline-dot-color: var(--wx-color-info);
  --wx-timeline-dot-contrast: var(--wx-color-info-contrast);
}

.wx-timeline-item__content {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.wx-timeline-item__timestamp {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  line-height: var(--wx-font-line-height-normal);
}

.wx-timeline-item__timestamp--bottom {
  margin-top: var(--wx-space-4);
}

.wx-timeline-item__title {
  margin: 0;
  color: var(--wx-text-strong);
  font-size: var(--wx-font-size-md);
  font-weight: var(--wx-font-weight-semibold);
  line-height: var(--wx-font-line-height-tight);
}

.wx-timeline-item--sm .wx-timeline-item__title {
  font-size: var(--wx-font-size-sm);
}

.wx-timeline-item__body {
  color: var(--wx-text-default);
  font-size: var(--wx-font-size-sm);
  line-height: var(--wx-font-line-height-normal);
}
</style>
