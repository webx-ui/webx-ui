import { inject, provide, type InjectionKey, type Ref } from 'vue'
import type { EventRow } from './types'

/**
 * What the editor knows and the nodes of `events.form` do not: the event being edited, and a way
 * to read it again. The history needs both — its list is keyed by the event, and a restored
 * version has to reach the fields the form is bound to.
 */
export interface EventEditorContext {
  /** The event as the server last answered it, or `null` while the first request is out. */
  event: Ref<EventRow | null>
  /** Whether this administrator may write at all. */
  canManage: boolean
  /** Ask the server for the event again — after a restore, a publication, a discard. */
  reload(): Promise<void>
}

export const eventEditorKey: InjectionKey<EventEditorContext> = Symbol('wx-event-editor')

export function provideEventEditor(editor: EventEditorContext): void {
  provide(eventEditorKey, editor)
}

/** The editor above this node, or `null` outside one — a demo or a test draws nothing. */
export function useEventEditor(): EventEditorContext | null {
  return inject(eventEditorKey, null)
}
