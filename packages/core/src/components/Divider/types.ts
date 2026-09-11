export type DividerDirection = 'horizontal' | 'vertical'
export type DividerVariant = 'solid' | 'dashed' | 'dotted'
export type DividerAlign = 'start' | 'center' | 'end'
export type DividerSpacing = 'none' | 'sm' | 'md' | 'lg'

export interface DividerProps {
  /** A rule across the flow, or a hairline between two things on one line. */
  direction?: DividerDirection
  /** Line style. */
  variant?: DividerVariant
  /** Where the label sits on the line. Ignored without a label. */
  align?: DividerAlign
  /** Margin around the rule. */
  spacing?: DividerSpacing
  /** Label on the line, when a slot would be overkill. */
  label?: string
}
