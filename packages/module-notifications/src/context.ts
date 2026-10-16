import { computed, inject, provide, ref, type ComputedRef, type InjectionKey, type Ref } from 'vue'
import type { ScreenModel } from '@webx-ui/schema'
import type { NotificationEvent, NotificationProvider, NotificationsMeta } from './types'

/**
 * What the fields of a channel's form know about the form around them: the kind of channel and
 * the event chosen in it, which decide what the template suggests and which addressees there
 * are, and the record itself, which "Find chats" asks the saved token of.
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
    }
  )
}
