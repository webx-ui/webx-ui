/** What shape the placeholder stands in for. */
export type SkeletonVariant = 'text' | 'title' | 'circle' | 'square' | 'image' | 'button'

export interface SkeletonProps {
  /**
   * Whether the placeholder is showing. Once it is off, the default slot is what
   * renders — so the same markup covers both states and the caller writes one `v-if`
   * fewer.
   */
  loading?: boolean
  /** How many lines of text to stand in for. The last one is drawn short. */
  rows?: number
  /** Adds a circle beside the lines, for a row that begins with a face. */
  avatar?: boolean
  /** Draws the first line heavier, as a heading. */
  title?: boolean
  /** The shimmer. Off for anything that will be on screen for a long time. */
  animated?: boolean
  /**
   * How long the content may take before the placeholder appears, in milliseconds.
   * A request that answers in 80ms should not flash a skeleton on the way.
   */
  delay?: number
}

export interface SkeletonItemProps {
  variant?: SkeletonVariant
  /** Any CSS length, or pixels as a number. */
  width?: string | number
  height?: string | number
  /** Follows the enclosing `WxSkeleton` unless set here. */
  animated?: boolean
}
