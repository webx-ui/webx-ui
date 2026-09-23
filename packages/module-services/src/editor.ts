import { inject, provide, type InjectionKey, type Ref } from 'vue'
import type { ServiceRow } from './types'

/**
 * What the editor knows and the nodes of `services.form` do not: the service being edited, and a
 * way to read it again. The history needs both — its list is keyed by the service, and a restored
 * version has to reach the fields the form is bound to.
 */
export interface ServiceEditorContext {
  /** The service as the server last answered it, or `null` while the first request is out. */
  service: Ref<ServiceRow | null>
  /** Whether this administrator may write at all. */
  canManage: boolean
  /** Ask the server for the service again — after a restore, a publication, a discard. */
  reload(): Promise<void>
}

export const serviceEditorKey: InjectionKey<ServiceEditorContext> = Symbol('wx-service-editor')

export function provideServiceEditor(editor: ServiceEditorContext): void {
  provide(serviceEditorKey, editor)
}

/** The editor above this node, or `null` outside one — a demo or a test draws nothing. */
export function useServiceEditor(): ServiceEditorContext | null {
  return inject(serviceEditorKey, null)
}
