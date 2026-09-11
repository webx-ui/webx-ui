export type TextSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl'
export type TextWeight = 'regular' | 'medium' | 'semibold' | 'bold'
export type TextTone =
  | 'default'
  | 'strong'
  | 'muted'
  | 'placeholder'
  | 'primary'
  | 'success'
  | 'warning'
  | 'danger'
  | 'info'
  | 'inverse'
export type TextAlign = 'start' | 'center' | 'end' | 'justify'

export interface TextProps {
  /** Element to render. Use `p` for a paragraph, `label` beside a control, and so on. */
  as?: string
  size?: TextSize
  weight?: TextWeight
  /** Colour role, taken from the text tokens. */
  tone?: TextTone
  align?: TextAlign
  /**
   * Cuts the text off with an ellipsis: `true` on one line, a number over that many
   * lines. The element needs a width to cut against.
   */
  truncate?: boolean | number
  italic?: boolean
  /** Tabular text — ids, hashes, prices in a column. */
  mono?: boolean
}
