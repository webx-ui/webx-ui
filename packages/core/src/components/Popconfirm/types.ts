import type { ButtonType } from '../Button/types'
import type { IconName } from '../Icon/types'
import type { PopoverAlign, PopoverSide } from '../Popover/types'

export interface PopconfirmProps {
  /** The question. Keep it about what will happen, not about the button. */
  title?: string
  /** What else the reader should know before answering. */
  description?: string
  /** Label of the button that goes ahead. */
  confirmText?: string
  /** Label of the button that does not. */
  cancelText?: string
  /** Colour of the confirming button — `danger` for anything that destroys. */
  confirmType?: ButtonType
  /** The glyph beside the question. `false` drops it. */
  icon?: IconName | false
  side?: PopoverSide
  align?: PopoverAlign
  /** Distance from the trigger, in pixels. */
  offset?: number
  /** Draws the little pointer at the trigger. */
  arrow?: boolean
  /** Width of the panel: a number in pixels, or any CSS length. */
  width?: number | string
  /** The trigger stops asking anything. */
  disabled?: boolean
  /** Keeps the panel open and the confirming button busy — for an async answer. */
  loading?: boolean
}

export interface PopconfirmEmits {
  confirm: []
  cancel: []
}
