<script setup lang="ts">
import { computed } from 'vue'
import type { ButtonEmits, ButtonProps } from './types'

defineOptions({ name: 'WxButton', inheritAttrs: false })

const props = withDefaults(defineProps<ButtonProps>(), {
  type: 'default',
  variant: 'solid',
  size: 'md',
  disabled: false,
  loading: false,
  block: false,
  round: false,
  href: undefined,
  target: undefined,
  nativeType: 'button',
})

const emit = defineEmits<ButtonEmits>()

const isDisabled = computed(() => props.disabled || props.loading)
const tag = computed(() => (props.href ? 'a' : 'button'))

const classes = computed(() => [
  'wx-button',
  `wx-button--${props.type}`,
  `wx-button--${props.variant}`,
  `wx-button--${props.size}`,
  {
    'wx-button--block': props.block,
    'wx-button--round': props.round,
    'wx-button--loading': props.loading,
    'is-disabled': isDisabled.value,
  },
])

const nativeAttrs = computed(() =>
  props.href
    ? {
        href: isDisabled.value ? undefined : props.href,
        target: props.target,
        role: 'button',
        'aria-disabled': isDisabled.value ? 'true' : undefined,
        tabindex: isDisabled.value ? -1 : undefined,
      }
    : {
        type: props.nativeType,
        disabled: isDisabled.value,
      },
)

function onClick(event: MouseEvent) {
  if (isDisabled.value) {
    event.preventDefault()
    event.stopPropagation()
    return
  }
  emit('click', event)
}
</script>

<template>
  <component
    :is="tag"
    v-bind="{ ...nativeAttrs, ...$attrs }"
    :class="classes"
    :aria-busy="loading || undefined"
    @click="onClick"
  >
    <span v-if="loading" class="wx-button__spinner" aria-hidden="true" />
    <span v-else-if="$slots.icon" class="wx-button__icon">
      <slot name="icon" />
    </span>
    <span v-if="$slots.default" class="wx-button__label">
      <slot />
    </span>
  </component>
</template>

<style scoped>
.wx-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--wx-space-8);
  box-sizing: border-box;
  border: 1px solid transparent;
  border-radius: var(--wx-radius-control);
  font-family: inherit;
  font-weight: var(--wx-font-weight-semibold);
  line-height: var(--wx-font-line-height-tight);
  text-decoration: none;
  white-space: nowrap;
  cursor: pointer;
  user-select: none;
  transition:
    background-color var(--wx-duration-normal) var(--wx-easing-standard),
    border-color var(--wx-duration-normal) var(--wx-easing-standard),
    color var(--wx-duration-normal) var(--wx-easing-standard),
    box-shadow var(--wx-duration-normal) var(--wx-easing-standard);
}

.wx-button:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

/* sizes */
.wx-button--sm {
  height: var(--wx-size-control-sm);
  padding: 0 var(--wx-space-12);
  font-size: var(--wx-font-size-sm);
}

.wx-button--md {
  height: var(--wx-size-control-md);
  padding: 0 var(--wx-space-18);
  font-size: var(--wx-font-size-md);
}

.wx-button--lg {
  height: var(--wx-size-control-lg);
  padding: 0 var(--wx-space-24);
  font-size: var(--wx-font-size-lg);
}

.wx-button--block {
  display: flex;
  width: 100%;
}

.wx-button--round {
  border-radius: var(--wx-radius-full);
}

/*
 * Each type exposes its own palette through local variables, so the variant rules
 * below stay type-agnostic.
 */
.wx-button--default {
  --wx-button-bg: var(--wx-bg-surface);
  --wx-button-bg-hover: var(--wx-bg-fill);
  --wx-button-bg-active: var(--wx-bg-fill-hover);
  --wx-button-bg-disabled: var(--wx-bg-surface);
  --wx-button-fg: var(--wx-text-default);
  --wx-button-border: var(--wx-border-default);
}

.wx-button--primary {
  --wx-button-bg: var(--wx-color-primary);
  --wx-button-bg-hover: var(--wx-color-primary-hover);
  --wx-button-bg-active: var(--wx-color-primary-active);
  --wx-button-bg-disabled: var(--wx-color-primary-disabled);
  --wx-button-fg: var(--wx-color-primary-contrast);
  --wx-button-border: var(--wx-color-primary);
  --wx-button-accent: var(--wx-color-primary);
  --wx-button-soft: var(--wx-color-primary-soft);
}

.wx-button--success {
  --wx-button-bg: var(--wx-color-success);
  --wx-button-bg-hover: var(--wx-color-success-hover);
  --wx-button-bg-active: var(--wx-color-success-active);
  --wx-button-bg-disabled: var(--wx-color-success-disabled);
  --wx-button-fg: var(--wx-color-success-contrast);
  --wx-button-border: var(--wx-color-success);
  --wx-button-accent: var(--wx-color-success);
  --wx-button-soft: var(--wx-color-success-soft);
}

.wx-button--warning {
  --wx-button-bg: var(--wx-color-warning);
  --wx-button-bg-hover: var(--wx-color-warning-hover);
  --wx-button-bg-active: var(--wx-color-warning-active);
  --wx-button-bg-disabled: var(--wx-color-warning-disabled);
  --wx-button-fg: var(--wx-color-warning-contrast);
  --wx-button-border: var(--wx-color-warning);
  --wx-button-accent: var(--wx-color-warning);
  --wx-button-soft: var(--wx-color-warning-soft);
}

.wx-button--danger {
  --wx-button-bg: var(--wx-color-danger);
  --wx-button-bg-hover: var(--wx-color-danger-hover);
  --wx-button-bg-active: var(--wx-color-danger-active);
  --wx-button-bg-disabled: var(--wx-color-danger-disabled);
  --wx-button-fg: var(--wx-color-danger-contrast);
  --wx-button-border: var(--wx-color-danger);
  --wx-button-accent: var(--wx-color-danger);
  --wx-button-soft: var(--wx-color-danger-soft);
}

/* solid */
.wx-button--solid {
  background: var(--wx-button-bg);
  border-color: var(--wx-button-border);
  color: var(--wx-button-fg);
}

.wx-button--solid:hover:not(.is-disabled) {
  background: var(--wx-button-bg-hover);
  border-color: var(--wx-button-bg-hover);
}

.wx-button--solid:active:not(.is-disabled) {
  background: var(--wx-button-bg-active);
  border-color: var(--wx-button-bg-active);
}

.wx-button--solid.is-disabled {
  background: var(--wx-button-bg-disabled);
  border-color: var(--wx-button-bg-disabled);
}

.wx-button--solid.wx-button--default.is-disabled {
  border-color: var(--wx-border-default);
  color: var(--wx-text-disabled);
}

/* outline */
.wx-button--outline {
  background: transparent;
  border-color: var(--wx-button-accent, var(--wx-border-default));
  color: var(--wx-button-accent, var(--wx-text-default));
}

.wx-button--outline:hover:not(.is-disabled) {
  background: var(--wx-button-soft, var(--wx-bg-fill));
}

.wx-button--outline:active:not(.is-disabled) {
  border-color: var(--wx-button-bg-active);
  color: var(--wx-button-bg-active);
}

.wx-button--outline.is-disabled {
  border-color: var(--wx-button-bg-disabled);
  color: var(--wx-button-bg-disabled);
}

/* text */
.wx-button--text {
  background: transparent;
  border-color: transparent;
  color: var(--wx-button-accent, var(--wx-text-default));
}

.wx-button--text:hover:not(.is-disabled) {
  background: var(--wx-bg-fill);
}

.wx-button--text:active:not(.is-disabled) {
  background: var(--wx-bg-fill-hover);
}

.wx-button--text.is-disabled {
  color: var(--wx-text-disabled);
}

/* states */
.wx-button.is-disabled {
  cursor: not-allowed;
}

.wx-button--loading {
  cursor: progress;
}

.wx-button__spinner {
  width: 1em;
  height: 1em;
  border: 2px solid currentcolor;
  border-right-color: transparent;
  border-radius: var(--wx-radius-full);
  animation: wx-button-spin var(--wx-duration-slow) linear infinite;
}

.wx-button__icon {
  display: inline-flex;
  align-items: center;
}

@keyframes wx-button-spin {
  to {
    transform: rotate(360deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  .wx-button {
    transition: none;
  }

  .wx-button__spinner {
    animation-duration: 2s;
  }
}
</style>
