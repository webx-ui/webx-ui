import { watch, type Ref } from 'vue'
import type { ScreenModel } from '@webx-ui/schema'
import type { ChannelContext } from './context'
import type { EventSample, NotificationEvent } from './types'

/**
 * Whom a channel with these recipients writes to, as far as its default template goes
 * (`EventDefinition::addresseeOf()`): the first addressee among them the event has a template of
 * its own for, with this provider; null for a channel to fixed addresses.
 */
export function addresseeOf(
  event: NotificationEvent | null | undefined,
  provider: string | null | undefined,
  recipients: unknown,
): string | null {
  if (!event?.addressed || !provider || !Array.isArray(recipients)) return null

  for (const recipient of recipients) {
    const name =
      recipient !== null && typeof recipient === 'object'
        ? (recipient as Record<string, unknown>).variable
        : null
    const own = typeof name === 'string' ? event.addressed[name] : undefined

    if (typeof name === 'string' && own && (own[provider] || own['*'])) return name
  }

  return null
}

/**
 * The default template of an event (`EventDefinition::samplesFor()`): what a channel of a provider
 * starts with, and what "Insert the default template" puts back — the subject and the template in
 * every language the event has words in. With the channel's recipients, the one of its addressee:
 * a channel to the buyer gets the buyer's letter, a channel to the shop the event's own.
 */
export function sampleOf(
  event: NotificationEvent | null | undefined,
  provider: string | null | undefined,
  recipients?: unknown,
): EventSample | null {
  if (!event || !provider) return null

  const addressee = addresseeOf(event, provider, recipients)
  const own = addressee === null ? undefined : event.addressed?.[addressee]

  return own?.[provider] ?? own?.['*'] ?? event.sample[provider] ?? event.sample['*'] ?? null
}

/** Whether the message of the form is this default template, untouched. */
export function isSample(
  values: ScreenModel,
  sample: EventSample | null,
  subject: boolean,
): boolean {
  if (sample === null) return false

  const same = (a: unknown, b: Record<string, string> | null) =>
    JSON.stringify(a ?? {}) === JSON.stringify(b ?? {})

  return (
    same(values.template, sample.template) && (!subject || same(values.subject, sample.subject))
  )
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
 *
 * The recipients changed to another addressee — the buyer added to a new order's channel — and
 * the message is still the default template of the one before, untouched: the new addressee's
 * takes its place, on a channel not saved yet. A message somebody edited is never swapped.
 */
export function fillOnEvent(context: ChannelContext, busy: Ref<boolean>, filled: () => void): void {
  watch(
    () => context.values.value.event,
    (event, before) => {
      if (busy.value || event === before) return

      const sample = sampleOf(
        context.event.value,
        context.provider.value?.key,
        context.values.value.recipients,
      )
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

  watch(
    () =>
      addresseeOf(
        context.event.value,
        context.provider.value?.key,
        context.values.value.recipients,
      ),
    (addressee, before) => {
      // A saved channel keeps its message: "Insert the default template" is there for it.
      if (busy.value || addressee === before || context.id.value !== null) return

      const event = context.event.value
      const provider = context.provider.value?.key
      const was = sampleOf(event, provider, before === null ? [] : [{ variable: before }])
      const now = sampleOf(event, provider, context.values.value.recipients)
      const subject = context.provider.value?.subject ?? false
      const values = context.values.value

      if (now === null || now === was || !isSample(values, was, subject)) return

      context.values.value = withSample(values, now, subject)
      filled()
    },
    { flush: 'sync' },
  )
}
