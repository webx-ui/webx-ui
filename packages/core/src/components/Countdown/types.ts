import type { StatisticSize } from '../Statistic/types'
import type { TextAlign, TextTone } from '../Text/types'

/** When the countdown reaches zero: a timestamp, a `Date`, or anything `Date` parses. */
export type CountdownTarget = number | string | Date

export interface CountdownProps {
  /** The moment being counted down to. */
  value: CountdownTarget
  /**
   * Pattern built from `DD`, `HH`, `mm`, `ss` and `SSS`. Only the tokens present are
   * filled, so `mm:ss` counts the whole span in minutes rather than dropping the hours.
   */
  format?: string
  /** How often the display is refreshed, in milliseconds. */
  interval?: number
  title?: string
  prefix?: string
  suffix?: string
  size?: StatisticSize
  tone?: TextTone
  align?: TextAlign
}

export interface CountdownEmits {
  /** Milliseconds left, on every tick. */
  change: [remaining: number]
  /** Fired once, when the target time is reached. */
  finish: []
}
