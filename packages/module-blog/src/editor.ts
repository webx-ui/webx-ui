import { inject, provide, type InjectionKey, type Ref } from 'vue'
import type { ScreenModel } from '@webx-ui/schema'
import type { ArticleDetail, ArticleOption, ArticleRow } from './types'

/**
 * What the editor knows and the nodes of its screen do not.
 *
 * The form is a described screen (`blog.article-form`), so the parts of it that need more than
 * a value — the address printed whole, the rubrics with their titles, the history — arrive as
 * node types of their own. Each of those needs the article being edited and the lists the
 * server sent with it, and none of them can be handed either through the description: a screen
 * is a description, not a binding. So the page that hosts the screen provides it, the same way
 * it provides the preview to `wx-blocks`.
 */
export interface ArticleEditorContext {
  /** The article as the server last answered it, or `null` while the first request is in flight. */
  article: Ref<ArticleRow | null>
  /**
   * The values of the screen as they are right now, edits included.
   *
   * A node that only draws still has to follow what is being typed: the address is a sentence
   * made of the slug field, and one that updated on save would be showing the old address at
   * the moment the new one is most worth reading.
   */
  values: Ref<ScreenModel>
  /** What the dropdowns on the screen can be set to, as the record carried them. */
  options: Ref<ArticleDetail['options']>
  /** Titles for the ids in `values.related` — the only place they come from. */
  related: Ref<ArticleOption[]>
  /** The first segment of every blog address, for the field that edits the last one. */
  prefix: Ref<string>
  /** Where the section lives, for the links the nodes draw. */
  base: string
  /** Whether this administrator may write at all. */
  canManage: boolean
  /**
   * Whether the screen is closed for writing right now — no permission, or a publication in
   * flight.
   *
   * Through the editor rather than through the form, because `WxForm`'s own `disabled` reaches
   * a control only through `useFormField`, which the design system does not hand out: a field
   * built outside `@webx-ui/core` has no way to ask. The screen is told the same thing twice —
   * once for the fields that are core's and once here — and both are computed from one place.
   */
  disabled: Ref<boolean>
  /** Ask the server for the article again — after a restore, a publication, a discard. */
  reload(): Promise<void>
  /** Write the draft now rather than at the end of the next pause. */
  save(): Promise<void>
}

export const articleEditorKey: InjectionKey<ArticleEditorContext> = Symbol('wx-article-editor')

export function provideArticleEditor(editor: ArticleEditorContext): void {
  provide(articleEditorKey, editor)
}

/**
 * The editor above this node, or `null` outside one — a node of `blog.article-form` drawn in a
 * demo or a test has no article to talk about, and must draw nothing rather than throw.
 */
export function useArticleEditor(): ArticleEditorContext | null {
  return inject(articleEditorKey, null)
}
