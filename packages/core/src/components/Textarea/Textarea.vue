<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useFormField } from '../../composables/useFormField'
import { useLocalized } from '../../composables/useLocalized'
import LocalePicker from '../Locales/LocalePicker.vue'
import WxTokenButton from '../../internal/TokenButton.vue'
import WxTokenMenu from '../../internal/TokenMenu.vue'
import {
  fieldAnchor,
  textRect,
  tokenAnchor,
  tokenSegments,
  tokenText,
  useTokenMenu,
  type TokenOption,
  type TokenRange,
} from '../../composables/useTokens'
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
  tokens: undefined,
  tokensTitle: 'Placeholders',
  tokensLabel: 'Insert a placeholder',
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
    'has-tokens': hasTokens.value,
    'is-tokenized': tokenized.value,
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

const rootRef = ref<HTMLElement | null>(null)
const mirrorRef = ref<HTMLElement | null>(null)

const tokens = useTokenMenu(() => props.tokens)
const hasTokens = computed(() => tokens.all.value.length > 0)
const listId = computed(() => `${field.id.value}-tokens`)

/*
 * The text again, behind the same box, with the placeholders as chips — the same mirror as in
 * `WxInput`, wrapped instead of scrolled sideways. The zero-width space at its end gives a
 * trailing newline the empty line the textarea shows for it, so both scroll the same height.
 */
const segments = computed(() =>
  hasTokens.value ? tokenSegments(String(currentValue.value), tokens.known.value) : [],
)
const tokenized = computed(() => segments.value.some((segment) => segment.token !== null))

function syncMirror(): void {
  const textarea = textareaRef.value
  const mirror = mirrorRef.value
  if (!textarea || !mirror) return

  // The client box rather than the border box: a scrollbar takes width from the text, and the
  // mirror has to wrap where the textarea wraps.
  mirror.style.width = `${textarea.clientWidth}px`
  mirror.style.height = `${textarea.clientHeight}px`
  mirror.scrollTop = textarea.scrollTop
  mirror.scrollLeft = textarea.scrollLeft
}

/* Dragged by its corner, grown by autosize, or narrowed with the panel. */
let resizing: ResizeObserver | null = null

onMounted(() => {
  void nextTick(syncMirror)
  if (typeof ResizeObserver !== 'undefined' && textareaRef.value) {
    resizing = new ResizeObserver(() => syncMirror())
    resizing.observe(textareaRef.value)
  }
})
onBeforeUnmount(() => resizing?.disconnect())
watch(segments, () => void nextTick(syncMirror))

/** Under the line being typed, at the bracket; under the field where nothing can be measured. */
function caretAnchor(offset: number) {
  const rect = mirrorRef.value ? textRect(mirrorRef.value, offset) : null

  return rect ? tokenAnchor(rect, rootRef.value) : fieldAnchor(rootRef.value, null)
}

function suggestTokens(): void {
  const textarea = textareaRef.value
  if (!hasTokens.value || !textarea) return

  const caret = textarea.selectionStart ?? textarea.value.length
  if (caret !== textarea.selectionEnd) return tokens.close()

  const before = textarea.value.slice(0, caret)
  tokens.suggest(before, caret, () => caretAnchor(before.lastIndexOf('[')))
}

function insertToken(token: TokenOption, at: TokenRange): void {
  const textarea = textareaRef.value
  const value = String(currentValue.value)
  const text = tokenText(token)
  const next = value.slice(0, at.from) + text + value.slice(at.to)

  locales.write(editing.value, next)
  emit('input', next)

  if (!textarea) return

  textarea.value = next
  textarea.focus()
  textarea.setSelectionRange(at.from + text.length, at.from + text.length)
  void nextTick(syncMirror)
}

/** The help button: every placeholder, going where the caret is — or at the end, if nowhere. */
function browseTokens(): void {
  const textarea = textareaRef.value
  if (!textarea) return
  if (tokens.open.value && tokens.browsing.value) return tokens.close()

  const length = textarea.value.length
  const had = document.activeElement === textarea

  textarea.focus()
  if (!had) textarea.setSelectionRange(length, length)

  const from = had ? (textarea.selectionStart ?? length) : length
  const to = had ? (textarea.selectionEnd ?? from) : length

  tokens.browse({ from, to }, caretAnchor(from))
}

function onKeydown(event: KeyboardEvent): void {
  tokens.keydown(event, insertToken)
}

/* The caret moved without typing: an open list follows it, or goes. */
function onCaretMove(event: Event): void {
  syncMirror()
  if (!tokens.open.value) return
  if (
    event instanceof KeyboardEvent &&
    !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)
  )
    return
  suggestTokens()
}

function onInput(event: Event) {
  const value = (event.target as HTMLTextAreaElement).value
  locales.write(editing.value, value)
  emit('input', value)
  suggestTokens()
  syncMirror()
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
  tokens.close()
  emit('blur', event)
}

/** Same as the input: the caret follows the language into this field. */
function choose(code: string): void {
  locales.active.value = code
  void nextTick(() => textareaRef.value?.focus())
}

defineExpose({
  focus: () => textareaRef.value?.focus(),
  blur: () => textareaRef.value?.blur(),
  select: () => textareaRef.value?.select(),
  textarea: textareaRef,
})
</script>

<template>
  <div ref="rootRef" :class="classes" v-bind="rootAttrs">
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
      :aria-autocomplete="hasTokens ? 'list' : undefined"
      :aria-controls="tokens.open.value ? listId : undefined"
      :aria-activedescendant="tokens.open.value ? `${listId}-${tokens.active.value}` : undefined"
      @input="onInput"
      @change="onChange"
      @focus="onFocus"
      @blur="onBlur"
      @keydown="onKeydown"
      @keyup="onCaretMove"
      @click="onCaretMove"
      @scroll="syncMirror"
    />

    <div
      v-if="hasTokens"
      ref="mirrorRef"
      class="wx-textarea__mirror"
      :class="{ 'is-shown': tokenized }"
      aria-hidden="true"
    >
      <template v-for="(segment, index) in segments" :key="index"
        ><span v-if="segment.token" class="wx-token">{{ segment.text }}</span
        ><template v-else>{{ segment.text }}</template></template
      >&#8203;
    </div>

    <input
      v-for="other in carried"
      :key="other.code"
      type="hidden"
      :name="locales.nameFor(controlAttrs.name, other.code)"
      :value="other.value"
    />

    <span v-if="counter" class="wx-textarea__count">{{ counter }}</span>

    <wx-token-button
      v-if="hasTokens"
      :label="tokensLabel"
      :disabled="field.disabled.value || readonly"
      @click="browseTokens"
    />

    <wx-token-menu
      v-if="hasTokens"
      :open="tokens.open.value"
      :items="tokens.items.value"
      :active="tokens.active.value"
      :anchor="tokens.anchor.value"
      :list-id="listId"
      :title="tokens.browsing.value ? tokensTitle : undefined"
      :owner="rootRef"
      @choose="(token) => tokens.choose(token, insertToken)"
      @hover="(index) => (tokens.active.value = index)"
      @close="tokens.close()"
    />

    <locale-picker
      v-if="locales.on.value"
      :locales="locales.list.value"
      :active="locales.active.value"
      @choose="choose"
    />
  </div>
</template>

<style scoped>
.wx-textarea {
  /* Tall, so the chip is pinned near the top rather than centred, and a size smaller. */
  --wx-locale-picker-height: 20px;
  --wx-locale-picker-top: var(--wx-space-6);
  --wx-locale-picker-shift: 0;

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

/*
 * Over the textarea, in its box and with its padding, so every letter lands where the textarea
 * draws it; the size and the scroll are copied over in `syncMirror`. Transparent until there is a
 * chip to draw — it is also what the caret's place is measured on.
 */
.wx-textarea__mirror {
  position: absolute;
  top: 0;
  left: 0;
  box-sizing: border-box;
  padding: var(--wx-space-8) var(--wx-space-12);
  overflow: hidden;
  color: transparent;
  font: inherit;
  line-height: var(--wx-font-line-height-normal);
  letter-spacing: inherit;
  white-space: pre-wrap;
  overflow-wrap: break-word;
  pointer-events: none;
}

.wx-textarea__mirror.is-shown {
  color: inherit;
}

/* Only the letters go: the caret, the selection and the placeholder are still the textarea's. */
.wx-textarea.is-tokenized .wx-textarea__inner {
  color: transparent;
  caret-color: var(--wx-text-default);
}

/* The help button takes the top corner, beside the language chip when there is one. */
.wx-textarea .wx-token-button {
  position: absolute;
  top: var(--wx-space-6);
  right: var(--wx-space-8);
}

.wx-textarea.is-localized .wx-token-button {
  right: var(--wx-space-40);
}

.wx-textarea.is-localized .wx-textarea__mirror {
  padding-right: var(--wx-space-40);
}

.wx-textarea.has-tokens .wx-textarea__inner,
.wx-textarea.has-tokens .wx-textarea__mirror {
  padding-right: var(--wx-space-40);
}

.wx-textarea.has-tokens.is-localized .wx-textarea__inner,
.wx-textarea.has-tokens.is-localized .wx-textarea__mirror {
  padding-right: var(--wx-space-64);
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
