export type CardShadow = 'never' | 'hover' | 'always'
export type CardPadding = 'none' | 'sm' | 'md' | 'lg'

export interface CardProps {
  /** Header text — ignored when the `header` slot is used. */
  title?: string
  /** When the card should cast a shadow. */
  shadow?: CardShadow
  /** Inner padding of the body (and of header/footer). */
  padding?: CardPadding
  /** Removes the outer border, keeping only the surface colour. */
  borderless?: boolean
}
