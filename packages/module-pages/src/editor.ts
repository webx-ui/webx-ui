import { inject, provide, type InjectionKey, type Ref } from 'vue'
import type { ScreenModel } from '@webx-ui/schema'
import type { PageRow } from './types'

/**
 * What the editor knows and the nodes of its screen do not.
 *
 * The form is a described screen (`pages.form`), so the parts of it that are not fields —
 * where the page sits, what may be done to it, what its history is — arrive as node types of
 * their own. Each of those needs the page being edited, and none of them can be handed it
 * through the description: a screen is a description, not a binding. So the page that hosts
 * the screen provides it, the same way it provides the preview to `wx-blocks`.
 */
export interface PageEditorContext {
  /** The page as the server last answered it, or `null` while the first request is in flight. */
  page: Ref<PageRow | null>
  /** The trail above it, home page first. */
  ancestors: Ref<PageRow[]>
  /**
   * The values of the screen as they are right now, edits included.
   *
   * A node that only draws still has to follow what is being typed: the address is a sentence
   * made of the slug field, and one that updated on save would be showing the old address at
   * the moment the new one is most worth reading.
   */
  values: Ref<ScreenModel>
  /** The address of the page above, by content language. */
  prefixes: Ref<Record<string, string>>
  /** Where the section lives, for the links the nodes draw. */
  base: string
  /** Whether this administrator may write at all. */
  canManage: boolean
  /** Ask the server for the page again — after a move, a restore, a publication. */
  reload(): Promise<void>
  /** Write the draft now rather than at the end of the next pause. */
  save(): Promise<void>
}

export const pageEditorKey: InjectionKey<PageEditorContext> = Symbol('wx-page-editor')

export function providePageEditor(editor: PageEditorContext): void {
  provide(pageEditorKey, editor)
}

/**
 * The editor above this node, or `null` outside one — a node of `pages.form` drawn in a demo
 * or a test has no page to talk about, and must draw nothing rather than throw.
 */
export function usePageEditor(): PageEditorContext | null {
  return inject(pageEditorKey, null)
}
