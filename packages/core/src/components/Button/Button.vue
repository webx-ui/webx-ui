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
  gap: var(--wx-space-2);
  box-sizing: border-box;
  border: 1px solid transparent;
  border-radius: var(--wx-radius-md);
  font-family: inherit;
  font-weight: var(--wx-font-weight-medium);
  line-height: var(--wx-font-line-height-tight);
  text-decoration: none;
  white-space: nowrap;
  cursor: pointer;
  user-select: none;
  transition:
    background-color var(--wx-duration-fast) var(--wx-easing-standard),
    border-color var(--wx-duration-fast) var(--wx-easing-standard),
    color var(--wx-duration-fast) var(--wx-easing-standard),
    box-shadow var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-button:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

/* sizes */
.wx-button--sm {
  height: var(--wx-size-control-sm);
  padding: 0 var(--wx-space-3);
  font-size: var(--wx-font-size-sm);
}

.wx-button--md {
  height: var(--wx-size-control-md);
  padding: 0 var(--wx-space-5);
  font-size: var(--wx-font-size-md);
}

.wx-button--lg {
  height: var(--wx-size-control-lg);
  padding: 0 var(--wx-space-7);
  font-size: var(--wx-font-size-lg);
}

.wx-button--block {
  display: flex;
  width: 100%;
}

.wx-button--round {
  border-radius: var(--wx-radius-full);
}

/* solid */
.wx-button--solid.wx-button--default {
  background: var(--wx-bg-surface);
  border-color: var(--wx-border-strong);
  color: var(--wx-text-default);
}

.wx-button--solid.wx-button--default:hover:not(.is-disabled) {
  background: var(--wx-bg-muted);
}

.wx-button--solid.wx-button--primary {
  background: var(--wx-color-primary);
  color: var(--wx-color-primary-contrast);
}

.wx-button--solid.wx-button--primary:hover:not(.is-disabled) {
  background: var(--wx-color-primary-hover);
}

.wx-button--solid.wx-button--success {
  background: var(--wx-color-success);
  color: var(--wx-color-primary-contrast);
}

.wx-button--solid.wx-button--warning {
  background: var(--wx-color-warning);
  color: var(--wx-color-primary-contrast);
}

.wx-button--solid.wx-button--danger {
  background: var(--wx-color-danger);
  color: var(--wx-color-danger-contrast);
}

.wx-button--solid.wx-button--danger:hover:not(.is-disabled) {
  background: var(--wx-color-danger-hover);
}

.wx-button--solid.wx-button--success:hover:not(.is-disabled),
.wx-button--solid.wx-button--warning:hover:not(.is-disabled) {
  filter: brightness(0.94);
}

/* outline */
.wx-button--outline {
  background: transparent;
  border-color: var(--wx-border-strong);
  color: var(--wx-text-default);
}

.wx-button--outline.wx-button--primary {
  border-color: var(--wx-color-primary);
  color: var(--wx-color-primary);
}

.wx-button--outline.wx-button--success {
  border-color: var(--wx-color-success);
  color: var(--wx-color-success);
}

.wx-button--outline.wx-button--warning {
  border-color: var(--wx-color-warning);
  color: var(--wx-color-warning);
}

.wx-button--outline.wx-button--danger {
  border-color: var(--wx-color-danger);
  color: var(--wx-color-danger);
}

.wx-button--outline:hover:not(.is-disabled) {
  background: var(--wx-bg-muted);
}

.wx-button--outline.wx-button--primary:hover:not(.is-disabled) {
  background: var(--wx-color-primary-soft);
}

.wx-button--outline.wx-button--danger:hover:not(.is-disabled) {
  background: var(--wx-color-danger-soft);
}

/* text */
.wx-button--text {
  background: transparent;
  border-color: transparent;
  color: var(--wx-text-default);
}

.wx-button--text.wx-button--primary {
  color: var(--wx-color-primary);
}

.wx-button--text.wx-button--danger {
  color: var(--wx-color-danger);
}

.wx-button--text.wx-button--success {
  color: var(--wx-color-success);
}

.wx-button--text.wx-button--warning {
  color: var(--wx-color-warning);
}

.wx-button--text:hover:not(.is-disabled) {
  background: var(--wx-bg-muted);
}

/* states */
.wx-button.is-disabled {
  cursor: not-allowed;
  opacity: 0.55;
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
