import { inject, provide, type InjectionKey, type Ref } from 'vue'
import type { VacancyRow } from './types'

/**
 * What the editor knows and the nodes of `vacancies.form` do not: the vacancy being edited, and a
 * way to read it again. The history needs both — its list is keyed by the vacancy, and a restored
 * version has to reach the fields the form is bound to.
 */
export interface VacancyEditorContext {
  /** The vacancy as the server last answered it, or `null` while the first request is out. */
  vacancy: Ref<VacancyRow | null>
  /** Whether this administrator may write at all. */
  canManage: boolean
  /** Ask the server for the vacancy again — after a restore, a publication, a discard. */
  reload(): Promise<void>
}

export const vacancyEditorKey: InjectionKey<VacancyEditorContext> = Symbol('wx-vacancy-editor')

export function provideVacancyEditor(editor: VacancyEditorContext): void {
  provide(vacancyEditorKey, editor)
}

/** The editor above this node, or `null` outside one — a demo or a test draws nothing. */
export function useVacancyEditor(): VacancyEditorContext | null {
  return inject(vacancyEditorKey, null)
}
