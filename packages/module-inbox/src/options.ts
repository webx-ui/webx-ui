import { computed, type Ref, type WritableComputedRef } from 'vue'
import type { FormOptions } from './types'

/**
 * One setting of a form, as something a control can be bound to.
 *
 * The keys are literal and several of them have dots in them — `thank-you.heading` is one key
 * (§5) — so nothing here splits on a dot, and a whole new object is written on every change
 * rather than a property being set in place: the editor holds the options as one value, and a
 * mutation underneath it is a change nothing is told about.
 *
 * An empty value is not written as an empty value: it is taken out. The server does the same
 * when it saves, and a form whose settings are twelve blank strings reads as configured when
 * it is not.
 */
export function option<T>(
  options: Ref<FormOptions>,
  key: string,
  fallback: T,
): WritableComputedRef<T> {
  return computed({
    get: () => (options.value[key] ?? fallback) as T,
    set: (value: T) => {
      const next = { ...options.value }

      if (value === '' || value === null || value === undefined) {
        delete next[key]
      } else {
        next[key] = value
      }

      options.value = next
    },
  })
}
