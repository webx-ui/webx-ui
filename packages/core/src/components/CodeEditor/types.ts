import type { Extension } from '@codemirror/state'
import type { EditorView } from '@codemirror/view'
import type { ControlSize, ControlStatus } from '../../composables/useFormField'
import type { LocalizedValue } from '../../composables/useLocalized'

/** The document, or under `localized` one per language. */
export type CodeEditorModelValue = string | LocalizedValue | null | undefined

/**
 * The languages the editor highlights out of the box. `plain` highlights nothing; `mustache` is
 * Mustache tags over text, `mustache-html` over HTML.
 */
export type CodeEditorLanguage =
  | 'json'
  | 'javascript'
  | 'typescript'
  | 'html'
  | 'css'
  | 'markdown'
  | 'yaml'
  | 'php'
  | 'mustache'
  | 'mustache-html'
  | 'plain'

/** One suggestion of the completion list. */
export interface CodeEditorCompletion {
  /** What the list shows, and what typing is matched against. */
  label: string
  /**
   * What goes into the document instead of the label. `${}` marks where the caret lands and
   * `${name}` a field to type over, as in CodeMirror's snippets.
   */
  apply?: string
  /** A short word beside the label: a type, an example value. */
  detail?: string
  /** A longer description, shown beside the list for the suggestion in focus. */
  info?: string
  /** CodeMirror's kind of suggestion — `variable`, `keyword`, `property` and the like. */
  type?: string
  /** Higher comes first among equally good matches; -99 … 99. */
  boost?: number
}

export interface CodeEditorCompletionContext {
  /** The whole document. */
  text: string
  /** Where the caret is. */
  pos: number
  /** Asked for with Ctrl+Space rather than by typing. */
  explicit: boolean
}

export interface CodeEditorCompletionResult {
  /** Where the text the suggestions replace starts. */
  from: number
  /** Where it ends; the caret when left out. */
  to?: number
  options: CodeEditorCompletion[]
}

/** Suggestions for a place in the document, or `null` for none there. */
export type CodeEditorCompleter = (
  context: CodeEditorCompletionContext,
) => CodeEditorCompletionResult | null

/**
 * A fixed vocabulary — completed from the word before the caret — or a function that decides
 * by the document itself, like `mustacheCompletions()`.
 */
export type CodeEditorCompletions = CodeEditorCompletion[] | CodeEditorCompleter

/** A value a Mustache template may use, for `mustacheCompletions()` and `mustacheProblems()`. */
export interface MustacheVariable {
  /** `name`, `field.phone`; `field.*` for a family of names. */
  name: string
  /** What it is, in words. */
  label?: string
  /** A value it may take. */
  example?: unknown
  /** A list: the fields of each of its elements. */
  fields?: { name: string; label?: string }[]
}

/** The words of the Mustache suggestions — English by default, `:name` filled in. */
export interface MustacheWords {
  variable: string
  list: string
  field: string
  section: string
  inverted: string
  close: string
  comment: string
  example: string
}

export interface CodeEditorDiagnostic {
  /** Offset of the first character the problem covers. */
  from: number
  /** Offset just past the last character it covers. */
  to: number
  severity: 'error' | 'warning' | 'info' | 'hint'
  message: string
}

export interface CodeEditorProps {
  language?: CodeEditorLanguage
  placeholder?: string
  disabled?: boolean
  readonly?: boolean
  size?: ControlSize
  status?: ControlStatus
  id?: string
  /** Accessible label used when there is no visible `<label>`. */
  ariaLabel?: string
  /** Height of the editing area before it starts growing. */
  minHeight?: string
  /** Height at which the editor stops growing and scrolls instead. */
  maxHeight?: string
  lineNumbers?: boolean
  /** Wrap long lines instead of scrolling horizontally. */
  lineWrapping?: boolean
  /** Width of one indentation step, in spaces; Tab inserts this many. */
  tabSize?: number
  /**
   * Check the document with the language's own linter and underline what it finds.
   * Only JSON ships with one; the flag is ignored elsewhere.
   */
  lint?: boolean
  /**
   * Suggestions while typing, and on Ctrl+Space: a list of words, or a function of the document
   * and the caret — `mustacheCompletions(variables)` for a Mustache template.
   */
  completions?: CodeEditorCompletions
  /**
   * Edits the document in every language the site publishes in, with a selector on the field;
   * the model is then `{ en: '…', uk: '…' }`.
   */
  localized?: boolean
  /** Extra CodeMirror extensions, for anything this component does not wrap. */
  extensions?: Extension[]
}

export interface CodeEditorEmits {
  change: [value: string]
  focus: []
  blur: []
  /** The linter ran; an empty list means the document is valid. */
  lint: [diagnostics: CodeEditorDiagnostic[]]
}

export interface CodeEditorExposed {
  view: EditorView | null
  focus: () => void
  /**
   * Re-indents a JSON document with `tabSize` spaces. Returns `false` and leaves the
   * text alone when it does not parse or the language is not JSON.
   */
  format: () => boolean
}
