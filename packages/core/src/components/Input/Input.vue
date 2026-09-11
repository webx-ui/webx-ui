<script setup lang="ts">
import { computed, ref } from 'vue'
import { useFormField } from '../../composables/useFormField'
import type { InputEmits, InputModelValue, InputProps } from './types'
import { useControlAttrs } from '../../composables/useControlAttrs'

defineOptions({ name: 'WxInput', inheritAttrs: false })

/* `class` and `style` belong to the control; the rest belongs to its input. */
const { rootAttrs, controlAttrs } = useControlAttrs()

const props = withDefaults(defineProps<InputProps>(), {
  type: 'text',
  size: undefined,
  status: undefined,
  id: undefined,
  placeholder: undefined,
  disabled: undefined,
  readonly: false,
  clearable: false,
  maxlength: undefined,
  showCount: false,
  autocomplete: undefined,
  ariaLabel: undefined,
})

const emit = defineEmits<InputEmits>()

const model = defineModel<InputModelValue>({ default: '' })

const field = useFormField(props)
const inputRef = ref<HTMLInputElement | null>(null)
const focused = ref(false)

const currentValue = computed(() => (model.value == null ? '' : String(model.value)))

const showClear = computed(
  () =>
    props.clearable && !field.disabled.value && !props.readonly && currentValue.value.length > 0,
)

const counter = computed(() =>
  props.showCount && props.maxlength ? `${currentValue.value.length}/${props.maxlength}` : null,
)

const classes = computed(() => [
  'wx-input',
  `wx-input--${field.size.value}`,
  {
    [`wx-input--${field.status.value}`]: field.status.value !== 'default',
    'is-focused': focused.value,
    'is-disabled': field.disabled.value,
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
  <div :class="classes" v-bind="rootAttrs">
    <span v-if="$slots.prefix" class="wx-input__affix wx-input__affix--prefix">
      <slot name="prefix" />
    </span>

    <input
      :id="field.id.value"
      ref="inputRef"
      v-bind="controlAttrs"
      class="wx-input__inner"
      :type="type"
      :value="currentValue"
      :placeholder="placeholder"
      :disabled="field.disabled.value"
      :readonly="readonly"
      :maxlength="maxlength"
      :autocomplete="autocomplete"
      :aria-label="ariaLabel"
      :aria-describedby="field.describedBy.value"
      :aria-invalid="field.status.value === 'error' || undefined"
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
  gap: var(--wx-space-8);
  box-sizing: border-box;
  width: 100%;
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-control);
  color: var(--wx-text-default);
  transition:
    border-color var(--wx-duration-normal) var(--wx-easing-standard),
    background-color var(--wx-duration-normal) var(--wx-easing-standard);
}

.wx-input:hover:not(.is-disabled) {
  border-color: var(--wx-border-strong);
}

/* The reference marks focus with a tinted border rather than a ring. */
.wx-input.is-focused {
  border-color: var(--wx-border-focus);
}

.wx-input--sm {
  height: var(--wx-size-control-sm);
  padding: 0 var(--wx-space-10);
  font-size: var(--wx-font-size-control-sm);
}

.wx-input--md {
  height: var(--wx-size-control-md);
  padding: 0 var(--wx-space-12);
  font-size: var(--wx-font-size-control-md);
}

.wx-input--lg {
  height: var(--wx-size-control-lg);
  padding: 0 var(--wx-space-16);
  font-size: var(--wx-font-size-control-lg);
}

.wx-input--error,
.wx-input--error.is-focused {
  border-color: var(--wx-color-danger);
}

.wx-input--success,
.wx-input--success.is-focused {
  border-color: var(--wx-color-success);
}

.wx-input--warning,
.wx-input--warning.is-focused {
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
  color: var(--wx-text-placeholder);
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
  width: 18px;
  height: 18px;
  padding: 0;
  background: transparent;
  border: none;
  border-radius: var(--wx-radius-full);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  line-height: 1;
  cursor: pointer;
  transition: background-color var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-input__clear:hover {
  background: var(--wx-bg-fill);
  color: var(--wx-text-default);
}
</style>
