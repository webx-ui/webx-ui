import type { TextAlign, TextTone } from '../Text/types'

export type HeadingLevel = 1 | 2 | 3 | 4 | 5 | 6
export type HeadingSize = 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl'

export interface HeadingProps {
  /** Which `<h*>` to render. Keep it truthful to the document outline. */
  level?: HeadingLevel
  /**
   * Visual size, when it should differ from the level — an `h3` that has to look
   * like a page title, or an `h1` that must stay small in a drawer.
   */
  size?: HeadingSize
  tone?: TextTone
  align?: TextAlign
  /** Cuts the heading off with an ellipsis after one line. */
  truncate?: boolean
}
