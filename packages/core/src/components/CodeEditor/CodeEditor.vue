<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue'
import { Compartment, EditorState, type Extension } from '@codemirror/state'
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
import { closeBrackets, closeBracketsKeymap } from '@codemirror/autocomplete'
import { highlightSelectionMatches, searchKeymap } from '@codemirror/search'
import { linter, type Diagnostic } from '@codemirror/lint'
import { useFormField } from '../../composables/useFormField'
import { LANGUAGES } from './languages'
import { highlightStyle } from './highlight'
import type { CodeEditorDiagnostic, CodeEditorEmits, CodeEditorProps } from './types'

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
  extensions: () => [],
})

const emit = defineEmits<CodeEditorEmits>()

const model = defineModel<string>({ default: '' })

const field = useFormField(props)
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
      keymap.of([
        ...closeBracketsKeymap,
        ...defaultKeymap,
        ...searchKeymap,
        ...historyKeymap,
        ...foldKeymap,
        indentWithTab,
      ]),
      compartments.language.of(languageExtension()),
      compartments.lint.of(lintExtension()),
      compartments.editable.of(editableExtension()),
      compartments.gutter.of(gutterExtension()),
      compartments.wrapping.of(wrappingExtension()),
      compartments.indent.of(indentExtension()),
      compartments.placeholder.of(placeholderExtensionFor()),
      compartments.attributes.of(attributesExtension()),
      compartments.extra.of(props.extensions),
      EditorView.updateListener.of((update) => {
        if (!update.docChanged) return
        const value = update.state.doc.toString()
        model.value = value
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
  view.value = new EditorView({ state: buildState(model.value ?? ''), parent: host.value })
})

onBeforeUnmount(() => {
  view.value?.destroy()
  view.value = null
})

/** Replaces the document only when the value really differs, or the caret jumps. */
watch(model, (value) => {
  const instance = view.value
  if (!instance) return
  const next = value ?? ''
  const current = instance.state.doc.toString()
  if (next === current) return
  instance.dispatch({ changes: { from: 0, to: current.length, insert: next } })
})

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
  },
])

const style = computed(() => ({
  '--wx-code-editor-min-height': props.minHeight,
  '--wx-code-editor-max-height': props.maxHeight ?? 'none',
}))

defineExpose({
  view,
  focus: () => view.value?.focus(),
  format,
})
</script>

<template>
  <div :class="classes" :style="style">
    <div ref="host" class="wx-code-editor__host" />
  </div>
</template>

<style scoped>
.wx-code-editor {
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
  background: var(--wx-bg-overlay);
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
