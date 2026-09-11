export type BadgeType = 'default' | 'primary' | 'success' | 'warning' | 'danger' | 'info'
export type BadgeVariant = 'soft' | 'solid' | 'outline'
export type BadgeSize = 'sm' | 'md' | 'lg'

export interface BadgeProps {
  /** Semantic colour of the badge. */
  type?: BadgeType
  /** Visual weight: tinted, filled or bordered. */
  variant?: BadgeVariant
  size?: BadgeSize
  /** Pill shape instead of the default rounded rectangle. */
  round?: boolean
  /** Shows a coloured dot before the label — a status badge rather than a tag. */
  dot?: boolean
  /** Adds a × that emits `close`; the caller decides what to remove. */
  closable?: boolean
  /** Label for the close button, for screen readers. */
  closeLabel?: string
}

export interface BadgeEmits {
  close: [event: MouseEvent]
}
