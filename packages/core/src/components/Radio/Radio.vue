<script setup lang="ts">
import { computed, inject } from 'vue'
import { radioGroupKey, useFormField, type ChoiceValue } from '../../composables/useFormField'
import type { RadioEmits, RadioProps } from './types'

defineOptions({ name: 'WxRadio', inheritAttrs: false })

const props = withDefaults(defineProps<RadioProps>(), {
  label: undefined,
  disabled: undefined,
  size: undefined,
  id: undefined,
  name: undefined,
  ariaLabel: undefined,
})

const emit = defineEmits<RadioEmits>()

const model = defineModel<ChoiceValue>({ default: null })

const group = inject(radioGroupKey, null)
const field = useFormField(props)

const checked = computed(() =>
  group ? group.modelValue.value === props.value : model.value === props.value,
)

const disabled = computed(() => props.disabled || group?.disabled.value || field.disabled.value)
const size = computed(() => props.size ?? group?.size.value ?? field.size.value)

const classes = computed(() => [
  'wx-radio',
  `wx-radio--${size.value}`,
  { 'is-checked': checked.value, 'is-disabled': disabled.value },
])

function onChange() {
  if (group) group.toggle(props.value, true)
  else model.value = props.value
  emit('change', props.value)
}
</script>

<template>
  <label :class="classes">
    <input
      :id="field.id.value"
      v-bind="$attrs"
      class="wx-radio__native"
      type="radio"
      :name="name ?? group?.name.value"
      :checked="checked"
      :disabled="disabled"
      :aria-label="ariaLabel"
      :aria-describedby="field.describedBy.value"
      @change="onChange"
    />

    <span class="wx-radio__box" aria-hidden="true">
      <span class="wx-radio__dot" />
    </span>

    <span v-if="label || $slots.default" class="wx-radio__label">
      <slot>{{ label }}</slot>
    </span>
  </label>
</template>

<style scoped>
.wx-radio {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-8);
  cursor: pointer;
  user-select: none;
  color: var(--wx-text-default);
}

.wx-radio.is-disabled {
  color: var(--wx-text-disabled);
  cursor: not-allowed;
}

.wx-radio__native {
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

.wx-radio__box {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  box-sizing: border-box;
  width: var(--wx-radio-size, 20px);
  height: var(--wx-radio-size, 20px);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-strong);
  border-radius: var(--wx-radius-full);
  transition:
    border-color var(--wx-duration-fast) var(--wx-easing-standard),
    box-shadow var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-radio--sm {
  --wx-radio-size: 16px;
  font-size: var(--wx-font-size-sm);
}

.wx-radio--md {
  --wx-radio-size: 20px;
  font-size: var(--wx-font-size-md);
}

.wx-radio--lg {
  --wx-radio-size: 24px;
  font-size: var(--wx-font-size-lg);
}

.wx-radio__dot {
  width: 50%;
  height: 50%;
  border-radius: var(--wx-radius-full);
  background: var(--wx-color-primary);
  transform: scale(0);
  transition: transform var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-radio:hover:not(.is-disabled) .wx-radio__box {
  border-color: var(--wx-color-primary);
}

.wx-radio.is-checked .wx-radio__box {
  border-color: var(--wx-color-primary);
}

.wx-radio.is-checked .wx-radio__dot {
  transform: scale(1);
}

.wx-radio.is-disabled .wx-radio__box {
  background: var(--wx-bg-disabled);
  border-color: var(--wx-border-default);
}

.wx-radio.is-disabled .wx-radio__dot {
  background: var(--wx-color-primary-disabled);
}

.wx-radio__native:focus-visible + .wx-radio__box {
  border-color: var(--wx-color-primary);
  box-shadow: var(--wx-ring-focus);
}

.wx-radio__label {
  line-height: var(--wx-font-line-height-normal);
}

@media (prefers-reduced-motion: reduce) {
  .wx-radio__box,
  .wx-radio__dot {
    transition: none;
  }
}
</style>
