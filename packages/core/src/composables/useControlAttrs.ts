import { computed, useAttrs, type ComputedRef } from 'vue'

/**
 * Splits what the caller wrote on a control between its two halves.
 *
 * Every control here sets `inheritAttrs: false` and hands `$attrs` to the element
 * inside it, so that `placeholder`, `autocomplete`, `data-*` and the ARIA attributes
 * reach the real `<input>` rather than the wrapper around it. Taken literally that
 * sends `class` and `style` there too, and both are then in the wrong place:
 *
 * - The caller means the control, not its input. `class="w-60"` on a `<wx-select>` is
 *   asking for a narrower select, and the select is the wrapper.
 * - A parent's scoped CSS cannot reach it. Scoped styles carry an attribute that is
 *   stamped on a child component's *root*, so a class that lands three elements deep
 *   matches nothing — silently.
 * - And where `$attrs` is bound to an element that is only sometimes rendered — the
 *   search field of a filterable `WxSelect` — the class disappears altogether.
 *
 * So `class` and `style` go on the root, and everything else goes on the control.
 *
 * ```ts
 * const { rootAttrs, controlAttrs } = useControlAttrs()
 * ```
 * ```vue
 * <div :class="classes" v-bind="rootAttrs">
 *   <input v-bind="controlAttrs" />
 * </div>
 * ```
 */
export interface ControlAttrs {
  /** `class` and `style` — for the wrapper the caller can see. */
  rootAttrs: ComputedRef<Record<string, unknown>>
  /** Everything else — for the element the browser focuses and reads out. */
  controlAttrs: ComputedRef<Record<string, unknown>>
}

export function useControlAttrs(): ControlAttrs {
  const attrs = useAttrs()

  const rootAttrs = computed(() => {
    const picked: Record<string, unknown> = {}
    if (attrs.class !== undefined) picked.class = attrs.class
    if (attrs.style !== undefined) picked.style = attrs.style
    return picked
  })

  const controlAttrs = computed(() => {
    const rest: Record<string, unknown> = {}
    for (const [key, value] of Object.entries(attrs)) {
      if (key !== 'class' && key !== 'style') rest[key] = value
    }
    return rest
  })

  return { rootAttrs, controlAttrs }
}
