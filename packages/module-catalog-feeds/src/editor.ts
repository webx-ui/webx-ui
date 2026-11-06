import { inject, provide, type InjectionKey, type Ref } from 'vue'
import type { CatalogFeedsApi } from './api'
import type { FeedDetail, FeedsMeta, FeedValues, FeedVariables } from './types'

/**
 * What the feed's form shares with the fields of its screen. Most of them edit more than one key of
 * the feed, or nothing of it at all, so they read and write here rather than through a `name`.
 */
export interface FeedEditorContext {
  api: CatalogFeedsApi
  /** Where the catalogue lives in the panel: `/catalog`. */
  base: string
  /** As the server last answered; `null` for a feed not created yet. */
  feed: Ref<FeedDetail | null>
  values: Ref<FeedValues>
  locked: Ref<boolean>
  errors: Ref<Record<string, string[]>>
  meta: Ref<FeedsMeta | null>
  variables: Ref<FeedVariables | null>
  /** The address changed on the server: the form takes the feed without what is being typed. */
  took(feed: FeedDetail): void
}

export const feedEditorKey: InjectionKey<FeedEditorContext> = Symbol('wx-catalog-feed')

export function provideFeedEditor(context: FeedEditorContext): void {
  provide(feedEditorKey, context)
}

/** The fields live on the feed's page alone: outside it there is nothing for them to edit. */
export function useFeedEditor(): FeedEditorContext {
  const context = inject(feedEditorKey, null)

  if (context === null) throw new Error('A field of a feed is outside the feed’s page.')

  return context
}
