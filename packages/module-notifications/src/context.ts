import { computed, inject, provide, ref, type ComputedRef, type InjectionKey, type Ref } from 'vue'
import type { ScreenModel } from '@webx-ui/schema'
import type { NotificationEvent, NotificationProvider, NotificationsMeta } from './types'

/**
 * What the fields of a channel's form know about the form around them: the kind of channel and
 * the event chosen in it, which decide what the template suggests and which addressees there
 * are, and the record itself, which "Find chats" asks the saved token of.
 *
 * The template fields of the form — the template and the subject — tell it which of them the
 * caret was in last, so that a variable clicked under the template goes where the person was
 * typing, the subject included.
 *
 * Provided by the channel's page; a field drawn anywhere else gets an empty one and works as a
 * plain field — a template with only Mustache's syntax to suggest, chats without a search.
 */
export interface ChannelContext {
  meta: Ref<NotificationsMeta | null>
  values: Ref<ScreenModel>
  /** The saved channel, or null for one not written yet. */
  id: Ref<number | null>
  provider: ComputedRef<NotificationProvider | null>
  event: ComputedRef<NotificationEvent | null>
  /** The template field the caret was in last: `template`, `subject`. */
  focused: Ref<string | null>
  /** Each template field's way of putting text where its caret is, by part. */
  inserters: Map<string, (snippet: string) => void>
  /** Into the field the caret was in last, else the template. */
  insert: (snippet: string) => void
}

function fields(): Pick<ChannelContext, 'focused' | 'inserters' | 'insert'> {
  const focused = ref<string | null>(null)
  const inserters = new Map<string, (snippet: string) => void>()

  return {
    focused,
    inserters,
    insert: (snippet) =>
      (inserters.get(focused.value ?? '') ?? inserters.get('template'))?.(snippet),
  }
}

const key: InjectionKey<ChannelContext> = Symbol('wx-notifications-channel')

export function provideChannel(
  meta: Ref<NotificationsMeta | null>,
  values: Ref<ScreenModel>,
  id: Ref<number | null>,
): ChannelContext {
  const context: ChannelContext = {
    meta,
    values,
    id,
    provider: computed(
      () => meta.value?.providers.find((one) => one.key === values.value.provider) ?? null,
    ),
    event: computed(() => meta.value?.events.find((one) => one.key === values.value.event) ?? null),
    ...fields(),
  }

  provide(key, context)

  return context
}

export function useChannel(): ChannelContext {
  return (
    inject(key, null) ?? {
      meta: ref(null),
      values: ref({}),
      id: ref(null),
      provider: computed(() => null),
      event: computed(() => null),
      ...fields(),
    }
  )
}
