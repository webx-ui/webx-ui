import { computed, inject, provide, type ComputedRef, type InjectionKey, type Ref } from 'vue'

/** An IANA zone such as `'Asia/Hong_Kong'`, or something that reads one — or nothing yet. */
export type DateTimezoneSource =
  string | Ref<string | null | undefined> | ComputedRef<string | null | undefined>

/**
 * The zone every date picker below shows and takes a *moment* in.
 *
 * A moment travels with its offset (`2026-10-12T09:30:00+08:00`), and without a zone the
 * picker draws it on the reader's own clock: an event at half past nine in Hong Kong reads
 * 04:30 to an editor in Moscow, and whatever they pick goes back with Moscow's offset. A site
 * has one clock — the one its events, opening hours and publication times are written in — so
 * a panel provides that zone here and every picker of a moment follows it. A `timezone` prop on
 * the picker still wins over what is provided.
 */
export const dateTimezoneKey: InjectionKey<DateTimezoneSource> = Symbol('wx-date-timezone')

/**
 * Tells every date picker below which zone a moment is shown and entered in.
 *
 * ```ts
 * app.provide(dateTimezoneKey, computed(() => manifest.value?.timezone))
 * ```
 */
export function provideDateTimezone(timezone: DateTimezoneSource): void {
  provide(dateTimezoneKey, timezone)
}

/** The zone: the `timezone` prop, then the application's, then none — the reader's own. */
export function useDateTimezone(
  timezone: () => string | undefined,
): ComputedRef<string | undefined> {
  const provided = inject(dateTimezoneKey, undefined)

  return computed(() => {
    const asked = timezone() ?? (typeof provided === 'string' ? provided : provided?.value)

    return asked ? asked : undefined
  })
}

/** The zone this browser keeps its clock in, or an empty string where it will not say. */
export function readersTimezone(): string {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone ?? ''
  } catch {
    return ''
  }
}
