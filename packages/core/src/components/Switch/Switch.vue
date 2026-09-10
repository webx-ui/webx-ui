<script setup lang="ts">
import { computed } from 'vue'
import { useFormField, type ChoiceValue } from '../../composables/useFormField'
import type { SwitchEmits, SwitchProps } from './types'

defineOptions({ name: 'WxSwitch', inheritAttrs: false })

const props = withDefaults(defineProps<SwitchProps>(), {
  activeValue: true,
  inactiveValue: false,
  label: undefined,
  disabled: undefined,
  size: undefined,
  id: undefined,
  name: undefined,
  ariaLabel: undefined,
})

const emit = defineEmits<SwitchEmits>()

const model = defineModel<ChoiceValue>({ default: false })

const field = useFormField(props)

const checked = computed(() => model.value === props.activeValue)
const size = computed(() => props.size ?? field.size.value)

const classes = computed(() => [
  'wx-switch',
  `wx-switch--${size.value}`,
  { 'is-checked': checked.value, 'is-disabled': field.disabled.value },
])

function onChange(event: Event) {
  const next = (event.target as HTMLInputElement).checked ? props.activeValue : props.inactiveValue
  model.value = next
  emit('change', next)
}
</script>

<template>
  <label :class="classes">
    <input
      :id="field.id.value"
      v-bind="$attrs"
      class="wx-switch__native"
      type="checkbox"
      role="switch"
      :name="name"
      :checked="checked"
      :disabled="field.disabled.value"
      :aria-label="ariaLabel"
      :aria-describedby="field.describedBy.value"
      @change="onChange"
    />

    <span class="wx-switch__track" aria-hidden="true">
      <span class="wx-switch__thumb" />
    </span>

    <span v-if="label || $slots.default" class="wx-switch__label">
      <slot>{{ label }}</slot>
    </span>
  </label>
</template>

<style scoped>
.wx-switch {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-8);
  cursor: pointer;
  user-select: none;
  color: var(--wx-text-default);
}

.wx-switch.is-disabled {
  color: var(--wx-text-disabled);
  cursor: not-allowed;
}

.wx-switch__native {
  position: absolute;
  width: 1px;
  height: 1px;
  margin: -1px;
  padding: 0;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
  border: 0;
}

.wx-switch__track {
  display: inline-flex;
  align-items: center;
  flex: 0 0 auto;
  box-sizing: border-box;
  width: calc(var(--wx-switch-height) * 1.8);
  height: var(--wx-switch-height);
  padding: 2px;
  background: var(--wx-border-strong);
  border-radius: var(--wx-radius-full);
  transition:
    background-color var(--wx-duration-normal) var(--wx-easing-standard),
    box-shadow var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-switch--sm {
  --wx-switch-height: 18px;
  font-size: var(--wx-font-size-sm);
}

.wx-switch--md {
  --wx-switch-height: 22px;
  font-size: var(--wx-font-size-md);
}

.wx-switch--lg {
  --wx-switch-height: 26px;
  font-size: var(--wx-font-size-lg);
}

.wx-switch__thumb {
  width: calc(var(--wx-switch-height) - 4px);
  height: calc(var(--wx-switch-height) - 4px);
  background: var(--wx-bg-surface);
  border-radius: var(--wx-radius-full);
  box-shadow: var(--wx-shadow-card);
  transition: transform var(--wx-duration-normal) var(--wx-easing-standard);
}

.wx-switch.is-checked .wx-switch__track {
  background: var(--wx-color-primary);
}

.wx-switch.is-checked .wx-switch__thumb {
  transform: translateX(calc(var(--wx-switch-height) * 0.8));
}

.wx-switch.is-disabled .wx-switch__track {
  background: var(--wx-border-default);
}

.wx-switch.is-disabled.is-checked .wx-switch__track {
  background: var(--wx-color-primary-disabled);
}

.wx-switch__native:focus-visible + .wx-switch__track {
  box-shadow: var(--wx-ring-focus);
}

.wx-switch__label {
  line-height: var(--wx-font-line-height-normal);
}

@media (prefers-reduced-motion: reduce) {
  .wx-switch__track,
  .wx-switch__thumb {
    transition: none;
  }
}
</style>
