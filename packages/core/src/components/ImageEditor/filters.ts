import type { ImageEditorAdjustments } from './types'

/** Nothing done to the picture: the reading every slider starts at. */
export const NEUTRAL: ImageEditorAdjustments = {
  brightness: 100,
  contrast: 100,
  saturation: 100,
  mono: false,
}

export function isNeutral(adjust: ImageEditorAdjustments) {
  return (
    adjust.brightness === NEUTRAL.brightness &&
    adjust.contrast === NEUTRAL.contrast &&
    adjust.saturation === NEUTRAL.saturation &&
    adjust.mono === NEUTRAL.mono
  )
}

/**
 * The adjustments as a CSS `filter` — the same string the `<img>` is shown through and
 * the canvas is drawn under, which is what keeps the preview honest.
 *
 * Order matters and is the order a reader expects: lighten, then stretch the contrast,
 * then the colour, and black-and-white last so that it wins over whatever the saturation
 * slider was left at.
 */
export function filterString(adjust: ImageEditorAdjustments) {
  const parts: string[] = []
  if (adjust.brightness !== 100) parts.push(`brightness(${adjust.brightness}%)`)
  if (adjust.contrast !== 100) parts.push(`contrast(${adjust.contrast}%)`)
  if (adjust.saturation !== 100 && !adjust.mono) parts.push(`saturate(${adjust.saturation}%)`)
  if (adjust.mono) parts.push('grayscale(1)')
  return parts.join(' ')
}

/** What the eye reads as brightness — the weights CSS itself uses for `saturate`. */
function luminance(r: number, g: number, b: number) {
  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

function clampByte(value: number) {
  return value < 0 ? 0 : value > 255 ? 255 : value
}

/**
 * The same arithmetic by hand, for a browser whose canvas has no `filter`.
 *
 * Safari only learned `ctx.filter` in 16.4, and where it is missing it is missing
 * silently: the picture on screen is adjusted, the file that comes out is not. Doing the
 * pass ourselves is the difference between a wrong file and a slow one.
 *
 * Written over the pixels in place, in the order `filterString` composes them.
 */
export function applyAdjustments(pixels: Uint8ClampedArray, adjust: ImageEditorAdjustments) {
  const brightness = adjust.brightness / 100
  const contrast = adjust.contrast / 100
  const saturation = adjust.mono ? 0 : adjust.saturation / 100

  for (let index = 0; index < pixels.length; index += 4) {
    let r = pixels[index]!
    let g = pixels[index + 1]!
    let b = pixels[index + 2]!

    if (brightness !== 1) {
      r *= brightness
      g *= brightness
      b *= brightness
    }

    /* Contrast pivots on mid-grey, which is what makes it contrast and not brightness. */
    if (contrast !== 1) {
      r = (r - 127.5) * contrast + 127.5
      g = (g - 127.5) * contrast + 127.5
      b = (b - 127.5) * contrast + 127.5
    }

    if (saturation !== 1) {
      const grey = luminance(r, g, b)
      r = grey + (r - grey) * saturation
      g = grey + (g - grey) * saturation
      b = grey + (b - grey) * saturation
    }

    pixels[index] = clampByte(r)
    pixels[index + 1] = clampByte(g)
    pixels[index + 2] = clampByte(b)
  }
}
