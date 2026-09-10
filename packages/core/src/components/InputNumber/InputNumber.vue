<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useFormField } from '../../composables/useFormField'
import type { InputNumberEmits, InputNumberModelValue, InputNumberProps } from './types'

defineOptions({ name: 'WxInputNumber', inheritAttrs: false })

const props = withDefaults(defineProps<InputNumberProps>(), {
  min: undefined,
  max: undefined,
  step: 1,
  precision: undefined,
  controls: true,
  controlsPosition: 'sides',
  size: undefined,
  status: undefined,
  id: undefined,
  name: undefined,
  placeholder: undefined,
  disabled: undefined,
  readonly: false,
  ariaLabel: undefined,
})

const emit = defineEmits<InputNumberEmits>()

const model = defineModel<InputNumberModelValue>({ default: null })

const field = useFormField(props)
const inputRef = ref<HTMLInputElement | null>(null)
const focused = ref(false)

/** Decimals to keep: explicit `precision`, otherwise whatever `step` implies. */
const precision = computed(() => {
  if (props.precision !== undefined) return props.precision
  const [, decimals = ''] = String(props.step).split('.')
  return decimals.length
})

/** Rounds through a scaled integer so 0.1 + 0.2 does not leak into the model. */
function round(value: number): number {
  const factor = 10 ** precision.value
  return Math.round((value + Number.EPSILON) * factor) / factor
}

function clamp(value: number): number {
  let next = value
  if (props.min !== undefined) next = Math.max(next, props.min)
  if (props.max !== undefined) next = Math.min(next, props.max)
  return round(next)
}

const current = computed<number | null>(() =>
  typeof model.value === 'number' && Number.isFinite(model.value) ? model.value : null,
)

/** What the user sees. Kept as text so a half-typed "-" or "1." survives. */
const display = ref(current.value === null ? '' : current.value.toFixed(precision.value))

watch(current, (value) => {
  if (focused.value) return
  display.value = value === null ? '' : value.toFixed(precision.value)
})

const disabled = computed(() => field.disabled.value)
const canDecrease = computed(
  () =>
    !disabled.value &&
    !props.readonly &&
    (props.min === undefined || (current.value ?? 0) > props.min),
)
const canIncrease = computed(
  () =>
    !disabled.value &&
    !props.readonly &&
    (props.max === undefined || (current.value ?? 0) < props.max),
)

const classes = computed(() => [
  'wx-input-number',
  `wx-input-number--${field.size.value}`,
  `wx-input-number--controls-${props.controls ? props.controlsPosition : 'none'}`,
  {
    [`wx-input-number--${field.status.value}`]: field.status.value !== 'default',
    'is-focused': focused.value,
    'is-disabled': disabled.value,
  },
])

function commit(value: number | null) {
  if (model.value === value) return
  model.value = value
  emit('change', value)
}

function stepBy(direction: 1 | -1) {
  if (disabled.value || props.readonly) return
  const base = current.value ?? clamp(0)
  const next = clamp(base + direction * props.step)
  commit(next)
  display.value = next.toFixed(precision.value)
}

function onInput(event: Event) {
  display.value = (event.target as HTMLInputElement).value
  const parsed = Number.parseFloat(display.value)
  // While typing, only push through values that already make sense.
  if (display.value === '') commit(null)
  else if (Number.isFinite(parsed)) commit(round(parsed))
}

/** Clamping and formatting happen on blur, so typing 5 into a min=10 field is possible. */
function normalize() {
  if (display.value.trim() === '') {
    commit(null)
    display.value = ''
    return
  }
  const parsed = Number.parseFloat(display.value)
  if (!Number.isFinite(parsed)) {
    display.value = current.value === null ? '' : current.value.toFixed(precision.value)
    return
  }
  const next = clamp(parsed)
  commit(next)
  display.value = next.toFixed(precision.value)
}

function onFocus(event: FocusEvent) {
  focused.value = true
  emit('focus', event)
}

function onBlur(event: FocusEvent) {
  focused.value = false
  normalize()
  emit('blur', event)
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'ArrowUp') {
    event.preventDefault()
    stepBy(1)
  } else if (event.key === 'ArrowDown') {
    event.preventDefault()
    stepBy(-1)
  } else if (event.key === 'Enter') {
    normalize()
  }
}

defineExpose({
  focus: () => inputRef.value?.focus(),
  blur: () => inputRef.value?.blur(),
  increase: () => stepBy(1),
  decrease: () => stepBy(-1),
  input: inputRef,
})
</script>

<template>
  <div :class="classes">
    <button
      v-if="controls"
      class="wx-input-number__button wx-input-number__button--decrease"
      type="button"
      tabindex="-1"
      aria-label="Decrease"
      :disabled="!canDecrease"
      @click="stepBy(-1)"
    >
      <svg viewBox="0 0 16 16" fill="none" aria-hidden="true">
        <path d="M3.5 8h9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
      </svg>
    </button>

    <input
      :id="field.id.value"
      ref="inputRef"
      v-bind="$attrs"
      class="wx-input-number__inner"
      type="text"
      inputmode="decimal"
      autocomplete="off"
      :name="name"
      :value="display"
      :placeholder="placeholder"
      :disabled="disabled"
      :readonly="readonly"
      :aria-label="ariaLabel"
      :aria-describedby="field.describedBy.value"
      :aria-invalid="field.status.value === 'error' || undefined"
      role="spinbutton"
      :aria-valuenow="current ?? undefined"
      :aria-valuemin="min"
      :aria-valuemax="max"
      @input="onInput"
      @focus="onFocus"
      @blur="onBlur"
      @keydown="onKeydown"
    />

    <button
      v-if="controls"
      class="wx-input-number__button wx-input-number__button--increase"
      type="button"
      tabindex="-1"
      aria-label="Increase"
      :disabled="!canIncrease"
      @click="stepBy(1)"
    >
      <svg viewBox="0 0 16 16" fill="none" aria-hidden="true">
        <path
          d="M8 3.5v9M3.5 8h9"
          stroke="currentColor"
          stroke-width="1.5"
          stroke-linecap="round"
        />
      </svg>
    </button>
  </div>
</template>

<style scoped>
.wx-input-number {
  display: inline-flex;
  align-items: center;
  box-sizing: border-box;
  width: 100%;
  height: var(--wx-size-control-md);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-control);
  color: var(--wx-text-default);
  overflow: hidden;
  transition:
    border-color var(--wx-duration-normal) var(--wx-easing-standard),
    background-color var(--wx-duration-normal) var(--wx-easing-standard);
}

.wx-input-number--sm {
  height: var(--wx-size-control-sm);
  font-size: var(--wx-font-size-sm);
}

.wx-input-number--md {
  height: var(--wx-size-control-md);
  font-size: var(--wx-font-size-md);
}

.wx-input-number--lg {
  height: var(--wx-size-control-lg);
  font-size: var(--wx-font-size-lg);
}

.wx-input-number:hover:not(.is-disabled) {
  border-color: var(--wx-border-strong);
}

.wx-input-number.is-focused {
  border-color: var(--wx-border-focus);
}

.wx-input-number--error,
.wx-input-number--error.is-focused {
  border-color: var(--wx-color-danger);
}

.wx-input-number--success {
  border-color: var(--wx-color-success);
}

.wx-input-number--warning {
  border-color: var(--wx-color-warning);
}

.wx-input-number.is-disabled {
  background: var(--wx-bg-disabled);
  color: var(--wx-text-disabled);
}

.wx-input-number__inner {
  flex: 1 1 auto;
  min-width: 0;
  height: 100%;
  padding: 0 var(--wx-space-12);
  background: transparent;
  border: none;
  outline: none;
  color: inherit;
  font-family: inherit;
  font-size: inherit;
  font-variant-numeric: tabular-nums;
  text-align: center;
}

.wx-input-number--controls-none .wx-input-number__inner {
  text-align: left;
}

.wx-input-number__inner::placeholder {
  color: var(--wx-text-placeholder);
}

.wx-input-number__inner:disabled {
  cursor: not-allowed;
}

.wx-input-number__button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  width: calc(var(--wx-size-control-md) - 8px);
  height: 100%;
  padding: 0;
  background: var(--wx-bg-fill);
  border: none;
  color: var(--wx-text-default);
  cursor: pointer;
  transition: background-color var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-input-number__button svg {
  width: 16px;
  height: 16px;
}

.wx-input-number__button:hover:not(:disabled) {
  background: var(--wx-bg-fill-hover);
  color: var(--wx-color-primary);
}

.wx-input-number__button:disabled {
  color: var(--wx-text-disabled);
  cursor: not-allowed;
}

/*
 * Stacked layout: the DOM order stays decrease → input → increase (which is what a
 * screen reader and the tab order want); grid puts the buttons where they belong.
 */
.wx-input-number--controls-right {
  display: grid;
  grid-template-columns: 1fr auto;
  grid-template-rows: 1fr 1fr;
}

.wx-input-number--controls-right .wx-input-number__inner {
  grid-column: 1;
  grid-row: 1 / span 2;
  text-align: left;
}

.wx-input-number--controls-right .wx-input-number__button {
  width: calc(var(--wx-size-control-md) - 16px);
  height: 100%;
  border-left: 1px solid var(--wx-border-default);
}

.wx-input-number--controls-right .wx-input-number__button--increase {
  grid-column: 2;
  grid-row: 1;
}

.wx-input-number--controls-right .wx-input-number__button--decrease {
  grid-column: 2;
  grid-row: 2;
  border-top: 1px solid var(--wx-border-default);
}
</style>
