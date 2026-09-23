import { inject, reactive, type App, type InjectionKey } from 'vue'
import {
  applyTheme,
  systemTheme,
  watchSystemTheme,
  type Theme,
  type ThemePreference,
} from '@webx-ui/tokens'

export type { Theme, ThemePreference }

const STORED_THEME = 'webx.theme'

export interface ThemeState {
  /** What was chosen: one of the two themes, or an instruction to follow the machine. */
  preference: ThemePreference
  /** What that works out to at this moment — what is actually on the screen. */
  resolved: Theme
}

export interface ThemeController {
  readonly state: ThemeState
  /**
   * Choose, paint and remember in this browser.
   *
   * Writing the choice down against the person is somebody else's job: this package knows
   * nothing about accounts, and a panel with no sign-in still deserves a theme.
   */
  set(preference: ThemePreference): void
  /**
   * Take up what the administrator's record says, without writing anything back to it.
   *
   * `null` is an answer — "follow the machine" is what somebody chose, and it has to travel
   * between their machines like any other choice. `undefined` is not: it is a server that
   * has never heard of themes, and the browser's own choice should survive it.
   */
  adopt(preference: ThemePreference | null | undefined): void
}

export const themeKey: InjectionKey<ThemeController> = Symbol('webx-theme')

export function useTheme(): ThemeController {
  const theme = inject(themeKey, null)

  if (theme === null) {
    throw new Error('useTheme() was called outside a panel created by createAdmin().')
  }

  return theme
}

export function provideTheme(app: App, theme: ThemeController): void {
  app.provide(themeKey, theme)
}

export interface CreateThemeOptions {
  /**
   * Where the choice is kept in this browser, or `null` to keep it nowhere. It is read before
   * the panel paints anything, so the sign-in screen is already the right colour — the copy
   * that follows somebody between machines is the one on their account.
   */
  storageKey?: string | null
}

export function createTheme(options: CreateThemeOptions = {}): ThemeController {
  const key = options.storageKey === undefined ? STORED_THEME : options.storageKey

  const state = reactive<ThemeState>({
    preference: read(key) ?? 'system',
    resolved: 'light',
  })

  function paint(): void {
    applyTheme(state.preference)
    state.resolved = state.preference === 'system' ? systemTheme() : state.preference
  }

  paint()

  /*
   * The stylesheet follows the machine on its own — nothing here has to repaint. This is so
   * that anything *reading* `resolved`, to paint a canvas or hand an editor a colour scheme,
   * hears about a room whose lights just went out.
   */
  watchSystemTheme((theme) => {
    if (state.preference === 'system') {
      state.resolved = theme
    }
  })

  return {
    state,
    set(preference) {
      state.preference = preference
      paint()
      remember(key, preference)
    },
    adopt(preference) {
      if (preference === undefined) {
        return
      }

      const chosen = preference ?? 'system'

      if (chosen === state.preference) {
        return
      }

      state.preference = chosen
      paint()
      remember(key, chosen)
    },
  }
}

function remember(key: string | null, preference: ThemePreference): void {
  if (key === null) {
    return
  }

  try {
    localStorage.setItem(key, preference)
  } catch {
    // Private windows, blocked site data. A theme is not worth an error.
  }
}

function read(key: string | null): ThemePreference | null {
  if (key === null) {
    return null
  }

  try {
    const stored = localStorage.getItem(key)

    return stored === 'light' || stored === 'dark' || stored === 'system' ? stored : null
  } catch {
    return null
  }
}
