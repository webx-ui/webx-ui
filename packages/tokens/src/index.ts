export { tokens, lightVars, darkVars } from './generated/tokens'

/** `cssVar('color-primary')` -> `var(--wx-color-primary)`. */
export function cssVar(name: string, fallback?: string): string {
  return fallback ? `var(--wx-${name}, ${fallback})` : `var(--wx-${name})`
}

export type Theme = 'light' | 'dark'

/** Sets `data-theme` on an element (defaults to `<html>`). */
export function applyTheme(theme: Theme, element?: HTMLElement): void {
  const target = element ?? (typeof document === 'undefined' ? undefined : document.documentElement)
  target?.setAttribute('data-theme', theme)
}
