import type { ThemePreference } from '@webx-ui/tokens'
import type { ControlSize } from '../../composables/useFormField'

export type { ThemePreference }

export interface ThemeSwitchProps {
  size?: ControlSize
  /** Stretches to the width it is given, the three sharing it equally. */
  block?: boolean
  disabled?: boolean
  /** Accessible name for the group. */
  ariaLabel?: string
  /**
   * The three names. They are read out, shown on hover and nowhere else — the pictures are
   * the control — but they are the only thing that says what "system" means, so they are
   * worth translating.
   */
  lightLabel?: string
  darkLabel?: string
  systemLabel?: string
}

export interface ThemeSwitchEmits {
  change: [value: ThemePreference]
}
