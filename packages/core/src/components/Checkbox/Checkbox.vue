<script setup lang="ts">
import { computed, inject, onMounted, ref, watch } from 'vue'
import { checkboxGroupKey, useFormField } from '../../composables/useFormField'
import type { CheckboxEmits, CheckboxProps } from './types'
import { useControlAttrs } from '../../composables/useControlAttrs'

defineOptions({ name: 'WxCheckbox', inheritAttrs: false })

/* `class` and `style` belong to the control; the rest belongs to its input. */
const { rootAttrs, controlAttrs } = useControlAttrs()

const props = withDefaults(defineProps<CheckboxProps>(), {
  value: undefined,
  label: undefined,
  indeterminate: false,
  disabled: undefined,
  size: undefined,
  id: undefined,
  name: undefined,
  ariaLabel: undefined,
})

const emit = defineEmits<CheckboxEmits>()

const model = defineModel<boolean>({ default: false })

const group = inject(checkboxGroupKey, null)
const field = useFormField(props)
const inputRef = ref<HTMLInputElement | null>(null)

const checked = computed(() =>
  group ? group.modelValue.value.includes(props.value ?? null) : model.value === true,
)

const disabled = computed(() => props.disabled || group?.disabled.value || field.disabled.value)
const size = computed(() => props.size ?? group?.size.value ?? field.size.value)

const classes = computed(() => [
  'wx-checkbox',
  `wx-checkbox--${size.value}`,
  {
    'is-checked': checked.value,
    'is-indeterminate': props.indeterminate && !checked.value,
    'is-disabled': disabled.value,
  },
])

/** `indeterminate` only exists as a DOM property, never as an attribute. */
function syncIndeterminate() {
  if (inputRef.value) inputRef.value.indeterminate = props.indeterminate && !checked.value
}

onMounted(syncIndeterminate)
watch([() => props.indeterminate, checked], syncIndeterminate)

function onChange(event: Event) {
  const next = (event.target as HTMLInputElement).checked
  if (group) group.toggle(props.value ?? null, next)
  else model.value = next
  emit('change', next)
}
</script>

<template>
  <label :class="classes" v-bind="rootAttrs">
    <input
      :id="field.id.value"
      ref="inputRef"
      v-bind="controlAttrs"
      class="wx-checkbox__native"
      type="checkbox"
      :name="name ?? group?.name.value"
      :checked="checked"
      :disabled="disabled"
      :aria-label="ariaLabel"
      :aria-describedby="field.describedBy.value"
      @change="onChange"
    />

    <span class="wx-checkbox__box" aria-hidden="true">
      <svg class="wx-checkbox__mark" viewBox="0 0 16 16" fill="none">
        <path
          v-if="indeterminate && !checked"
          d="M4 8h8"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
        />
        <path
          v-else
          d="M3.5 8.5l3 3 6-6"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </svg>
    </span>

    <span v-if="label || $slots.default" class="wx-checkbox__label">
      <slot>{{ label }}</slot>
    </span>
  </label>
</template>

<style scoped>
.wx-checkbox {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-8);
  cursor: pointer;
  user-select: none;
  color: var(--wx-text-default);
}

.wx-checkbox.is-disabled {
  color: var(--wx-text-disabled);
  cursor: not-allowed;
}

.wx-checkbox__native {
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

.wx-checkbox__box {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  box-sizing: border-box;
  width: var(--wx-checkbox-size, 20px);
  height: var(--wx-checkbox-size, 20px);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-strong);
  border-radius: var(--wx-radius-xs);
  color: transparent;
  transition:
    background-color var(--wx-duration-fast) var(--wx-easing-standard),
    border-color var(--wx-duration-fast) var(--wx-easing-standard),
    color var(--wx-duration-fast) var(--wx-easing-standard),
    box-shadow var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-checkbox--sm {
  --wx-checkbox-size: 16px;
  font-size: var(--wx-font-size-control-sm);
}

.wx-checkbox--md {
  --wx-checkbox-size: 20px;
  /* A tick beside a label is a choice in a list, and reads at the list size. */
  font-size: var(--wx-font-size-control-md);
}

.wx-checkbox--lg {
  --wx-checkbox-size: 24px;
  font-size: var(--wx-font-size-control-lg);
}

.wx-checkbox:hover:not(.is-disabled) .wx-checkbox__box {
  border-color: var(--wx-color-primary);
}

.wx-checkbox.is-checked .wx-checkbox__box,
.wx-checkbox.is-indeterminate .wx-checkbox__box {
  border-color: var(--wx-color-primary);
  color: var(--wx-color-primary);
}

.wx-checkbox.is-disabled .wx-checkbox__box {
  background: var(--wx-bg-disabled);
  border-color: var(--wx-border-default);
}

.wx-checkbox.is-disabled.is-checked .wx-checkbox__box,
.wx-checkbox.is-disabled.is-indeterminate .wx-checkbox__box {
  color: var(--wx-color-primary-disabled);
  border-color: var(--wx-color-primary-disabled);
}

.wx-checkbox__native:focus-visible + .wx-checkbox__box {
  border-color: var(--wx-color-primary);
  box-shadow: var(--wx-ring-focus);
}

.wx-checkbox__mark {
  width: 100%;
  height: 100%;
}

.wx-checkbox__label {
  line-height: var(--wx-font-line-height-normal);
}

@media (prefers-reduced-motion: reduce) {
  .wx-checkbox__box {
    transition: none;
  }
}
</style>
