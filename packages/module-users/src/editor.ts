import { inject, provide, type InjectionKey, type Ref } from 'vue'
import type { UserSummary } from './types'

/**
 * What the open card shares with the node types on it (`wx-users-state`, `wx-users-ways`): the
 * customer as the server last answered, and what the ways do — the node draws, the page asks.
 */
export interface UserEditorContext {
  user: Ref<UserSummary | null>
  locked: Ref<boolean>
  unlink(provider: string): Promise<void>
}

export const userEditorKey: InjectionKey<UserEditorContext> = Symbol('webx-users-editor')

export function provideUserEditor(context: UserEditorContext): void {
  provide(userEditorKey, context)
}

/** Outside a card — the screen's preview, a project's page — there is nothing to show. */
export function useUserEditor(): UserEditorContext | null {
  return inject(userEditorKey, null)
}
