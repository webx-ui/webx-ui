import type { ObjectDirective } from 'vue'
import type { SelectionValue } from './types'

/** Marks an element as something a selection box can catch. */
export const SELECTABLE = 'data-wx-selectable'

/*
 * The value is kept against the element instead of in a registry the area holds, because
 * the area reads its items out of the DOM when a drag begins: `v-for` may have rewritten
 * the list since the last one, a virtual scroller may have recycled half of it, and a
 * registry would have to be kept in step with all of that to say the same thing. The map
 * is weak, so an element that leaves the page takes its entry with it.
 */
const values = new WeakMap<Element, SelectionValue>()

/**
 * The value of a selectable element. Falls back to the attribute, so markup that is not
 * written in Vue can say `data-wx-selectable="42"` and be caught the same way — as the
 * string `'42'`, since that is all an attribute can hold.
 */
export function valueOf(el: Element): SelectionValue | undefined {
  const known = values.get(el)
  if (known !== undefined) return known
  const written = el.getAttribute(SELECTABLE)
  return written ? written : undefined
}

function mark(el: HTMLElement, value: SelectionValue) {
  values.set(el, value)
  el.setAttribute(SELECTABLE, '')
}

/**
 * `v-wx-select="item.id"` hands an element to the selection area around it.
 *
 * A directive rather than a component, because the element is already there — a card, a
 * row, a list item — and wrapping every one of them in a box of ours would break the grid
 * or table it sits in. It also keeps the value's type: an attribute could only carry the
 * string `'42'`, and the selection would stop matching the numbers the model holds.
 */
export const vWxSelect: ObjectDirective<HTMLElement, SelectionValue> = {
  mounted(el, binding) {
    mark(el, binding.value)
  },
  updated(el, binding) {
    if (binding.value !== binding.oldValue) mark(el, binding.value)
  },
  unmounted(el) {
    values.delete(el)
    el.removeAttribute(SELECTABLE)
  },
}
