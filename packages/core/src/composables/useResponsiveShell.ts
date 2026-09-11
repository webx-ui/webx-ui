import { computed, onBeforeUnmount, onMounted, ref, watch, type ComputedRef, type Ref } from 'vue'

/** What shape the navigation takes at the width it has been given. */
export type ShellLayout = 'sidebar' | 'rail' | 'drawer'

export interface ResponsiveShellOptions {
  /** Under this width the menu leaves the page for a drawer behind a burger. */
  phone?: number
  /** Under this width the sidebar starts as an icon rail. */
  tablet?: number
  /** Whether it starts as a rail on a screen wide enough for the full sidebar. */
  collapsed?: boolean
}

export interface ResponsiveShell {
  /** Width of what is being measured, in pixels. `0` until it has been measured. */
  width: Ref<number>
  layout: ComputedRef<ShellLayout>
  /** Hand this to `WxAside` and `WxMenu` — true for both the rail and the drawer. */
  collapsed: ComputedRef<boolean>
  /** Whether there is room for a sidebar at all. False on a phone. */
  showAside: ComputedRef<boolean>
  drawerOpen: Ref<boolean>
  /**
   * What the one button in the header does: it collapses and expands the sidebar
   * while the sidebar is on the page, and opens the drawer once the menu is gone.
   * The icon follows `layout` — a burger belongs to the drawer alone.
   */
  toggle: () => void
  close: () => void
}

const PHONE = 640
const TABLET = 1024

/**
 * The rule on its own, so it can be read — and tested — without a browser.
 *
 * `collapsed` is the answer to "is the sidebar a rail", and `null` leaves it to the
 * width: a rail from the tablet breakpoint down. Only the phone breakpoint is
 * absolute, because a menu that has nowhere to stand has to leave the page.
 *
 * A width of `0` means nothing has been measured yet: on the server, and on the very
 * first render. The widest shape is the right guess there, since it is the one a
 * desktop gets, and the measurement that follows corrects it before paint.
 */
export function shellLayoutFor(
  width: number,
  collapsed: boolean | null,
  options: ResponsiveShellOptions = {},
): ShellLayout {
  const { phone = PHONE, tablet = TABLET } = options

  if (width > 0 && width < phone) return 'drawer'

  const rail = collapsed ?? (width > 0 && width < tablet)
  return rail ? 'rail' : 'sidebar'
}

/**
 * Drives the three shapes an admin sidebar takes — full, icon rail, drawer — from
 * the width of the shell rather than from the viewport's. The two are the same
 * thing on a real page, and they are not inside a preview, a split screen or the
 * demo box on this page, which is the case that catches a viewport media query out.
 *
 * ```ts
 * const shell = useResponsiveShell(shellRef)
 * ```
 *
 * Pass nothing to measure the page itself.
 */
export function useResponsiveShell(
  target?: Ref<HTMLElement | null | undefined>,
  options: ResponsiveShellOptions = {},
): ResponsiveShell {
  const width = ref(0)
  const drawerOpen = ref(false)

  /*
   * What the reader last asked for, or `null` for "whatever the width suggests".
   * The width picks the rail on a tablet; it does not hold it there, and the toggle
   * expands the sidebar in place like it does on any other screen.
   */
  const collapsedByUser = ref<boolean | null>(options.collapsed ?? null)

  const layout = computed(() => shellLayoutFor(width.value, collapsedByUser.value, options))
  const collapsed = computed(() => layout.value !== 'sidebar')
  const showAside = computed(() => layout.value !== 'drawer')

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
  })

  /*
   * The menu is back on the page, so the drawer has nothing left to show — and what
   * the reader last asked of a sidebar they could see does not carry over the gap.
   */
  watch(layout, (value) => {
    /* What the reader asked of a sidebar they could see does not carry over the gap. */
    if (value === 'drawer') collapsedByUser.value = null
    /* And once the menu is back on the page, the drawer has nothing left to show. */
    else drawerOpen.value = false
  })

  /**
   * The button does the one thing that is visible from where the reader is standing:
   * where the sidebar is on the page it collapses and expands it, and where the menu
   * is gone entirely it is the burger that brings the drawer back.
   */
  function toggle() {
    if (layout.value === 'drawer') drawerOpen.value = !drawerOpen.value
    else collapsedByUser.value = layout.value !== 'rail'
  }

  function close() {
    drawerOpen.value = false
  }

  return { width, layout, collapsed, showAside, drawerOpen, toggle, close }
}
