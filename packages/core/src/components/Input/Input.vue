<script setup lang="ts">
import { computed, ref } from 'vue'
import type { InputEmits, InputModelValue, InputProps } from './types'

defineOptions({ name: 'WxInput', inheritAttrs: false })

const props = withDefaults(defineProps<InputProps>(), {
  type: 'text',
  size: 'md',
  status: 'default',
  placeholder: undefined,
  disabled: false,
  readonly: false,
  clearable: false,
  maxlength: undefined,
  showCount: false,
  autocomplete: undefined,
  ariaLabel: undefined,
})

const emit = defineEmits<InputEmits>()

const model = defineModel<InputModelValue>({ default: '' })

const inputRef = ref<HTMLInputElement | null>(null)
const focused = ref(false)

const currentValue = computed(() => (model.value == null ? '' : String(model.value)))

const showClear = computed(
  () => props.clearable && !props.disabled && !props.readonly && currentValue.value.length > 0,
)

const counter = computed(() =>
  props.showCount && props.maxlength ? `${currentValue.value.length}/${props.maxlength}` : null,
)

const classes = computed(() => [
  'wx-input',
  `wx-input--${props.size}`,
  {
    [`wx-input--${props.status}`]: props.status !== 'default',
    'is-focused': focused.value,
    'is-disabled': props.disabled,
    'is-readonly': props.readonly,
  },
])

function onInput(event: Event) {
  const value = (event.target as HTMLInputElement).value
  model.value = value
  emit('input', value)
}

function onChange(event: Event) {
  emit('change', (event.target as HTMLInputElement).value)
}

function onFocus(event: FocusEvent) {
  focused.value = true
  emit('focus', event)
}

function onBlur(event: FocusEvent) {
  focused.value = false
  emit('blur', event)
}

function clear() {
  model.value = ''
  emit('input', '')
  emit('change', '')
  emit('clear')
  inputRef.value?.focus()
}

defineExpose({
  focus: () => inputRef.value?.focus(),
  blur: () => inputRef.value?.blur(),
  select: () => inputRef.value?.select(),
  input: inputRef,
})
</script>

<template>
  <div :class="classes">
    <span v-if="$slots.prefix" class="wx-input__affix wx-input__affix--prefix">
      <slot name="prefix" />
    </span>

    <input
      ref="inputRef"
      v-bind="$attrs"
      class="wx-input__inner"
      :type="type"
      :value="currentValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :readonly="readonly"
      :maxlength="maxlength"
      :autocomplete="autocomplete"
      :aria-label="ariaLabel"
      :aria-invalid="status === 'error' || undefined"
      @input="onInput"
      @change="onChange"
      @focus="onFocus"
      @blur="onBlur"
    />

    <button
      v-if="showClear"
      class="wx-input__clear"
      type="button"
      tabindex="-1"
      aria-label="Clear"
      @click="clear"
    >
      &#10005;
    </button>

    <span v-if="counter" class="wx-input__count">{{ counter }}</span>

    <span v-if="$slots.suffix" class="wx-input__affix wx-input__affix--suffix">
      <slot name="suffix" />
    </span>
  </div>
</template>

<style scoped>
.wx-input {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-2);
  box-sizing: border-box;
  width: 100%;
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  color: var(--wx-text-default);
  transition:
    border-color var(--wx-duration-fast) var(--wx-easing-standard),
    box-shadow var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-input:hover:not(.is-disabled) {
  border-color: var(--wx-border-strong);
}

.wx-input.is-focused {
  border-color: var(--wx-border-focus);
  box-shadow: var(--wx-ring-focus);
}

.wx-input--sm {
  height: var(--wx-size-control-sm);
  padding: 0 var(--wx-space-3);
  font-size: var(--wx-font-size-sm);
}

.wx-input--md {
  height: var(--wx-size-control-md);
  padding: 0 var(--wx-space-4);
  font-size: var(--wx-font-size-md);
}

.wx-input--lg {
  height: var(--wx-size-control-lg);
  padding: 0 var(--wx-space-5);
  font-size: var(--wx-font-size-lg);
}

.wx-input--error {
  border-color: var(--wx-color-danger);
}

.wx-input--error.is-focused {
  box-shadow: 0 0 0 3px var(--wx-color-danger-soft);
}

.wx-input--success {
  border-color: var(--wx-color-success);
}

.wx-input--warning {
  border-color: var(--wx-color-warning);
}

.wx-input.is-disabled {
  background: var(--wx-bg-disabled);
  color: var(--wx-text-disabled);
  cursor: not-allowed;
}

.wx-input__inner {
  flex: 1 1 auto;
  min-width: 0;
  height: 100%;
  padding: 0;
  background: transparent;
  border: none;
  outline: none;
  color: inherit;
  font-family: inherit;
  font-size: inherit;
  line-height: inherit;
}

.wx-input__inner::placeholder {
  color: var(--wx-text-muted);
}

.wx-input__inner:disabled {
  cursor: not-allowed;
}

.wx-input__affix,
.wx-input__count {
  display: inline-flex;
  align-items: center;
  flex: 0 0 auto;
  color: var(--wx-text-muted);
}

.wx-input__count {
  font-size: var(--wx-font-size-xs);
  font-variant-numeric: tabular-nums;
}

.wx-input__clear {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  width: 16px;
  height: 16px;
  padding: 0;
  background: transparent;
  border: none;
  border-radius: var(--wx-radius-full);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  line-height: 1;
  cursor: pointer;
}

.wx-input__clear:hover {
  background: var(--wx-bg-muted);
  color: var(--wx-text-default);
}
</style>
