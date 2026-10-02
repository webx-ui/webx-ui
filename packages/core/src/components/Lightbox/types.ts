/**
 * A video on a gallery item. The item's own picture is its poster, and nothing is fetched
 * from the video's host until somebody presses play.
 */
export interface LightboxVideo {
  /** A file the browser plays itself — MP4 or WebM. */
  src?: string
  /** An address for an iframe: a YouTube embed, or anything else that plays in one. Wins over `src`. */
  embed?: string
}

export interface LightboxItem {
  /** The picture shown — and, when there is a `video`, its poster. */
  src?: string
  /** Alternative text, and the caption when there is no `caption`. */
  alt?: string
  /** Text under the picture. */
  caption?: string
  /** Where "Open the original" goes: a bigger file than the one shown. Default: `src`. */
  original?: string
  /** A small version, for the strip and for the moment before `src` arrives. Default: `src`. */
  thumb?: string
  /**
   * Makes the item a video. A string is a YouTube link (any of its forms) or a file;
   * an object says which it is.
   */
  video?: string | LightboxVideo
}

/** An item, or just the address of a picture. */
export type LightboxSource = string | LightboxItem

export interface LightboxProps {
  items: LightboxSource[]
  /** Going past the last item comes back to the first. */
  loop?: boolean
  /** A strip of thumbnails along the bottom. Default: on when there is more than one item. */
  thumbnails?: boolean
  /** How far a picture can be enlarged, as a multiple of its size on screen. */
  maxZoom?: number
  /** The "Open the original" link. */
  original?: boolean
  /** Accessible name of the gallery. */
  ariaLabel?: string
  prevLabel?: string
  nextLabel?: string
  closeLabel?: string
  zoomInLabel?: string
  zoomOutLabel?: string
  originalLabel?: string
  playLabel?: string
  /** `{index}` and `{total}` are replaced; a function is given both. */
  counterText?: string | ((index: number, total: number) => string)
}

export interface LightboxEmits {
  /** The item on screen changed — by an arrow, a key, a swipe or a thumbnail. */
  change: [index: number]
}

/** What `openLightbox` takes besides the items. */
export interface OpenLightboxOptions extends Omit<LightboxProps, 'items'> {
  /** The item to open on. */
  start?: number
}
