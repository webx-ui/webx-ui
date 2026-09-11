<script setup lang="ts">
import { computed } from 'vue'
import WxIcon from '../Icon/Icon.vue'
import type { AlertEmits, AlertProps } from './types'
import type { IconName } from '../Icon/types'

defineOptions({ name: 'WxAlert' })

const props = withDefaults(defineProps<AlertProps>(), {
  type: 'info',
  variant: 'soft',
  title: undefined,
  description: undefined,
  icon: undefined,
  closable: false,
  closeLabel: 'Dismiss',
  live: false,
})

const emit = defineEmits<AlertEmits>()

/**
 * Kept as a model so `<wx-alert closable />` dismisses itself, while a page that
 * needs the alert back — a validation summary, say — binds `v-model:visible`.
 */
const visible = defineModel<boolean>('visible', { default: true })

const typeIcons: Record<string, IconName> = {
  info: 'info',
  success: 'check-circle',
  warning: 'warning',
  danger: 'close-circle',
}

const iconName = computed(() => {
  if (props.icon === false) return undefined
  return props.icon ?? typeIcons[props.type]
})

const classes = computed(() => [
  'wx-alert',
  `wx-alert--${props.type}`,
  `wx-alert--${props.variant}`,
  { 'wx-alert--titled': Boolean(props.title) },
])

function onClose(event: MouseEvent) {
  visible.value = false
  emit('close', event)
}
</script>

<template>
  <div v-if="visible" :class="classes" :role="live ? 'alert' : undefined">
    <span v-if="iconName || $slots.icon" class="wx-alert__icon">
      <slot name="icon">
        <wx-icon v-if="iconName" :name="iconName" size="1.15em" />
      </slot>
    </span>

    <div class="wx-alert__content">
      <p v-if="title || $slots.title" class="wx-alert__title">
        <slot name="title">{{ title }}</slot>
      </p>
      <div v-if="description || $slots.default" class="wx-alert__body">
        <slot>{{ description }}</slot>
      </div>
    </div>

    <div v-if="$slots.actions" class="wx-alert__actions">
      <slot name="actions" />
    </div>

    <button
      v-if="closable"
      class="wx-alert__close"
      type="button"
      :aria-label="closeLabel"
      @click="onClose"
    >
      <wx-icon name="close" />
    </button>
  </div>
</template>

<style scoped>
.wx-alert {
  display: flex;
  align-items: flex-start;
  gap: var(--wx-space-10);
  box-sizing: border-box;
  padding: var(--wx-space-12) var(--wx-space-14);
  border: 1px solid transparent;
  border-radius: var(--wx-radius-sm);
  font-family: var(--wx-font-family-sans);
  font-size: var(--wx-font-size-sm);
  line-height: var(--wx-font-line-height-normal);
}

/* Each type names its colours once; the variants below stay type-agnostic. */
.wx-alert--info {
  --wx-alert-accent: var(--wx-color-info-active);
  --wx-alert-soft: var(--wx-color-info-soft);
  --wx-alert-solid: var(--wx-color-info);
  --wx-alert-contrast: var(--wx-color-info-contrast);
}

.wx-alert--success {
  --wx-alert-accent: var(--wx-color-success-active);
  --wx-alert-soft: var(--wx-color-success-soft);
  --wx-alert-solid: var(--wx-color-success);
  --wx-alert-contrast: var(--wx-color-success-contrast);
}

.wx-alert--warning {
  --wx-alert-accent: var(--wx-color-warning-active);
  --wx-alert-soft: var(--wx-color-warning-soft);
  --wx-alert-solid: var(--wx-color-warning);
  --wx-alert-contrast: var(--wx-color-warning-contrast);
}

.wx-alert--danger {
  --wx-alert-accent: var(--wx-color-danger);
  --wx-alert-soft: var(--wx-color-danger-soft);
  --wx-alert-solid: var(--wx-color-danger);
  --wx-alert-contrast: var(--wx-color-danger-contrast);
}

/* variants */
.wx-alert--soft {
  background: var(--wx-alert-soft);
  color: var(--wx-text-default);
}

.wx-alert--outline {
  background: var(--wx-bg-surface);
  border-color: var(--wx-alert-accent);
  color: var(--wx-text-default);
}

.wx-alert--solid {
  background: var(--wx-alert-solid);
  color: var(--wx-alert-contrast);
}

/* The icon carries the colour in the quiet variants; in `solid` everything already has it. */
.wx-alert--soft .wx-alert__icon,
.wx-alert--outline .wx-alert__icon {
  color: var(--wx-alert-accent);
}

.wx-alert__icon {
  display: inline-flex;
  flex: 0 0 auto;
  /* Lines the icon up with the first line of text rather than the box. */
  padding-top: 0.1em;
}

.wx-alert__content {
  flex: 1 1 auto;
  min-width: 0;
}

.wx-alert__title {
  margin: 0;
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-semibold);
  line-height: var(--wx-font-line-height-tight);
}

.wx-alert--titled .wx-alert__body {
  margin-top: var(--wx-space-4);
}

.wx-alert--soft .wx-alert__body,
.wx-alert--outline .wx-alert__body {
  color: var(--wx-text-muted);
}

.wx-alert__body :deep(p) {
  margin: 0;
}

.wx-alert__body :deep(p + p) {
  margin-top: var(--wx-space-8);
}

.wx-alert__actions {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  flex: 0 0 auto;
}

.wx-alert__close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  padding: 0;
  background: transparent;
  border: none;
  border-radius: var(--wx-radius-xs);
  color: inherit;
  font-size: var(--wx-font-size-lg);
  line-height: 1;
  opacity: 0.65;
  cursor: pointer;
}

.wx-alert__close:hover {
  opacity: 1;
}

.wx-alert__close:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

/* A stack of alerts on a narrow screen reads better with the actions on their own line. */
@media (max-width: 480px) {
  .wx-alert {
    flex-wrap: wrap;
  }

  .wx-alert__actions {
    width: 100%;
    padding-left: calc(1.15em + var(--wx-space-10));
  }
}
</style>
