import { inject, provide, type InjectionKey, type Ref } from 'vue'
import type { QuestionRow } from './types'

/**
 * What the form knows and the nodes of `faq.form` do not: the question being edited.
 *
 * The anchor is not a value of the screen — nobody writes it, it is made once on the server and
 * never changes (decision 10) — so the node that shows it (`wx-faq-anchor`) reads the record.
 */
export interface QuestionEditorContext {
  /** The question as the server last answered it; `null` while it is new and not yet saved. */
  question: Ref<QuestionRow | null>
}

export const questionEditorKey: InjectionKey<QuestionEditorContext> = Symbol('wx-faq-question')

export function provideQuestionEditor(editor: QuestionEditorContext): void {
  provide(questionEditorKey, editor)
}

/** The form above this node, or `null` outside one — a demo or a test draws the empty state. */
export function useQuestionEditor(): QuestionEditorContext | null {
  return inject(questionEditorKey, null)
}
