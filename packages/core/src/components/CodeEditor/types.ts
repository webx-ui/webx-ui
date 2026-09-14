import type { Extension } from '@codemirror/state'
import type { EditorView } from '@codemirror/view'
import type { ControlSize, ControlStatus } from '../../composables/useFormField'

/** The languages the editor highlights out of the box. `plain` highlights nothing. */
export type CodeEditorLanguage =
  'json' | 'javascript' | 'typescript' | 'html' | 'css' | 'markdown' | 'yaml' | 'php' | 'plain'

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
