<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useFormField } from '../../composables/useFormField'
import { useLocalized } from '../../composables/useLocalized'
import LocalePicker from '../Locales/LocalePicker.vue'
import type { TextareaEmits, TextareaModelValue, TextareaProps } from './types'
import { useControlAttrs } from '../../composables/useControlAttrs'

defineOptions({ name: 'WxTextarea', inheritAttrs: false })

/* `class` and `style` belong to the control; the rest belongs to its input. */
const { rootAttrs, controlAttrs } = useControlAttrs()

const props = withDefaults(defineProps<TextareaProps>(), {
  size: undefined,
  status: undefined,
  id: undefined,
  placeholder: undefined,
  disabled: undefined,
  readonly: false,
  rows: 3,
  autosize: false,
  maxlength: undefined,
  showCount: false,
  resize: 'vertical',
  ariaLabel: undefined,
  localized: false,
})

const emit = defineEmits<TextareaEmits>()

const model = defineModel<TextareaModelValue>({ default: '' })

const field = useFormField(props)
const textareaRef = ref<HTMLTextAreaElement | null>(null)
const focused = ref(false)

const locales = useLocalized(props, model)

/** The language on screen, or nothing at all when the field is plain. */
const editing = computed(() => (locales.on.value ? locales.active.value : undefined))

const currentValue = computed(() => locales.read(editing.value))

/* The languages not on screen, carried along — see the same note in `WxInput`. */
const carried = computed(() =>
  locales.on.value
    ? locales.list.value
        .filter((locale) => locale.code !== locales.active.value)
        .map((locale) => ({ code: locale.code, value: locales.read(locale.code) }))
    : [],
)

const counter = computed(() =>
  props.showCount && props.maxlength ? `${currentValue.value.length}/${props.maxlength}` : null,
)

const autosizeOptions = computed(() =>
  props.autosize === true ? {} : props.autosize === false ? null : props.autosize,
)

const classes = computed(() => [
  'wx-textarea',
  `wx-textarea--${field.size.value}`,
  {
    [`wx-textarea--${field.status.value}`]: field.status.value !== 'default',
    'is-focused': focused.value,
    'is-disabled': field.disabled.value,
    'is-readonly': props.readonly,
    'is-autosize': Boolean(autosizeOptions.value),
    'is-localized': locales.on.value,
  },
])

/** Grows the field to fit its content, clamped by minRows / maxRows. */
function syncHeight() {
  const options = autosizeOptions.value
  const el = textareaRef.value
  if (!options || !el) return

  el.style.height = 'auto'
  const styles = getComputedStyle(el)
  const lineHeight = Number.parseFloat(styles.lineHeight) || 20
  const vertical =
    Number.parseFloat(styles.paddingTop) +
    Number.parseFloat(styles.paddingBottom) +
    Number.parseFloat(styles.borderTopWidth) +
    Number.parseFloat(styles.borderBottomWidth)

  let height = el.scrollHeight
  if (options.minRows) height = Math.max(height, options.minRows * lineHeight + vertical)
  if (options.maxRows) height = Math.min(height, options.maxRows * lineHeight + vertical)

  el.style.height = `${height}px`
  el.style.overflowY = options.maxRows && el.scrollHeight > height ? 'auto' : 'hidden'
}

onMounted(() => nextTick(syncHeight))
watch([currentValue, autosizeOptions], () => nextTick(syncHeight))

function onInput(event: Event) {
  const value = (event.target as HTMLTextAreaElement).value
  locales.write(editing.value, value)
  emit('input', value)
}

function onChange(event: Event) {
  emit('change', (event.target as HTMLTextAreaElement).value)
}

function onFocus(event: FocusEvent) {
  focused.value = true
  emit('focus', event)
}

function onBlur(event: FocusEvent) {
  focused.value = false
  emit('blur', event)
}

defineExpose({
  focus: () => textareaRef.value?.focus(),
  blur: () => textareaRef.value?.blur(),
  select: () => textareaRef.value?.select(),
  textarea: textareaRef,
})
</script>

<template>
  <div :class="classes" v-bind="rootAttrs">
    <textarea
      :id="field.id.value"
      ref="textareaRef"
      v-bind="controlAttrs"
      class="wx-textarea__inner"
      :name="locales.nameFor(controlAttrs.name, editing)"
      :value="currentValue"
      :rows="rows"
      :placeholder="placeholder"
      :disabled="field.disabled.value"
      :readonly="readonly"
      :maxlength="maxlength"
      :aria-label="ariaLabel"
      :aria-describedby="field.describedBy.value"
      :aria-invalid="field.status.value === 'error' || undefined"
      :style="{ resize }"
      @input="onInput"
      @change="onChange"
      @focus="onFocus"
      @blur="onBlur"
    />

    <input
      v-for="other in carried"
      :key="other.code"
      type="hidden"
      :name="locales.nameFor(controlAttrs.name, other.code)"
      :value="other.value"
    />

    <span v-if="counter" class="wx-textarea__count">{{ counter }}</span>

    <locale-picker
      v-if="locales.on.value"
      :locales="locales.list.value"
      :active="locales.active.value"
      @choose="(code) => (locales.active.value = code)"
    />
  </div>
</template>

<style scoped>
.wx-textarea {
  position: relative;
  display: block;
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

.wx-textarea:hover:not(.is-disabled) {
  border-color: var(--wx-border-strong);
}

.wx-textarea.is-focused {
  border-color: var(--wx-border-focus);
}

.wx-textarea--error,
.wx-textarea--error.is-focused {
  border-color: var(--wx-color-danger);
}

.wx-textarea--success,
.wx-textarea--success.is-focused {
  border-color: var(--wx-color-success);
}

.wx-textarea--warning,
.wx-textarea--warning.is-focused {
  border-color: var(--wx-color-warning);
}

.wx-textarea.is-disabled {
  background: var(--wx-bg-disabled);
  color: var(--wx-text-disabled);
}

.wx-textarea__inner {
  display: block;
  box-sizing: border-box;
  width: 100%;
  margin: 0;
  padding: var(--wx-space-8) var(--wx-space-12);
  background: transparent;
  border: none;
  outline: none;
  color: inherit;
  font-family: inherit;
  font-size: inherit;
  line-height: var(--wx-font-line-height-normal);
}

.wx-textarea--sm {
  font-size: var(--wx-font-size-control-sm);
}

.wx-textarea--md {
  font-size: var(--wx-font-size-control-md);
}

.wx-textarea--lg {
  font-size: var(--wx-font-size-control-lg);
}

.wx-textarea.is-autosize .wx-textarea__inner {
  resize: none !important;
  overflow-y: hidden;
}

.wx-textarea__inner::placeholder {
  color: var(--wx-text-placeholder);
}

.wx-textarea__inner:disabled {
  cursor: not-allowed;
}

/* Room for the language chip in the corner, so the first line does not run under it. */
.wx-textarea.is-localized .wx-textarea__inner {
  padding-right: var(--wx-space-40);
}

.wx-textarea__count {
  position: absolute;
  right: var(--wx-space-8);
  bottom: var(--wx-space-4);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  font-variant-numeric: tabular-nums;
  pointer-events: none;
}
</style>
