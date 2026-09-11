import { computed, onMounted, ref, watch, type ComputedRef, type Ref } from 'vue'
import { useElementWidth } from './useElementWidth'
import { usePanelMemory } from './useOverlayPanel'

/** What shape the navigation takes at the width it has been given. */
export type ShellLayout = 'sidebar' | 'rail' | 'drawer'

export interface ResponsiveShellOptions {
  /** Under this width the menu leaves the page for a drawer behind a burger. */
  phone?: number
  /** Under this width the sidebar starts as an icon rail. */
  tablet?: number
  /** Whether it starts as a rail on a screen wide enough for the full sidebar. */
  collapsed?: boolean
  /**
   * Remembers a sidebar that was closed by hand, under this key in `localStorage`.
   * Only the closing is kept: a sidebar left open goes back to following the width,
   * so a desktop opens it and a tablet still starts with the rail.
   */
  persist?: string
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

/** What `persist` keeps: a sidebar that was closed by hand, and nothing else. */
interface ShellMemory {
  collapsed: boolean
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
  const width = useElementWidth(target)
  const drawerOpen = ref(false)

  /*
   * What the reader last asked for, or `null` for "whatever the width suggests".
   * The two answers are not equal: closing a sidebar is a decision that holds at
   * every width and across reloads, while opening one only says "not here, not now"
   * — it is dropped as soon as the screen changes size class, and the width decides
   * again. Otherwise a sidebar opened on a desktop would be waiting on a tablet.
   */
  const preference = ref<boolean | null>(options.collapsed ?? null)

  const memory = usePanelMemory<ShellMemory>('wx-shell:', () => options.persist)

  const layout = computed(() => shellLayoutFor(width.value, preference.value, options))
  const collapsed = computed(() => layout.value !== 'sidebar')
  const showAside = computed(() => layout.value !== 'drawer')

  onMounted(() => {
    /* Read after mounting: the server has no storage, and the markup must match. */
    if (memory.read()?.collapsed) preference.value = true
  })

  /** Which size class the screen is in — what an open sidebar is remembered against. */
  const sizeClass = computed<ShellLayout>(() => shellLayoutFor(width.value, null, options))

  watch(sizeClass, () => {
    /* The screen changed class, so "open, here" has been answered and is let go. */
    if (preference.value === false) preference.value = null
    /* And once the menu is back on the page, the drawer has nothing left to show. */
    if (sizeClass.value !== 'drawer') drawerOpen.value = false
  })

  /**
   * The button does the one thing that is visible from where the reader is standing:
   * where the sidebar is on the page it collapses and expands it, and where the menu
   * is gone entirely it is the burger that brings the drawer back.
   */
  function toggle() {
    if (layout.value === 'drawer') {
      drawerOpen.value = !drawerOpen.value
      return
    }

    const closing = layout.value !== 'rail'
    preference.value = closing ? true : false

    /* Only the closing is worth keeping; an open sidebar is the default anyway. */
    if (closing) memory.write({ collapsed: true })
    else memory.clear()
  }

  function close() {
    drawerOpen.value = false
  }

  return { width, layout, collapsed, showAside, drawerOpen, toggle, close }
}
