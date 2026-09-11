<script setup lang="ts">
import { computed } from 'vue'
import WxIcon from '../Icon/Icon.vue'
import type { BadgeEmits, BadgeProps } from './types'

defineOptions({ name: 'WxBadge' })

const props = withDefaults(defineProps<BadgeProps>(), {
  type: 'default',
  variant: 'soft',
  size: 'md',
  round: false,
  dot: false,
  closable: false,
  closeLabel: 'Remove',
})

const emit = defineEmits<BadgeEmits>()

const classes = computed(() => [
  'wx-badge',
  `wx-badge--${props.type}`,
  `wx-badge--${props.variant}`,
  `wx-badge--${props.size}`,
  {
    'wx-badge--round': props.round,
    'wx-badge--closable': props.closable,
  },
])
</script>

<template>
  <span :class="classes">
    <span v-if="dot" class="wx-badge__dot" aria-hidden="true" />
    <span v-if="$slots.icon" class="wx-badge__icon">
      <slot name="icon" />
    </span>
    <span class="wx-badge__label">
      <slot />
    </span>
    <button
      v-if="closable"
      class="wx-badge__close"
      type="button"
      :aria-label="closeLabel"
      @click="emit('close', $event)"
    >
      <wx-icon name="close" />
    </button>
  </span>
</template>

<style scoped>
.wx-badge {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-4);
  box-sizing: border-box;
  max-width: 100%;
  border: 1px solid transparent;
  border-radius: var(--wx-radius-xs);
  font-family: inherit;
  font-weight: var(--wx-font-weight-medium);
  line-height: var(--wx-font-line-height-tight);
  white-space: nowrap;
  vertical-align: middle;
}

/* sizes */
.wx-badge--sm {
  padding: 1px var(--wx-space-6);
  font-size: var(--wx-font-size-xs);
}

.wx-badge--md {
  padding: 3px var(--wx-space-8);
  font-size: var(--wx-font-size-xs);
}

.wx-badge--lg {
  padding: 5px var(--wx-space-10);
  font-size: var(--wx-font-size-sm);
}

.wx-badge--round {
  border-radius: var(--wx-radius-full);
}

/* Each type names its colours once; the variants below stay type-agnostic. */
.wx-badge--default {
  --wx-badge-accent: var(--wx-text-muted);
  --wx-badge-soft: var(--wx-bg-fill);
  --wx-badge-solid: var(--wx-bg-inverse);
  --wx-badge-contrast: var(--wx-text-inverse);
  --wx-badge-border: var(--wx-border-default);
}

.wx-badge--primary {
  --wx-badge-accent: var(--wx-color-primary);
  --wx-badge-soft: var(--wx-color-primary-soft);
  --wx-badge-solid: var(--wx-color-primary);
  --wx-badge-contrast: var(--wx-color-primary-contrast);
  --wx-badge-border: var(--wx-color-primary);
}

.wx-badge--success {
  --wx-badge-accent: var(--wx-color-success-active);
  --wx-badge-soft: var(--wx-color-success-soft);
  --wx-badge-solid: var(--wx-color-success);
  --wx-badge-contrast: var(--wx-color-success-contrast);
  --wx-badge-border: var(--wx-color-success);
}

.wx-badge--warning {
  --wx-badge-accent: var(--wx-color-warning-active);
  --wx-badge-soft: var(--wx-color-warning-soft);
  --wx-badge-solid: var(--wx-color-warning);
  --wx-badge-contrast: var(--wx-color-warning-contrast);
  --wx-badge-border: var(--wx-color-warning);
}

.wx-badge--danger {
  --wx-badge-accent: var(--wx-color-danger);
  --wx-badge-soft: var(--wx-color-danger-soft);
  --wx-badge-solid: var(--wx-color-danger);
  --wx-badge-contrast: var(--wx-color-danger-contrast);
  --wx-badge-border: var(--wx-color-danger);
}

.wx-badge--info {
  --wx-badge-accent: var(--wx-color-info-active);
  --wx-badge-soft: var(--wx-color-info-soft);
  --wx-badge-solid: var(--wx-color-info);
  --wx-badge-contrast: var(--wx-color-info-contrast);
  --wx-badge-border: var(--wx-color-info);
}

/* variants */
.wx-badge--soft {
  background: var(--wx-badge-soft);
  color: var(--wx-badge-accent);
}

.wx-badge--solid {
  background: var(--wx-badge-solid);
  color: var(--wx-badge-contrast);
}

.wx-badge--outline {
  background: transparent;
  border-color: var(--wx-badge-border);
  color: var(--wx-badge-accent);
}

.wx-badge__dot {
  width: 6px;
  height: 6px;
  flex: 0 0 auto;
  background: currentcolor;
  border-radius: var(--wx-radius-full);
}

.wx-badge__icon {
  display: inline-flex;
  align-items: center;
}

.wx-badge__label {
  overflow: hidden;
  text-overflow: ellipsis;
}

.wx-badge__close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  /* Keeps the badge from growing taller than its text. */
  margin-right: -2px;
  padding: 0;
  background: transparent;
  border: none;
  border-radius: var(--wx-radius-full);
  color: inherit;
  font-size: 1em;
  line-height: 1;
  opacity: 0.65;
  cursor: pointer;
}

.wx-badge__close:hover {
  opacity: 1;
}

.wx-badge__close:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}
</style>
