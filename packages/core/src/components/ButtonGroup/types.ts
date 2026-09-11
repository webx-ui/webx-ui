import type { ButtonSize, ButtonType, ButtonVariant } from '../Button/types'

export interface ButtonGroupProps {
  /** Colour handed to every button that does not set its own. */
  type?: ButtonType
  /** Variant handed to every button that does not set its own. */
  variant?: ButtonVariant
  /** Size handed to every button that does not set its own. */
  size?: ButtonSize
  /** Disables every button in the group. */
  disabled?: boolean
  /** Stacks the buttons instead of lining them up. */
  vertical?: boolean
  /**
   * Joins the buttons into one segmented control — shared borders, rounded only at
   * the ends. Turn it off for a plain row of separate buttons.
   */
  attached?: boolean
  /** Accessible name for the group, e.g. "Row actions". */
  ariaLabel?: string
}
