export type SelectionValue = string | number

export type SelectionMatch = 'intersect' | 'contain'

export interface SelectionAreaProps {
  /**
   * Whether more than one item may be held at once. Off, the model never holds more than
   * a single value: there is no box, no run and no toggle, and a click or a tap picks the
   * item under it. A gallery wants several; a file picker wants one.
   */
  multiple?: boolean
  /** Whether the box has to cover an item or merely touch it. */
  match?: SelectionMatch
  /** How far the pointer travels before it is a drag rather than a click, in pixels. */
  threshold?: number
  /** A click picks one item; a click on the background clears the selection. */
  clickSelect?: boolean
  /**
   * Draw the box with a finger too, at the cost of the area scrolling with one. Off,
   * because on a touch screen a drag means scroll — a tap still picks either way.
   */
  touch?: boolean
  /** How near the scroller's edge the pointer scrolls it, in pixels. `0` never does. */
  edgeScroll?: number
  disabled?: boolean
}

export interface SelectionAreaEmits {
  /** The pointer has moved past the threshold and a box is being drawn. */
  start: []
  /** The drag is over. Carries the selection it ended with. */
  end: [value: SelectionValue[]]
}

export interface SelectionAreaSlotProps {
  selected: Set<SelectionValue>
  isSelected: (value: SelectionValue) => boolean
  selecting: boolean
}
