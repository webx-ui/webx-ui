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
  tokenSegments,
  tokenText,
  useTokenMenu,
  type TokenOption,
  type TokenRange,
} from '../../composables/useTokens'
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
  localized: false,
  tokens: undefined,
  tokensTitle: 'Placeholders',
  tokensLabel: 'Insert a placeholder',
})

const emit = defineEmits<InputEmits>()

const model = defineModel<InputModelValue>({ default: '' })

const field = useFormField(props)
const inputRef = ref<HTMLInputElement | null>(null)
const focused = ref(false)

const locales = useLocalized(props, model)

/** The language on screen, or nothing at all when the field is plain. */
const editing = computed(() => (locales.on.value ? locales.active.value : undefined))

const currentValue = computed(() => locales.read(editing.value))

/**
 * The languages not on screen, carried along as hidden inputs.
 *
 * One visible control and the rest hidden, rather than one styled box per language: the box is
 * this component's root, and a component's root is what a parent's scoped CSS is stamped on and
 * what `class` lands on. They are here at all because a control that is not in the DOM is one a
 * classic form post leaves out, and saving would wipe every language nobody was looking at.
 */
const carried = computed(() =>
  locales.on.value
    ? locales.list.value
        .filter((locale) => locale.code !== locales.active.value)
        .map((locale) => ({ code: locale.code, value: locales.read(locale.code) }))
    : [],
)

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
    'is-localized': locales.on.value,
    'is-tokenized': tokenized.value,
  },
])

const rootRef = ref<HTMLElement | null>(null)
const mirrorRef = ref<HTMLElement | null>(null)

const tokens = useTokenMenu(() => props.tokens)
const hasTokens = computed(() => tokens.all.value.length > 0)
const listId = computed(() => `${field.id.value}-tokens`)

/**
 * The text again, in a layer over the input, with the known placeholders wrapped as chips.
 *
 * The input cannot draw a chip — it holds one string in one colour — so while there is a chip to
 * draw the input's own letters go transparent and the mirror's show instead, at the same places:
 * same font, same box, same horizontal scroll. The input stays the control: caret, selection,
 * input methods and the value are all still its own, and the value is still `[name]`.
 */
const segments = computed(() =>
  hasTokens.value ? tokenSegments(String(currentValue.value), tokens.known.value) : [],
)
const tokenized = computed(() => segments.value.some((segment) => segment.token !== null))

function syncMirror(): void {
  const input = inputRef.value
  const mirror = mirrorRef.value
  if (!input || !mirror) return

  mirror.style.left = `${input.offsetLeft}px`
  mirror.style.top = `${input.offsetTop}px`
  mirror.style.width = `${input.offsetWidth}px`
  mirror.style.height = `${input.offsetHeight}px`
  mirror.scrollLeft = input.scrollLeft
}

/* The input's width moves with the panel, and the mirror has to move with it. */
let resizing: ResizeObserver | null = null

onMounted(() => {
  void nextTick(syncMirror)
  if (typeof ResizeObserver !== 'undefined' && inputRef.value) {
    resizing = new ResizeObserver(() => syncMirror())
    resizing.observe(inputRef.value)
  }
})
onBeforeUnmount(() => resizing?.disconnect())
watch(segments, () => void nextTick(syncMirror))

function caretAnchor(offset: number) {
  return fieldAnchor(rootRef.value, mirrorRef.value ? textRect(mirrorRef.value, offset) : null)
}

/** After the text or the caret moved: open, narrow or close the list by what is before it. */
function suggestTokens(): void {
  const input = inputRef.value
  if (!hasTokens.value || !input) return

  const caret = input.selectionStart ?? input.value.length
  if (caret !== input.selectionEnd) return tokens.close()

  const before = input.value.slice(0, caret)
  tokens.suggest(before, caret, () => caretAnchor(before.lastIndexOf('[')))
}

function insertToken(token: TokenOption, at: TokenRange): void {
  const input = inputRef.value
  const value = String(currentValue.value)
  const text = tokenText(token)
  const next = value.slice(0, at.from) + text + value.slice(at.to)

  locales.write(editing.value, next)
  emit('input', next)

  if (!input) return

  // Written straight away rather than on the next render, so the caret lands after the chip.
  input.value = next
  input.focus()
  input.setSelectionRange(at.from + text.length, at.from + text.length)
  void nextTick(syncMirror)
}

/** The help button: every placeholder, going where the caret is — or at the end, if nowhere. */
function browseTokens(): void {
  const input = inputRef.value
  if (!input) return
  if (tokens.open.value && tokens.browsing.value) return tokens.close()

  const length = input.value.length
  const had = document.activeElement === input

  input.focus()
  if (!had) input.setSelectionRange(length, length)

  const from = had ? (input.selectionStart ?? length) : length
  const to = had ? (input.selectionEnd ?? from) : length

  tokens.browse({ from, to }, fieldAnchor(rootRef.value, null))
}

function onKeydown(event: KeyboardEvent): void {
  tokens.keydown(event, insertToken)
}

/* The caret moved without typing: the list follows it, or goes. Only an open list cares. */
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
  const value = (event.target as HTMLInputElement).value
  locales.write(editing.value, value)
  emit('input', value)
  suggestTokens()
  syncMirror()
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
  tokens.close()
  emit('blur', event)
}

function clear() {
  locales.write(editing.value, '')
  emit('input', '')
  emit('change', '')
  emit('clear')
  inputRef.value?.focus()
}

/**
 * Switching the language is done to type in it, so the caret goes straight into the field
 * that was switched — this one, not every localized field on the screen.
 */
function choose(code: string): void {
  locales.active.value = code
  void nextTick(() => inputRef.value?.focus())
}

defineExpose({
  focus: () => inputRef.value?.focus(),
  blur: () => inputRef.value?.blur(),
  select: () => inputRef.value?.select(),
  input: inputRef,
})
</script>

<template>
  <div ref="rootRef" :class="classes" v-bind="rootAttrs">
    <span v-if="$slots.prefix" class="wx-input__affix wx-input__affix--prefix">
      <slot name="prefix" />
    </span>

    <input
      :id="field.id.value"
      ref="inputRef"
      v-bind="controlAttrs"
      class="wx-input__inner"
      :type="type"
      :name="locales.nameFor(controlAttrs.name, editing)"
      :value="currentValue"
      :placeholder="placeholder"
      :disabled="field.disabled.value"
      :readonly="readonly"
      :maxlength="maxlength"
      :autocomplete="autocomplete"
      :aria-label="ariaLabel"
      :aria-describedby="field.describedBy.value"
      :aria-invalid="field.status.value === 'error' || undefined"
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

    <span
      v-if="hasTokens"
      ref="mirrorRef"
      class="wx-input__mirror"
      :class="{ 'is-shown': tokenized }"
      aria-hidden="true"
      ><span class="wx-input__mirror-text"
        ><template v-for="(segment, index) in segments" :key="index"
          ><span v-if="segment.token" class="wx-token">{{ segment.text }}</span
          ><template v-else>{{ segment.text }}</template></template
        ></span
      ></span
    >

    <input
      v-for="other in carried"
      :key="other.code"
      type="hidden"
      :name="locales.nameFor(controlAttrs.name, other.code)"
      :value="other.value"
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

    <wx-token-button
      v-if="hasTokens"
      :label="tokensLabel"
      :disabled="field.disabled.value || readonly"
      @click="browseTokens"
    />

    <span v-if="counter" class="wx-input__count">{{ counter }}</span>

    <span v-if="$slots.suffix" class="wx-input__affix wx-input__affix--suffix">
      <slot name="suffix" />
    </span>

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
.wx-input {
  position: relative;
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

/* Room for the language chip in the corner, so a long title does not run under it. */
.wx-input.is-localized {
  padding-right: var(--wx-space-40);
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

/*
 * A field the browser filled in is painted by the browser, in a colour that belongs to no
 * theme — a yellow block in a light panel and an olive one in a dark panel. The background is
 * set with !important in the user agent stylesheet and cannot be overridden, so it is covered
 * instead: an inset shadow the size of the field paints over it.
 *
 * The absurd transition is the companion trick for Chrome, which repaints its tint on focus.
 */
.wx-input__inner:-webkit-autofill,
.wx-input__inner:-webkit-autofill:hover,
.wx-input__inner:-webkit-autofill:focus,
.wx-input__inner:autofill {
  box-shadow: 0 0 0 100vmax var(--wx-bg-surface) inset;
  -webkit-text-fill-color: var(--wx-text-default);
  caret-color: var(--wx-text-default);
  transition: background-color 100000s ease-in-out 0s;
}

.wx-input__inner:disabled {
  cursor: not-allowed;
}

/*
 * The mirror is placed over the input by measurement (see `syncMirror`) and always there while
 * the field has placeholders — transparent until it has a chip to draw, because it is also what
 * the caret's column is measured on.
 */
.wx-input__mirror {
  position: absolute;
  display: flex;
  align-items: center;
  box-sizing: border-box;
  overflow: hidden;
  color: transparent;
  font: inherit;
  letter-spacing: inherit;
  white-space: pre;
  pointer-events: none;
}

/* One run of inline text, so the chips sit in the line instead of becoming flex items. */
.wx-input__mirror-text {
  flex: 0 0 auto;
}

.wx-input__mirror.is-shown {
  color: inherit;
}

/* Only the letters go: the caret, the selection and the placeholder are still the input's. */
.wx-input.is-tokenized .wx-input__inner {
  color: transparent;
  caret-color: var(--wx-text-default);
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
