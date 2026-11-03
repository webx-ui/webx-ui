import { computed, onMounted, shallowRef, type ComputedRef, type Ref } from 'vue'

/** What a picker's `teleport` takes: on (`body`), off, a selector or an element. */
export type TeleportTarget = boolean | string | HTMLElement

/** The layers a panel of a picker has to stay inside: Reka's dialogs, sheets and popovers. */
const LAYER = '[role="dialog"], [role="alertdialog"]'

/**
 * Where a panel that is not Reka's own — the calendar of `@vuepic/vue-datepicker` — goes when it
 * is teleported: into the dialog or popover the control sits in, else `body`.
 *
 * A modal Reka dialog sets `pointer-events: none` on `body` and gives it back only to its own
 * layers. A calendar teleported to `body` inherits the `none`: the click on a day falls through
 * to whatever lies under it in the dialog, and the calendar closes without a date. Inside the
 * dialog's content it is part of the layer — clickable, and not «outside» either.
 */
export function useLayerTeleport(
  root: Ref<HTMLElement | null>,
  asked: () => TeleportTarget | undefined,
): ComputedRef<TeleportTarget | undefined> {
  const layer = shallowRef<HTMLElement | null>(null)

  onMounted(() => {
    layer.value = root.value?.closest<HTMLElement>(LAYER) ?? null
  })

  return computed(() => {
    const target = asked()

    return target === true && layer.value ? layer.value : target
  })
}
