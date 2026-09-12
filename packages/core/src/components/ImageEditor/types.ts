/** A rectangle of the picture, in the pixels of the image *after* its quarter turns. */
export interface ImageEditorCrop {
  x: number
  y: number
  width: number
  height: number
}

/**
 * A ratio the crop can be locked to: a number (width ÷ height), `free` for no lock, or
 * `original` for whatever the picture itself is.
 */
export type ImageEditorRatio = number | 'free' | 'original'

export interface ImageEditorRatioOption {
  value: ImageEditorRatio
  /** Derived from the number when it is left out — `1.7778` reads as `16:9`. */
  label?: string
}

/**
 * The adjustments, as percentages of what the picture already is: 100 leaves it alone.
 * They are the four a photograph for a website actually wants, and no more — a preset is
 * a name for a set of these numbers, and can be built on top without the editor knowing.
 */
export interface ImageEditorAdjustments {
  brightness: number
  contrast: number
  saturation: number
  /** Black and white, which overrules whatever the saturation is set to. */
  mono: boolean
}

/** What the editor hands over. Nothing is uploaded; this is a blob and its measurements. */
export interface ImageEditorResult {
  blob: Blob
  /** The same bytes named, ready to be posted straight to a server. */
  file: File
  /** MIME type of the blob. */
  type: string
  width: number
  height: number
  /**
   * The crop, in the pixels of the turned picture — turn by `rotation`, mirror by the
   * flips, then cut this out, and a server arrives at the same picture.
   */
  crop: ImageEditorCrop
  /** Quarter turns, clockwise: 0, 90, 180 or 270. */
  rotation: number
  flipX: boolean
  flipY: boolean
  /** The adjustments as they stood, and the CSS `filter` that says the same thing. */
  adjustments: ImageEditorAdjustments
  /** Empty when the picture was left alone. A server can apply the same numbers. */
  filter: string
}

export interface ImageEditorProps {
  /** The picture: a URL, or the `File` an upload field just handed you. */
  src: string | Blob
  /**
   * Name for the file in the result. Taken from `src` when it is a URL or a `File`, and
   * its extension is put right to match the format actually written.
   */
  fileName?: string
  /**
   * Locks the crop to one ratio and takes the picker away — `1` for an avatar. The
   * ratios offered stay if `ratios` is given as well.
   */
  aspect?: number
  /** The ratios offered. Empty hides the picker and leaves the crop free. */
  ratios?: (ImageEditorRatio | ImageEditorRatioOption)[]
  /** Which of them the editor opens on. Defaults to the first. */
  ratio?: ImageEditorRatio
  /** Quarter turns, left and right. */
  rotatable?: boolean
  /** Mirroring, across and down. */
  flippable?: boolean
  /** Offers the output size — the fields that scale the cut-out down before it is written. */
  resizable?: boolean
  /**
   * Offers the adjustments: brightness, contrast, saturation and black-and-white, behind
   * one button. Off by default, so an editor asked for as a cropper stays one.
   */
  filters?: boolean
  /** Largest output, in pixels. A bigger crop is scaled down to fit inside it. */
  maxWidth?: number
  maxHeight?: number
  /** Smallest crop, in the picture's own pixels. */
  minSize?: number
  /**
   * What to write. `auto` keeps the source's format where it is one a browser can write
   * (JPEG, PNG, WebP) and falls back to JPEG.
   */
  format?: 'auto' | 'image/jpeg' | 'image/png' | 'image/webp'
  /** 0 to 1, for the formats that have a quality. */
  quality?: number
  /**
   * Painted behind the picture when the format has no transparency, since transparent
   * turns black in a JPEG.
   */
  background?: string
  /**
   * `crossorigin` for the `<img>`. Anonymous by default: a canvas that has drawn a
   * picture from a host which sent no CORS header cannot be read back, so the export
   * would fail — this way it fails at loading, where it can be seen.
   */
  crossOrigin?: 'anonymous' | 'use-credentials' | ''
  /** The editor's own Cancel and Save. Off when the panel around it has its own. */
  footer?: boolean
  disabled?: boolean

  saveLabel?: string
  cancelLabel?: string
  resetLabel?: string
  rotateLeftLabel?: string
  rotateRightLabel?: string
  flipHorizontalLabel?: string
  flipVerticalLabel?: string
  /** Accessible name of the ratio picker, and of the crop box itself. */
  ratioLabel?: string
  cropLabel?: string
  /** The output-size row: its caption, the tip on that caption, and the two fields. */
  outputLabel?: string
  outputHint?: string
  widthLabel?: string
  heightLabel?: string
  /** The adjustments: the button, and the four things inside its panel. */
  adjustLabel?: string
  brightnessLabel?: string
  contrastLabel?: string
  saturationLabel?: string
  monoLabel?: string
  freeLabel?: string
  originalLabel?: string
  /** Shown in place of the picture when it will not load. */
  errorText?: string
}

export interface ImageEditorEmits {
  /** The picture, cut and turned. */
  save: [result: ImageEditorResult]
  cancel: []
  /** The picture loaded, at this size. */
  load: [size: { width: number; height: number }]
  /** It did not load, or could not be written. */
  error: [error: unknown]
  /** The crop moved or was resized, in the pixels of the turned picture. */
  crop: [crop: ImageEditorCrop]
}

/**
 * What `openImageEditor` takes: the editor's own props, and the panel it arrives in.
 *
 * A type rather than an interface so that it can be handed to `createModal`, which asks
 * for something a bag of props can be made of.
 */
export type ImageEditorModalProps = ImageEditorProps & {
  /** Heading of the panel. */
  title?: string
  /** Width of the panel: a number in pixels, or any CSS length. */
  width?: number | string
}
