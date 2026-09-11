<script setup lang="ts">
import { computed } from 'vue'
import WxIcon from '../Icon/Icon.vue'
import type { IconName } from '../Icon/types'
import type { ResultProps, ResultStatus } from './types'

defineOptions({ name: 'WxResult' })

const props = withDefaults(defineProps<ResultProps>(), {
  status: 'info',
  title: undefined,
  subtitle: undefined,
  icon: undefined,
})

defineSlots<{
  icon?: () => unknown
  title?: () => unknown
  subtitle?: () => unknown
  /** The way out: back to the list, try again, contact somebody. */
  actions?: () => unknown
  /** Anything under the actions — a stack trace behind a disclosure, a reference. */
  default?: () => unknown
}>()

const GLYPH: Record<ResultStatus, IconName> = {
  success: 'check-circle',
  warning: 'warning',
  danger: 'close-circle',
  info: 'info',
  403: 'lock',
  404: 'search',
  500: 'warning',
}

/* The three numbers borrow a colour rather than having one of their own. */
const TONE: Record<ResultStatus, string> = {
  success: 'success',
  warning: 'warning',
  danger: 'danger',
  info: 'info',
  403: 'warning',
  404: 'info',
  500: 'danger',
}

const icon = computed(() => props.icon ?? GLYPH[props.status])

const classes = computed(() => ['wx-result', `wx-result--${TONE[props.status]}`])
</script>

<template>
  <div :class="classes">
    <div class="wx-result__icon" aria-hidden="true">
      <slot name="icon">
        <wx-icon :name="icon" />
      </slot>
    </div>

    <p v-if="title || $slots.title" class="wx-result__title">
      <slot name="title">{{ title }}</slot>
    </p>

    <p v-if="subtitle || $slots.subtitle" class="wx-result__subtitle">
      <slot name="subtitle">{{ subtitle }}</slot>
    </p>

    <div v-if="$slots.actions" class="wx-result__actions">
      <slot name="actions" />
    </div>

    <div v-if="$slots.default" class="wx-result__body">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.wx-result {
  display: flex;
  flex-direction: column;
  align-items: center;
  box-sizing: border-box;
  gap: var(--wx-space-8);
  padding: var(--wx-space-48) var(--wx-space-24);
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
  text-align: center;
}

/*
 * The glyph carries the outcome, so it is the one thing here drawn in a colour —
 * the words below it say the same thing for anyone who cannot use the colour.
 */
.wx-result__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 56px;
  height: 56px;
  margin-bottom: var(--wx-space-4);
  background: var(--wx-result-soft);
  border-radius: var(--wx-radius-full);
  color: var(--wx-result-accent);
}

.wx-result__icon :deep(.wx-icon) {
  width: 28px;
  height: 28px;
}

.wx-result--success {
  --wx-result-accent: var(--wx-color-success-active);
  --wx-result-soft: var(--wx-color-success-soft);
}

.wx-result--warning {
  --wx-result-accent: var(--wx-color-warning-active);
  --wx-result-soft: var(--wx-color-warning-soft);
}

.wx-result--danger {
  --wx-result-accent: var(--wx-color-danger);
  --wx-result-soft: var(--wx-color-danger-soft);
}

.wx-result--info {
  --wx-result-accent: var(--wx-color-info-active);
  --wx-result-soft: var(--wx-color-info-soft);
}

.wx-result__title {
  margin: 0;
  color: var(--wx-text-strong);
  font-size: var(--wx-font-size-xl);
  font-weight: var(--wx-font-weight-semibold);
  line-height: var(--wx-font-line-height-tight);
}

.wx-result__subtitle {
  max-width: 52ch;
  margin: 0;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  line-height: var(--wx-font-line-height-normal);
}

.wx-result__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: var(--wx-space-8);
  margin-top: var(--wx-space-8);
}

.wx-result__body {
  width: 100%;
  margin-top: var(--wx-space-16);
  text-align: start;
}
</style>
