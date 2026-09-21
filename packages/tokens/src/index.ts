export { tokens, lightVars, darkVars } from './generated/tokens'

/** `cssVar('color-primary')` -> `var(--wx-color-primary)`. */
export function cssVar(name: string, fallback?: string): string {
  return fallback ? `var(--wx-${name}, ${fallback})` : `var(--wx-${name})`
}

export type Theme = 'light' | 'dark'

/**
 * What somebody chose, which is not the same thing as what is on screen: `system` is a
 * standing instruction to follow the machine, and it resolves to one of the two themes anew
 * every time the machine changes its mind.
 */
export type ThemePreference = Theme | 'system'

const QUERY = '(prefers-color-scheme: dark)'

/** Sets `data-theme` on an element (defaults to `<html>`). */
export function applyTheme(theme: ThemePreference, element?: HTMLElement): void {
  const target = element ?? (typeof document === 'undefined' ? undefined : document.documentElement)

  if (target === undefined) {
    return
  }

  /*
   * `system` is the absence of an instruction rather than a third value to write down: the
   * stylesheet follows `prefers-color-scheme` for everything that is not `data-theme="light"`,
   * so the way to say "whatever the machine says" is to say nothing at all. On a nested
   * element that means inheriting whatever the element above it was given, which is the right
   * answer there too — a dark sidebar inside a light panel is a choice somebody made about the
   * sidebar, not about the machine.
   */
  if (theme === 'system') {
    target.removeAttribute('data-theme')
  } else {
    target.setAttribute('data-theme', theme)
  }
}

/** Which of the two themes the machine is asking for right now. */
export function systemTheme(): Theme {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
    return 'light'
  }

  return window.matchMedia(QUERY).matches ? 'dark' : 'light'
}

/**
 * Calls back whenever the machine changes its mind, and returns the way to stop listening.
 *
 * The stylesheet needs none of this — it follows the media query on its own. This is for
 * anything that has to *know*: a canvas that paints its own background, an editor handed a
 * colour scheme, a picture with two versions.
 */
export function watchSystemTheme(listener: (theme: Theme) => void): () => void {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
    return () => {}
  }

  const query = window.matchMedia(QUERY)
  const handle = (event: MediaQueryListEvent): void => listener(event.matches ? 'dark' : 'light')

  query.addEventListener('change', handle)

  return () => query.removeEventListener('change', handle)
}
