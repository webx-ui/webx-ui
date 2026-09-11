export type LoadingSize = 'sm' | 'md' | 'lg'

export interface LoadingProps {
  /** Whether the veil is up. */
  loading?: boolean
  /** What is being waited for. Shown under the spinner. */
  text?: string
  size?: LoadingSize
  /**
   * Covers the whole window rather than the box it sits in. For a route change,
   * not for a panel refreshing.
   */
  fullscreen?: boolean
  /**
   * How long the work may take before the veil appears, in milliseconds. Anything
   * that answers quickly should not flash a spinner on the way.
   */
  delay?: number
  /**
   * Blurs what is underneath instead of only fading it. Reads as "this is on its
   * way out" rather than "this is broken".
   */
  blur?: boolean
  /** Accessible description of the wait. */
  ariaLabel?: string
}
