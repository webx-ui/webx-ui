import type { BuiltinIconName } from './icons'

/**
 * A built-in name, or any string once `registerIcons` has added your own — the
 * `(string & {})` arm keeps autocompletion for the built-ins while accepting the rest.
 */
export type IconName = BuiltinIconName | (string & {})

export type IconSize = 'sm' | 'md' | 'lg'

export interface IconProps {
  /** Name in the icon set. Unknown names render nothing. */
  name: IconName
  /**
   * Box size. A keyword follows the text scale; a number is pixels; any other string
   * is used as a CSS length. Defaults to `1em`, so an icon matches its surrounding text.
   */
  size?: IconSize | number | string
  /** Stroke width in the 24×24 grid. */
  strokeWidth?: number | string
  /** Spins the icon — for `loader` and `refresh` while something is in flight. */
  spin?: boolean
  /**
   * Accessible name. Without it the icon is `aria-hidden`, which is what you want
   * whenever there is a visible label beside it.
   */
  label?: string
}
