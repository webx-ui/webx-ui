export type ButtonType = 'default' | 'primary' | 'success' | 'warning' | 'danger'
export type ButtonVariant = 'solid' | 'outline' | 'text'
export type ButtonSize = 'sm' | 'md' | 'lg'
export type ButtonNativeType = 'button' | 'submit' | 'reset'

export interface ButtonProps {
  /** Semantic colour of the button. Defaults to `default`, or to the group's. */
  type?: ButtonType
  /** Visual weight: filled, bordered or borderless. Defaults to `solid`, or to the group's. */
  variant?: ButtonVariant
  /** Defaults to `md`, or to the enclosing `WxButtonGroup`. */
  size?: ButtonSize
  disabled?: boolean
  /** Shows a spinner and blocks interaction. */
  loading?: boolean
  /** Stretches the button to the full width of its container. */
  block?: boolean
  round?: boolean
  /** Renders an `<a>` instead of a `<button>`. */
  href?: string
  target?: string
  /** `type` attribute of the underlying `<button>`. */
  nativeType?: ButtonNativeType
}

export interface ButtonEmits {
  click: [event: MouseEvent]
}
