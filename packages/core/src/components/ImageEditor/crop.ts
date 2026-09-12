import type { ImageEditorCrop } from './types'

/** The eight grips, named after the compass the way every editor names them. */
export type CropHandle = 'nw' | 'n' | 'ne' | 'e' | 'se' | 's' | 'sw' | 'w'

export interface CropBounds {
  width: number
  height: number
}

/*
 * All of this works in the pixels of the picture *after* its quarter turns — the space
 * the crop is stored in. The screen only ever comes in as a scale factor, so the maths
 * here is the same whether the editor is 300 pixels wide or 3000.
 */

export function clamp(value: number, min: number, max: number) {
  return Math.min(Math.max(value, min), max)
}

/** `1.7778` → `16:9`. Falls back to a decimal for a ratio that is nobody's convention. */
export function ratioLabelFor(value: number) {
  for (let denominator = 1; denominator <= 16; denominator += 1) {
    const numerator = value * denominator
    if (Math.abs(numerator - Math.round(numerator)) < 0.001) {
      return `${Math.round(numerator)}:${denominator}`
    }
  }
  return value.toFixed(2)
}

/** The whole picture, or the largest rectangle of `ratio` in the middle of it. */
export function fullCrop(bounds: CropBounds, ratio?: number): ImageEditorCrop {
  const crop = { x: 0, y: 0, width: bounds.width, height: bounds.height }
  return ratio ? fitRatio(crop, ratio, bounds) : crop
}

/**
 * The largest rectangle of `ratio` that fits inside `crop`, kept where `crop` was — used
 * when the ratio changes and when a turn leaves the old crop the wrong shape.
 */
export function fitRatio(
  crop: ImageEditorCrop,
  ratio: number,
  bounds: CropBounds,
): ImageEditorCrop {
  const centreX = crop.x + crop.width / 2
  const centreY = crop.y + crop.height / 2

  let width = crop.width
  let height = width / ratio
  if (height > crop.height) {
    height = crop.height
    width = height * ratio
  }

  /* It can still be wider than the picture — a 16:9 asked of a tall crop, say. */
  const shrink = Math.min(1, bounds.width / width, bounds.height / height)
  width *= shrink
  height *= shrink

  return {
    x: clamp(centreX - width / 2, 0, bounds.width - width),
    y: clamp(centreY - height / 2, 0, bounds.height - height),
    width,
    height,
  }
}

/** Dragged from inside: the crop slides, and stops at the edges of the picture. */
export function moveCrop(
  start: ImageEditorCrop,
  dx: number,
  dy: number,
  bounds: CropBounds,
): ImageEditorCrop {
  return {
    ...start,
    x: clamp(start.x + dx, 0, bounds.width - start.width),
    y: clamp(start.y + dy, 0, bounds.height - start.height),
  }
}

/**
 * Dragged by a grip.
 *
 * The opposite corner — or the opposite edge, for the four in the middle — is the anchor
 * and does not move; everything else follows the pointer. With a ratio the rectangle is
 * brought back to that ratio before it is fitted inside the picture, so a locked crop
 * cannot be nudged out of shape by dragging it into a corner.
 */
export function resizeCrop(
  handle: CropHandle,
  point: { x: number; y: number },
  start: ImageEditorCrop,
  bounds: CropBounds,
  { ratio, min = 1 }: { ratio?: number; min?: number } = {},
): ImageEditorCrop {
  const dirX = handle.includes('w') ? -1 : handle.includes('e') ? 1 : 0
  const dirY = handle.includes('n') ? -1 : handle.includes('s') ? 1 : 0

  /* The point that stays put while the grip is dragged. */
  const anchorX = dirX > 0 ? start.x : dirX < 0 ? start.x + start.width : start.x + start.width / 2
  const anchorY =
    dirY > 0 ? start.y : dirY < 0 ? start.y + start.height : start.y + start.height / 2

  let width = dirX ? Math.abs(point.x - anchorX) : start.width
  let height = dirY ? Math.abs(point.y - anchorY) : start.height

  if (ratio) {
    /* An edge grip drives one side and the ratio decides the other; a corner is led by
     * whichever way it was dragged furthest. */
    if (!dirX) width = height * ratio
    else if (!dirY) height = width / ratio
    else if (width / height > ratio) height = width / ratio
    else width = height * ratio
  }

  /* How far the rectangle may grow before it leaves the picture. A grip in the middle of
   * an edge grows both ways around its anchor, so it runs out at the nearer side. */
  const roomX =
    dirX > 0
      ? bounds.width - anchorX
      : dirX < 0
        ? anchorX
        : 2 * Math.min(anchorX, bounds.width - anchorX)
  const roomY =
    dirY > 0
      ? bounds.height - anchorY
      : dirY < 0
        ? anchorY
        : 2 * Math.min(anchorY, bounds.height - anchorY)

  if (ratio) {
    const shrink = Math.min(1, roomX / width, roomY / height)
    width *= shrink
    height *= shrink

    const grow = Math.max(1, min / width, min / height)
    width = Math.min(width * grow, roomX)
    height = Math.min(height * grow, roomY)
  } else {
    width = clamp(width, Math.min(min, roomX), roomX)
    height = clamp(height, Math.min(min, roomY), roomY)
  }

  const x = dirX > 0 ? anchorX : dirX < 0 ? anchorX - width : anchorX - width / 2
  const y = dirY > 0 ? anchorY : dirY < 0 ? anchorY - height : anchorY - height / 2

  return {
    x: clamp(x, 0, bounds.width - width),
    y: clamp(y, 0, bounds.height - height),
    width,
    height,
  }
}

/**
 * The crop follows the picture through a quarter turn, so what was framed stays framed.
 * `bounds` is the space *before* the turn; the space after it has its sides swapped.
 */
export function turnCrop(
  crop: ImageEditorCrop,
  bounds: CropBounds,
  direction: 1 | -1,
): ImageEditorCrop {
  return direction === 1
    ? {
        x: bounds.height - (crop.y + crop.height),
        y: crop.x,
        width: crop.height,
        height: crop.width,
      }
    : {
        x: crop.y,
        y: bounds.width - (crop.x + crop.width),
        width: crop.height,
        height: crop.width,
      }
}

/** The same, for a mirroring. */
export function mirrorCrop(
  crop: ImageEditorCrop,
  bounds: CropBounds,
  axis: 'x' | 'y',
): ImageEditorCrop {
  return axis === 'x'
    ? { ...crop, x: bounds.width - (crop.x + crop.width) }
    : { ...crop, y: bounds.height - (crop.y + crop.height) }
}

/** The largest whole-pixel size of the same shape that fits within the caps. */
export function fitInside(width: number, height: number, maxWidth?: number, maxHeight?: number) {
  const shrink = Math.min(1, maxWidth ? maxWidth / width : 1, maxHeight ? maxHeight / height : 1)
  return {
    width: Math.max(1, Math.round(width * shrink)),
    height: Math.max(1, Math.round(height * shrink)),
  }
}
