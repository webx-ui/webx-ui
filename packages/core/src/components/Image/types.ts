export type ImageFit = 'cover' | 'contain' | 'fill' | 'none' | 'scale-down'

export interface ImageProps {
  src?: string
  alt?: string
  /** How the picture fills its box. */
  fit?: ImageFit
  width?: number | string
  height?: number | string
  /** Shape of the box: any CSS length, or a keyword from the radius scale. */
  radius?: number | string
  /**
   * Waits until the picture is near the screen before fetching it. On by default:
   * a media library is a hundred pictures and a reader looks at four.
   */
  lazy?: boolean
  /** A tiny picture — or a colour — shown while the real one arrives. */
  placeholder?: string
  /** Opens the picture full size when it is clicked. */
  preview?: boolean
  /** Accessible name of the button that opens the preview. */
  previewLabel?: string
}

export interface ImageEmits {
  load: [event: Event]
  error: [event: Event]
}
