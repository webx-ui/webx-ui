import { onBeforeUnmount, onMounted, ref, watch, type Ref } from 'vue'

/**
 * The width of an element, kept up to date — the measurement every layout that
 * answers to its own box rather than to the window is built on.
 *
 * `0` means nothing has been measured yet: on the server, and until the element is
 * mounted. Treat it as "assume the roomy case" rather than as zero width, the way
 * `useResponsiveShell` does — the real number arrives before paint.
 *
 * ```ts
 * const el = ref<HTMLElement | null>(null)
 * const width = useElementWidth(el)
 * ```
 *
 * Pass nothing to measure the page itself.
 */
export function useElementWidth(target?: Ref<HTMLElement | null | undefined>): Ref<number> {
  const width = ref(0)

  let observer: ResizeObserver | null = null
  let observed: HTMLElement | null = null

  function element() {
    if (target) return target.value ?? null
    return typeof document === 'undefined' ? null : document.documentElement
  }

  function observe() {
    const el = element()
    /* Mounting and the watch on `target` both land here; the same element is measured once. */
    if (el === observed) return

    observer?.disconnect()
    observed = el
    if (!el || typeof ResizeObserver === 'undefined') return

    /* A first reading, since an observer only reports once something changes. */
    const measured = Math.round(el.getBoundingClientRect().width)
    if (measured > 0) width.value = measured

    observer = new ResizeObserver((entries) => {
      const entry = entries[0]
      if (entry) width.value = Math.round(entry.contentRect.width)
    })
    observer.observe(el)
  }

  onMounted(observe)

  if (target) watch(target, observe)

  onBeforeUnmount(() => {
    observer?.disconnect()
    observer = null
    observed = null
  })

  return width
}
