import type { ControlSize } from '../../composables/useFormField'

export type SliderModelValue = number | number[] | null

export interface SliderProps {
  min?: number
  max?: number
  step?: number
  /** Two thumbs and a range between them; the model becomes a pair. */
  range?: boolean
  /** Smallest gap between the thumbs, in steps. */
  minStepsBetweenThumbs?: number
  /** Show the current value (or both values) next to the track. */
  showValue?: boolean
  /** Ticks under the track: `{ 0: 'Free', 100: 'Max' }`. */
  marks?: Record<number, string>
  disabled?: boolean
  size?: ControlSize
  id?: string
  name?: string
  /** Accessible label used when there is no visible `<label>`. */
  ariaLabel?: string
}

export interface SliderEmits {
  change: [value: SliderModelValue]
}
