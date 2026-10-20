<script setup lang="ts" generic="T extends CodeEditorModelValue = string">
import { computed, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue'
import { Compartment, EditorState, Prec, type Extension } from '@codemirror/state'
import {
  EditorView,
  highlightActiveLine,
  highlightActiveLineGutter,
  highlightSpecialChars,
  keymap,
  lineNumbers,
  placeholder as placeholderExtension,
} from '@codemirror/view'
import {
  bracketMatching,
  foldGutter,
  foldKeymap,
  indentOnInput,
  indentUnit,
  syntaxHighlighting,
} from '@codemirror/language'
import { defaultKeymap, history, historyKeymap, indentWithTab } from '@codemirror/commands'
import {
  autocompletion,
  closeBrackets,
  closeBracketsKeymap,
  snippet,
  type Completion,
  type CompletionContext,
  type CompletionResult,
} from '@codemirror/autocomplete'
import { highlightSelectionMatches, searchKeymap } from '@codemirror/search'
import { linter, type Diagnostic } from '@codemirror/lint'
import { useFormField } from '../../composables/useFormField'
import { useLocalized } from '../../composables/useLocalized'
import LocalePicker from '../Locales/LocalePicker.vue'
import { LANGUAGES } from './languages'
import { highlightStyle } from './highlight'
import type {
  CodeEditorCompletion,
  CodeEditorCompletionResult,
  CodeEditorDiagnostic,
  CodeEditorEmits,
  CodeEditorModelValue,
  CodeEditorProps,
} from './types'

defineOptions({ name: 'WxCodeEditor', inheritAttrs: false })

const props = withDefaults(defineProps<CodeEditorProps>(), {
  language: 'plain',
  placeholder: undefined,
  disabled: undefined,
  readonly: false,
  size: undefined,
  status: undefined,
  id: undefined,
  ariaLabel: undefined,
  minHeight: '160px',
  maxHeight: undefined,
  lineNumbers: true,
  lineWrapping: false,
  tabSize: 2,
  lint: true,
  completions: undefined,
  localized: false,
  singleLine: false,
  extensions: () => [],
})

const emit = defineEmits<CodeEditorEmits>()

/*
 * Generic so that a plain editor stays a `string` to whoever binds it, and a localized one is the
 * map of languages it edits.
 */
const model = defineModel<T>({ default: '' as never })

const field = useFormField(props)
const locales = useLocalized(props, model)

/** The language on screen, or nothing at all when the field is plain. */
const editing = computed(() => (locales.on.value ? locales.active.value : undefined))

/** The text of the language on screen: what the editor holds. */
const current = computed(() => locales.read(editing.value))
const host = ref<HTMLElement | null>(null)
const view = shallowRef<EditorView | null>(null)
const focused = ref(false)

const editable = computed(() => !field.disabled.value && !props.readonly)

/**
 * Everything that can change after mount lives in a compartment, so a prop change
 * is a reconfigure of that slot rather than a rebuild of the whole editor.
 */
const compartments = {
  language: new Compartment(),
  lint: new Compartment(),
  editable: new Compartment(),
  gutter: new Compartment(),
  wrapping: new Compartment(),
  indent: new Compartment(),
  placeholder: new Compartment(),
  attributes: new Compartment(),
  completion: new Compartment(),
  keys: new Compartment(),
  line: new Compartment(),
  extra: new Compartment(),
}

function languageExtension(): Extension {
  return LANGUAGES[props.language].extension()
}

function lintExtension(): Extension {
  const source = LANGUAGES[props.language].linter
  if (!props.lint || !source) return []
  const run = source()
  // Wrapping the source is the only hook for reporting results outward: the
  // diagnostics themselves stay inside the editor's own state.
  return linter(
    async (instance) => {
      const found = (await run(instance)).map((diagnostic) => widen(diagnostic, instance.state))
      emit('lint', found.map(toDiagnostic))
      return found
    },
    { delay: 300 },
  )
}

/**
 * A parser reports "unexpected token at 23" as an empty range, which CodeMirror
 * draws as a dot nobody notices. Underline from there to the end of the line instead.
 */
function widen(diagnostic: Diagnostic, state: EditorState): Diagnostic {
  if (diagnostic.from !== diagnostic.to) return diagnostic
  const line = state.doc.lineAt(diagnostic.from)
  if (diagnostic.from < line.to) return { ...diagnostic, to: line.to }
  if (diagnostic.from > line.from) return { ...diagnostic, from: line.from }
  return diagnostic
}

function toDiagnostic(diagnostic: Diagnostic): CodeEditorDiagnostic {
  return {
    from: diagnostic.from,
    to: diagnostic.to,
    severity: diagnostic.severity,
    message: diagnostic.message,
  }
}

/**
 * A suggestion as CodeMirror takes it: an `apply` with `${}` in it is a snippet. `extra` is how
 * much past the caret it replaces — the `}}` closing brackets typed after it.
 */
function toCompletion(option: CodeEditorCompletion, extra: number): Completion {
  const given = option.apply ?? option.label
  const write = given.includes('${') ? snippet(given) : null

  return {
    label: option.label,
    detail: option.detail,
    info: option.info,
    type: option.type,
    boost: option.boost,
    apply:
      extra === 0 && write === null
        ? option.apply
        : (view, completion, from, to) => {
            const end = Math.min(view.state.doc.length, to + extra)

            if (write !== null) {
              write(view, completion, from, end)

              return
            }

            view.dispatch({
              changes: { from, to: end, insert: given },
              selection: { anchor: from + given.length },
              userEvent: 'input.complete',
            })
          },
  }
}

/*
 * CodeMirror matches what was typed against the text from `from` to `to`, so a result that
 * reaches past the caret would be matched against that tail and offer nothing. It is told only
 * where the word starts; the tail is replaced when a suggestion is taken.
 */
function toResult(result: CodeEditorCompletionResult, pos: number): CompletionResult {
  const extra = Math.max(0, (result.to ?? pos) - pos)

  return { from: result.from, options: result.options.map((option) => toCompletion(option, extra)) }
}

/**
 * The completions prop on CodeMirror's own completion: a list completes the word before the
 * caret, a function is asked about every place and says where its suggestions belong.
 */
function completionExtension(): Extension {
  const given = props.completions

  if (given === undefined) return []

  const source = (context: CompletionContext): CompletionResult | null => {
    if (typeof given === 'function') {
      const result = given({
        text: context.state.doc.toString(),
        pos: context.pos,
        explicit: context.explicit,
      })

      return result === null ? null : toResult(result, context.pos)
    }

    const word = context.matchBefore(/[\w.]*/)

    if (word === null || (word.from === word.to && !context.explicit)) return null

    return toResult({ from: word.from, options: given }, context.pos)
  }

  return autocompletion({ override: [source], icons: false })
}

/*
 * Tab indents a document; in a one-line field it moves on to the next field, as in any input.
 */
function keysExtension(): Extension {
  return keymap.of([
    ...closeBracketsKeymap,
    ...defaultKeymap,
    ...searchKeymap,
    ...historyKeymap,
    ...foldKeymap,
    ...(props.singleLine ? [] : [indentWithTab]),
  ])
}

/**
 * One line, like an input: Enter takes a suggestion when the list is open (its keys come first)
 * and does nothing otherwise, and a line break typed, pasted or dropped in becomes a space.
 */
function lineExtension(): Extension {
  if (!props.singleLine) return []

  const nothing = () => true

  return [
    Prec.high(
      keymap.of([
        { key: 'Enter', run: nothing },
        { key: 'Shift-Enter', run: nothing },
        { key: 'Mod-Enter', run: nothing },
      ]),
    ),
    EditorState.transactionFilter.of((transaction) => {
      if (!transaction.docChanged || transaction.newDoc.lines === 1) return transaction

      const changes: { from: number; to: number; insert: string }[] = []

      transaction.changes.iterChanges((from, to, _fromB, _toB, inserted) => {
        changes.push({ from, to, insert: inserted.toString().replace(/\n/g, ' ') })
      })

      // A line break is one character, as is the space: the selection stays where it was.
      return {
        changes,
        selection: transaction.selection,
        effects: transaction.effects,
        scrollIntoView: transaction.scrollIntoView,
      }
    }),
  ]
}

function editableExtension(): Extension {
  const enabled = editable.value
  return [EditorState.readOnly.of(!enabled), EditorView.editable.of(enabled)]
}

function gutterExtension(): Extension {
  if (!props.lineNumbers) return []
  return [lineNumbers(), highlightActiveLineGutter(), foldGutter()]
}

function wrappingExtension(): Extension {
  return props.lineWrapping ? EditorView.lineWrapping : []
}

function indentExtension(): Extension {
  return [EditorState.tabSize.of(props.tabSize), indentUnit.of(' '.repeat(props.tabSize))]
}

function placeholderExtensionFor(): Extension {
  return props.placeholder ? placeholderExtension(props.placeholder) : []
}

function attributesExtension(): Extension {
  const attributes: Record<string, string> = { id: field.id.value }
  if (props.ariaLabel) attributes['aria-label'] = props.ariaLabel
  if (field.describedBy.value) attributes['aria-describedby'] = field.describedBy.value
  if (field.status.value === 'error') attributes['aria-invalid'] = 'true'
  // A disabled editor is skipped by Tab; a read-only one can still be scrolled into.
  if (field.disabled.value) {
    attributes.tabindex = '-1'
    attributes['aria-disabled'] = 'true'
  }
  return EditorView.contentAttributes.of(attributes)
}

function buildState(doc: string): EditorState {
  return EditorState.create({
    doc,
    extensions: [
      highlightSpecialChars(),
      history(),
      bracketMatching(),
      closeBrackets(),
      indentOnInput(),
      highlightActiveLine(),
      highlightSelectionMatches(),
      syntaxHighlighting(highlightStyle),
      compartments.keys.of(keysExtension()),
      compartments.line.of(lineExtension()),
      compartments.language.of(languageExtension()),
      compartments.lint.of(lintExtension()),
      compartments.editable.of(editableExtension()),
      compartments.gutter.of(gutterExtension()),
      compartments.wrapping.of(wrappingExtension()),
      compartments.indent.of(indentExtension()),
      compartments.placeholder.of(placeholderExtensionFor()),
      compartments.attributes.of(attributesExtension()),
      compartments.completion.of(completionExtension()),
      compartments.extra.of(props.extensions),
      EditorView.updateListener.of((update) => {
        if (!update.docChanged || replacing) return
        const value = update.state.doc.toString()
        locales.write(editing.value, value)
        emit('change', value)
      }),
      EditorView.domEventHandlers({
        focus: () => {
          focused.value = true
          emit('focus')
        },
        blur: () => {
          focused.value = false
          emit('blur')
        },
      }),
    ],
  })
}

onMounted(() => {
  if (!host.value) return
  view.value = new EditorView({ state: buildState(current.value), parent: host.value })
})

onBeforeUnmount(() => {
  view.value?.destroy()
  view.value = null
})

/*
 * Set while the document is swapped for another language's: that is not an edit of this one, and
 * written back it would copy one language's text into the other.
 */
let replacing = false

/** Replaces the document only when the value really differs, or the caret jumps. */
function follow(next: string, language: boolean): void {
  const instance = view.value
  if (!instance) return
  const shown = instance.state.doc.toString()
  if (next === shown) return
  replacing = language
  instance.dispatch({ changes: { from: 0, to: shown.length, insert: next } })
  replacing = false
}

watch(current, (next) => follow(next, false))
watch(editing, () => follow(current.value, true))

function reconfigure(compartment: Compartment, extension: Extension) {
  view.value?.dispatch({ effects: compartment.reconfigure(extension) })
}

watch(
  () => props.language,
  () => {
    reconfigure(compartments.language, languageExtension())
    reconfigure(compartments.lint, lintExtension())
  },
)
watch(
  () => props.lint,
  () => reconfigure(compartments.lint, lintExtension()),
)
watch(editable, () => reconfigure(compartments.editable, editableExtension()))
watch(
  () => props.lineNumbers,
  () => reconfigure(compartments.gutter, gutterExtension()),
)
watch(
  () => props.lineWrapping,
  () => reconfigure(compartments.wrapping, wrappingExtension()),
)
watch(
  () => props.tabSize,
  () => reconfigure(compartments.indent, indentExtension()),
)
watch(
  () => props.placeholder,
  () => reconfigure(compartments.placeholder, placeholderExtensionFor()),
)
watch([() => props.ariaLabel, field.id, field.describedBy, field.status, field.disabled], () =>
  reconfigure(compartments.attributes, attributesExtension()),
)
watch(
  () => props.completions,
  () => reconfigure(compartments.completion, completionExtension()),
)
watch(
  () => props.singleLine,
  () => {
    reconfigure(compartments.keys, keysExtension())
    reconfigure(compartments.line, lineExtension())
  },
)
watch(
  () => props.extensions,
  (extensions) => reconfigure(compartments.extra, extensions),
)

function format(): boolean {
  const instance = view.value
  if (!instance || props.language !== 'json' || !editable.value) return false
  const current = instance.state.doc.toString()
  let parsed: unknown
  try {
    parsed = JSON.parse(current)
  } catch {
    return false
  }
  const next = JSON.stringify(parsed, null, props.tabSize)
  if (next !== current) {
    instance.dispatch({ changes: { from: 0, to: current.length, insert: next } })
  }
  return true
}

const classes = computed(() => [
  'wx-code-editor',
  `wx-code-editor--${field.size.value}`,
  {
    [`wx-code-editor--${field.status.value}`]: field.status.value !== 'default',
    'is-focused': focused.value,
    'is-disabled': field.disabled.value,
    'is-readonly': props.readonly,
    'is-localized': locales.on.value,
    'is-single-line': props.singleLine,
  },
])

const style = computed(() => ({
  '--wx-code-editor-min-height': props.minHeight,
  '--wx-code-editor-max-height': props.maxHeight ?? 'none',
}))

/** Same as the other text fields: the caret follows the language into this one. */
function choose(code: string): void {
  locales.active.value = code
  view.value?.focus()
}

defineExpose({
  view,
  focus: () => view.value?.focus(),
  format,
})
</script>

<template>
  <div :class="classes" :style="style">
    <div ref="host" class="wx-code-editor__host" />

    <locale-picker
      v-if="locales.on.value"
      :locales="locales.list.value"
      :active="locales.active.value"
      @choose="choose"
    />
  </div>
</template>

<style scoped>
.wx-code-editor {
  /* Tall like a textarea: the chip is pinned near the top, a size smaller. */
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

.wx-code-editor:hover:not(.is-disabled) {
  border-color: var(--wx-border-strong);
}

.wx-code-editor.is-focused {
  border-color: var(--wx-border-focus);
}

.wx-code-editor--error,
.wx-code-editor--error.is-focused {
  border-color: var(--wx-color-danger);
}

.wx-code-editor--success,
.wx-code-editor--success.is-focused {
  border-color: var(--wx-color-success);
}

.wx-code-editor--warning,
.wx-code-editor--warning.is-focused {
  border-color: var(--wx-color-warning);
}

.wx-code-editor.is-disabled {
  background: var(--wx-bg-disabled);
  color: var(--wx-text-disabled);
}

.wx-code-editor--sm {
  font-size: var(--wx-font-size-control-sm);
}

.wx-code-editor--md {
  font-size: var(--wx-font-size-control-md);
}

.wx-code-editor--lg {
  font-size: var(--wx-font-size-control-lg);
}

/*
 * CodeMirror mounts its own tree under the host, so everything below reaches it
 * through :deep(). The editor's base theme is left in place for layout and
 * overridden only where it paints a colour.
 */
.wx-code-editor :deep(.cm-editor) {
  min-height: var(--wx-code-editor-min-height);
  max-height: var(--wx-code-editor-max-height);
  border-radius: inherit;
  color: inherit;
}

.wx-code-editor :deep(.cm-editor.cm-focused) {
  outline: none;
}

.wx-code-editor :deep(.cm-scroller) {
  font-family: var(--wx-font-family-mono);
  font-size: inherit;
  line-height: var(--wx-font-line-height-normal);
  border-radius: inherit;
}

.wx-code-editor :deep(.cm-content) {
  padding: var(--wx-space-8) 0;
  caret-color: var(--wx-text-default);
}

.wx-code-editor :deep(.cm-line) {
  padding: 0 var(--wx-space-12);
}

/*
 * One line: as tall as an input of the same size, the line in the middle, a long one scrolled
 * sideways under the caret rather than wrapped.
 */
.wx-code-editor.is-single-line {
  --wx-locale-picker-height: 24px;
  --wx-locale-picker-top: 50%;
  --wx-locale-picker-shift: calc(-1 * (var(--wx-locale-picker-height) / 2 + 3px));
}

.wx-code-editor.is-single-line :deep(.cm-editor) {
  min-height: 0;
  height: calc(var(--wx-size-control-md) - 2px);
}

.wx-code-editor--sm.is-single-line :deep(.cm-editor) {
  height: calc(var(--wx-size-control-sm) - 2px);
}

.wx-code-editor--lg.is-single-line :deep(.cm-editor) {
  height: calc(var(--wx-size-control-lg) - 2px);
}

.wx-code-editor.is-single-line :deep(.cm-scroller) {
  overflow-y: hidden;
  scrollbar-width: none;
}

/* CodeMirror pins the scroller's children to the top with !important; the line centres itself. */
.wx-code-editor.is-single-line :deep(.cm-content) {
  align-self: center;
  min-height: 0;
  padding: 0;
}

.wx-code-editor.is-single-line :deep(.cm-activeLine) {
  background: transparent;
}

/* Room for the language chip, so the end of a long line is never under it. */
.wx-code-editor.is-localized :deep(.cm-content) {
  padding-right: var(--wx-space-48);
}

/*
 * Mustache tags (`language="mustache"`): marks over the language underneath, in the palette of
 * the rest of the code — a value in the primary colour, a section bold, a comment muted.
 */
.wx-code-editor :deep(.wx-cm-mustache) {
  color: var(--wx-color-primary-active);
}

.wx-code-editor :deep(.wx-cm-mustache--section) {
  color: var(--wx-color-warning-active);
  font-weight: 600;
}

.wx-code-editor :deep(.wx-cm-mustache--comment) {
  color: var(--wx-text-muted);
  font-style: italic;
}

.wx-code-editor :deep(.wx-cm-mustache--invalid) {
  color: var(--wx-color-danger);
  text-decoration: underline wavy var(--wx-color-danger);
  text-underline-offset: 3px;
}

/* A mark inside a mark keeps the tag's colour over the HTML highlighter's. */
.wx-code-editor :deep(.wx-cm-mustache span) {
  color: inherit;
}

.wx-code-editor :deep(.cm-cursor) {
  border-left-color: var(--wx-text-default);
}

.wx-code-editor :deep(.cm-placeholder) {
  color: var(--wx-text-placeholder);
}

.wx-code-editor :deep(.cm-gutters) {
  background: var(--wx-bg-subtle);
  border-right: 1px solid var(--wx-border-muted);
  color: var(--wx-text-muted);
}

.wx-code-editor :deep(.cm-lineNumbers .cm-gutterElement) {
  padding: 0 var(--wx-space-8) 0 var(--wx-space-12);
  min-width: 0;
}

.wx-code-editor :deep(.cm-foldGutter .cm-gutterElement) {
  padding: 0 var(--wx-space-4);
  cursor: pointer;
}

.wx-code-editor :deep(.cm-activeLine) {
  background: var(--wx-bg-muted);
}

.wx-code-editor :deep(.cm-activeLineGutter) {
  background: var(--wx-bg-muted);
  color: var(--wx-text-default);
}

.wx-code-editor.is-disabled :deep(.cm-activeLine),
.wx-code-editor.is-disabled :deep(.cm-activeLineGutter) {
  background: transparent;
}

.wx-code-editor :deep(.cm-selectionMatch) {
  background: var(--wx-color-primary-soft);
}

.wx-code-editor :deep(.cm-matchingBracket),
.wx-code-editor :deep(.cm-focused .cm-matchingBracket) {
  background: var(--wx-color-primary-soft);
  outline: 1px solid var(--wx-color-primary-disabled);
}

.wx-code-editor :deep(.cm-nonmatchingBracket),
.wx-code-editor :deep(.cm-focused .cm-nonmatchingBracket) {
  background: var(--wx-color-danger-soft);
  outline: 1px solid var(--wx-color-danger);
}

.wx-code-editor :deep(.cm-foldPlaceholder) {
  margin: 0 var(--wx-space-2);
  padding: 0 var(--wx-space-4);
  background: var(--wx-bg-fill);
  border: none;
  border-radius: var(--wx-radius-xs);
  color: var(--wx-text-muted);
}

.wx-code-editor :deep(.cm-lintRange-error) {
  background-image: none;
  text-decoration: underline wavy var(--wx-color-danger);
  text-underline-offset: 3px;
}

.wx-code-editor :deep(.cm-lintRange-warning) {
  background-image: none;
  text-decoration: underline wavy var(--wx-color-warning);
  text-underline-offset: 3px;
}

/* Tooltips and the search panel are portalled inside the editor, so they inherit tokens. */
.wx-code-editor :deep(.cm-tooltip) {
  /* The surface a popover stands on; `--wx-bg-overlay` is the dimming behind a dialog. */
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-sm);
  box-shadow: var(--wx-shadow-md);
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
  font-size: var(--wx-font-size-sm);
}

.wx-code-editor :deep(.cm-tooltip-lint) {
  padding: var(--wx-space-4) 0;
}

/* The list an `autocompletion()` passed through `extensions` opens. */
.wx-code-editor :deep(.cm-tooltip-autocomplete > ul) {
  max-height: 16em;
  padding: var(--wx-space-4) 0;
  font-family: var(--wx-font-family-mono);
}

.wx-code-editor :deep(.cm-tooltip-autocomplete > ul > li) {
  display: flex;
  gap: var(--wx-space-12);
  align-items: baseline;
  padding: var(--wx-space-2) var(--wx-space-8);
}

.wx-code-editor :deep(.cm-tooltip-autocomplete > ul > li[aria-selected]) {
  background: var(--wx-color-primary-soft);
  color: var(--wx-text-default);
}

.wx-code-editor :deep(.cm-completionMatchedText) {
  text-decoration: none;
  font-weight: 600;
}

.wx-code-editor :deep(.cm-completionDetail) {
  margin-left: auto;
  font-style: normal;
  font-family: var(--wx-font-family-sans);
  color: var(--wx-text-muted);
}

.wx-code-editor :deep(.cm-completionInfo) {
  padding: var(--wx-space-6) var(--wx-space-8);
  max-width: 320px;
}

.wx-code-editor :deep(.cm-diagnostic) {
  padding: var(--wx-space-2) var(--wx-space-8);
  border-left-width: 3px;
}

.wx-code-editor :deep(.cm-diagnostic-error) {
  border-left-color: var(--wx-color-danger);
}

.wx-code-editor :deep(.cm-diagnostic-warning) {
  border-left-color: var(--wx-color-warning);
}

.wx-code-editor :deep(.cm-panels) {
  background: var(--wx-bg-subtle);
  border-color: var(--wx-border-muted);
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
  font-size: var(--wx-font-size-sm);
}

.wx-code-editor :deep(.cm-panel.cm-search) {
  padding: var(--wx-space-6) var(--wx-space-8);
}

.wx-code-editor :deep(.cm-panel.cm-search input),
.wx-code-editor :deep(.cm-panel.cm-search button) {
  margin: 0 var(--wx-space-4) var(--wx-space-2) 0;
  padding: var(--wx-space-2) var(--wx-space-6);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-xs);
  color: inherit;
  font: inherit;
}

.wx-code-editor :deep(.cm-panel.cm-search button) {
  background-image: none;
  cursor: pointer;
}

.wx-code-editor :deep(.cm-searchMatch) {
  background: var(--wx-color-warning-soft);
}

.wx-code-editor :deep(.cm-searchMatch.cm-searchMatch-selected) {
  background: var(--wx-color-warning-disabled);
}
</style>
