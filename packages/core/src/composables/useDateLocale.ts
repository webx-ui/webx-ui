import { computed, inject, provide, type ComputedRef, type InjectionKey, type Ref } from 'vue'
import { dateFnsLocale, type DateFnsLocale } from '../internal/dateLocale'

/** A BCP-47 tag, or something that reads one — the application's own language. */
export type DateLocaleSource = string | Ref<string> | ComputedRef<string>

/**
 * The language every date picker below draws its calendar in.
 *
 * This is the language of the *interface*, not of the content: an administrator who
 * switched the panel to Russian on a machine set to English expects the month header to
 * follow the switch, and neither the browser nor the site's content languages know about
 * it. A `locale` prop on the picker still wins over what is provided here.
 */
export const dateLocaleKey: InjectionKey<DateLocaleSource> = Symbol('wx-date-locale')

/**
 * Tells every date picker below which language to draw its calendar in.
 *
 * ```ts
 * app.provide(dateLocaleKey, computed(() => admin.i18n.state.locale))
 * ```
 */
export function provideDateLocale(locale: DateLocaleSource): void {
  provide(dateLocaleKey, locale)
}

/** The calendar's language: the `locale` prop, then the application's, then the browser's. */
export function useDateLocale(
  locale: () => string | DateFnsLocale | undefined,
): ComputedRef<DateFnsLocale> {
  const provided = inject(dateLocaleKey, undefined)

  return computed(() => {
    const asked = locale()
    // A hand-built date-fns locale is the picker's own escape hatch; it goes straight on.
    if (asked && typeof asked !== 'string') return asked

    const tag = asked ?? (typeof provided === 'string' ? provided : provided?.value)

    return dateFnsLocale(tag || browserLocale())
  })
}

function browserLocale(): string {
  return (typeof navigator === 'undefined' ? '' : navigator.language) || 'en-US'
}
