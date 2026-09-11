export interface AffixProps {
  /** How far from the edge it comes to rest, in pixels. */
  offset?: number
  /** Which edge it sticks to. */
  position?: 'top' | 'bottom'
  /** Puts the element back in the flow, whatever the scroll is doing. */
  disabled?: boolean
  /** Layer it sits on once it is stuck. */
  zIndex?: number
}

export interface AffixEmits {
  /** Fires when it sticks, and again when it lets go. */
  change: [stuck: boolean]
}
