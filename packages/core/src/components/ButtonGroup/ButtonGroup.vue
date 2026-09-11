<script setup lang="ts">
import { computed, provide } from 'vue'
import { buttonGroupKey } from '../../composables/useButtonGroup'
import type { ButtonGroupProps } from './types'

defineOptions({ name: 'WxButtonGroup' })

const props = withDefaults(defineProps<ButtonGroupProps>(), {
  type: undefined,
  variant: undefined,
  size: undefined,
  disabled: false,
  vertical: false,
  attached: true,
  ariaLabel: undefined,
})

provide(buttonGroupKey, {
  type: computed(() => props.type),
  variant: computed(() => props.variant),
  size: computed(() => props.size),
  disabled: computed(() => props.disabled),
  vertical: computed(() => props.vertical),
})

const classes = computed(() => [
  'wx-button-group',
  {
    'wx-button-group--vertical': props.vertical,
    'wx-button-group--attached': props.attached,
  },
])
</script>

<template>
  <div :class="classes" role="group" :aria-label="ariaLabel">
    <slot />
  </div>
</template>

<style scoped>
.wx-button-group {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-8);
  vertical-align: middle;
}

.wx-button-group--vertical {
  flex-direction: column;
  align-items: stretch;
}

/* Joined: the buttons share one outline, so the gap goes and the borders overlap. */
.wx-button-group--attached {
  gap: 0;
}

.wx-button-group--attached :deep(.wx-button) {
  position: relative;
  border-radius: 0;
}

.wx-button-group--attached :deep(.wx-button:first-child) {
  border-start-start-radius: var(--wx-radius-control);
  border-end-start-radius: var(--wx-radius-control);
}

.wx-button-group--attached :deep(.wx-button:last-child) {
  border-start-end-radius: var(--wx-radius-control);
  border-end-end-radius: var(--wx-radius-control);
}

.wx-button-group--attached :deep(.wx-button:not(:first-child)) {
  margin-left: -1px;
}

/* The hovered or focused button owns the shared border, so it is not half-covered. */
.wx-button-group--attached :deep(.wx-button:hover),
.wx-button-group--attached :deep(.wx-button:focus-visible),
.wx-button-group--attached :deep(.wx-button:active) {
  z-index: 1;
}

.wx-button-group--attached.wx-button-group--vertical :deep(.wx-button) {
  border-radius: 0;
}

.wx-button-group--attached.wx-button-group--vertical :deep(.wx-button:first-child) {
  border-start-start-radius: var(--wx-radius-control);
  border-start-end-radius: var(--wx-radius-control);
}

.wx-button-group--attached.wx-button-group--vertical :deep(.wx-button:last-child) {
  border-end-start-radius: var(--wx-radius-control);
  border-end-end-radius: var(--wx-radius-control);
}

.wx-button-group--attached.wx-button-group--vertical :deep(.wx-button:not(:first-child)) {
  margin-left: 0;
  margin-top: -1px;
}
</style>
