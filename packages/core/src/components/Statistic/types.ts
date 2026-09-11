import type { TextAlign, TextTone } from '../Text/types'

export type StatisticSize = 'sm' | 'md' | 'lg'

export interface StatisticProps {
  /** The number itself. A string is printed as-is — already formatted upstream. */
  value?: number | string
  /** Caption above the number. */
  title?: string
  /** Digits after the decimal point. Left alone, the number keeps its own. */
  precision?: number
  /** Locale for grouping and the decimal mark. Defaults to the browser's. */
  locale?: string
  /** Thousands grouping. */
  grouping?: boolean
  /** Short text before the number — a currency sign, an arrow. */
  prefix?: string
  /** Short text after the number — a unit, a percent sign. */
  suffix?: string
  /** Full control over the printed value; wins over `precision` and `grouping`. */
  formatter?: (value: number | string | undefined) => string
  size?: StatisticSize
  /** Colour of the number — `success` for growth, `danger` for a drop. */
  tone?: TextTone
  align?: TextAlign
}
