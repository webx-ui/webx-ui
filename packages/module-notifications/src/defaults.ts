import { watch, type Ref } from 'vue'
import type { ScreenModel } from '@webx-ui/schema'
import type { ChannelContext } from './context'
import type { EventSample, NotificationEvent } from './types'

/**
 * The default template of an event (`EventDefinition::samplesFor()`): what a channel of a provider
 * starts with, and what "Insert the default template" puts back — the subject and the template in
 * every language the event has words in.
 */
export function sampleOf(
  event: NotificationEvent | null | undefined,
  provider: string | null | undefined,
): EventSample | null {
  if (!event || !provider) return null

  return event.sample[provider] ?? event.sample['*'] ?? null
}

/** Nothing written in any language: what may be filled without asking. */
export function blankText(value: unknown): boolean {
  if (value === null || value === undefined) return true
  if (typeof value === 'string') return value.trim() === ''
  if (typeof value === 'object') return Object.values(value).every((one) => blankText(one))

  return false
}

/**
 * The form's values with the default template in place of the message: every language the
 * template had is replaced, not only the ones the sample has — what is kept would be half one
 * message and half another.
 */
export function withSample(
  values: ScreenModel,
  sample: EventSample,
  subject: boolean,
): ScreenModel {
  return {
    ...values,
    template: { ...sample.template },
    ...(subject && sample.subject !== null ? { subject: { ...sample.subject } } : {}),
  }
}

/**
 * An event chosen for a channel whose message is still empty brings its default template along —
 * nothing to lose. A message already written is kept: "Insert the default template" under it
 * asks first. Synchronous, so that the values a page puts in while `busy` loading are told apart
 * from a choice made in the form.
 */
export function fillOnEvent(context: ChannelContext, busy: Ref<boolean>, filled: () => void): void {
  watch(
    () => context.values.value.event,
    (event, before) => {
      if (busy.value || event === before) return

      const sample = sampleOf(context.event.value, context.provider.value?.key)
      const subject = context.provider.value?.subject ?? false
      const values = context.values.value

      if (
        sample === null ||
        !blankText(values.template) ||
        (subject && !blankText(values.subject))
      ) {
        return
      }

      context.values.value = withSample(values, sample, subject)
      filled()
    },
    { flush: 'sync' },
  )
}
