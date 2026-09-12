import { describe, expect, it } from 'vitest'
import {
  fitInside,
  fitRatio,
  fullCrop,
  mirrorCrop,
  moveCrop,
  ratioLabelFor,
  resizeCrop,
  turnCrop,
} from './crop'

/*
 * The geometry, on its own. It is the half of the editor that can be checked without a
 * browser — jsdom lays nothing out, so a dragged handle there proves only that the maths
 * ran, which is exactly what these check.
 */

const bounds = { width: 800, height: 600 }

describe('ratioLabelFor', () => {
  it('names the ratios people name', () => {
    expect(ratioLabelFor(1)).toBe('1:1')
    expect(ratioLabelFor(4 / 3)).toBe('4:3')
    expect(ratioLabelFor(16 / 9)).toBe('16:9')
    expect(ratioLabelFor(2.35)).toBe('2.35')
  })
})

describe('fullCrop', () => {
  it('is the whole picture when nothing is asked of it', () => {
    expect(fullCrop(bounds)).toEqual({ x: 0, y: 0, width: 800, height: 600 })
  })

  it('is the largest rectangle of the ratio, in the middle', () => {
    expect(fullCrop(bounds, 1)).toEqual({ x: 100, y: 0, width: 600, height: 600 })
  })
})

describe('fitRatio', () => {
  it('keeps the crop where it was', () => {
    const crop = { x: 200, y: 100, width: 400, height: 400 }
    const next = fitRatio(crop, 2, bounds)

    expect(next.width / next.height).toBeCloseTo(2)
    expect(next.x + next.width / 2).toBeCloseTo(400)
    expect(next.y + next.height / 2).toBeCloseTo(300)
  })

  it('stays inside the picture', () => {
    const next = fitRatio({ x: 0, y: 0, width: 800, height: 600 }, 4, bounds)

    expect(next.width).toBeLessThanOrEqual(800)
    expect(next.height).toBeLessThanOrEqual(600)
    expect(next.x).toBeGreaterThanOrEqual(0)
  })
})

describe('moveCrop', () => {
  it('slides by the drag', () => {
    const next = moveCrop({ x: 100, y: 100, width: 200, height: 200 }, 50, -30, bounds)

    expect(next).toEqual({ x: 150, y: 70, width: 200, height: 200 })
  })

  it('stops at the edge instead of leaving the picture', () => {
    const next = moveCrop({ x: 700, y: 500, width: 200, height: 200 }, 500, 500, bounds)

    expect(next).toEqual({ x: 600, y: 400, width: 200, height: 200 })
  })
})

describe('resizeCrop', () => {
  const crop = { x: 200, y: 150, width: 400, height: 300 }

  it('drags a corner and leaves the opposite one where it was', () => {
    const next = resizeCrop('se', { x: 500, y: 350 }, crop, bounds)

    expect(next).toEqual({ x: 200, y: 150, width: 300, height: 200 })
  })

  it('drags the other corner the other way', () => {
    const next = resizeCrop('nw', { x: 300, y: 250 }, crop, bounds)

    expect(next).toEqual({ x: 300, y: 250, width: 300, height: 200 })
  })

  it('leaves the other side alone when an edge is dragged', () => {
    const next = resizeCrop('e', { x: 700, y: 0 }, crop, bounds)

    expect(next).toEqual({ x: 200, y: 150, width: 500, height: 300 })
  })

  it('holds the ratio a corner is dragged under', () => {
    const next = resizeCrop('se', { x: 700, y: 200 }, crop, bounds, { ratio: 1 })

    expect(next.width).toBeCloseTo(next.height)
    expect(next.x).toBe(200)
    expect(next.y).toBe(150)
  })

  it('holds it through an edge, which then grows both ways', () => {
    const start = { x: 200, y: 150, width: 400, height: 200 }
    const next = resizeCrop('s', { x: 0, y: 450 }, start, bounds, { ratio: 2 })

    expect(next.height).toBeCloseTo(300)
    expect(next.width).toBeCloseTo(600)
    /* Centred on where it was, since the grip in the middle of an edge has no side. */
    expect(next.x + next.width / 2).toBeCloseTo(400)
    expect(next.y).toBe(150)
  })

  it('stops at the edge of the picture, ratio and all', () => {
    const next = resizeCrop('se', { x: 5000, y: 5000 }, crop, bounds, { ratio: 1 })

    expect(next.width).toBeCloseTo(next.height)
    expect(next.x + next.width).toBeLessThanOrEqual(800)
    expect(next.y + next.height).toBeCloseTo(600)
  })

  it('will not be dragged smaller than the minimum', () => {
    const next = resizeCrop('se', { x: 201, y: 151 }, crop, bounds, { min: 40 })

    expect(next.width).toBe(40)
    expect(next.height).toBe(40)
  })

  it('keeps the minimum without losing the ratio', () => {
    const next = resizeCrop('se', { x: 201, y: 151 }, crop, bounds, { ratio: 2, min: 40 })

    expect(next.height).toBeCloseTo(40)
    expect(next.width).toBeCloseTo(80)
  })
})

describe('turnCrop', () => {
  it('takes the crop round with the picture', () => {
    const crop = { x: 0, y: 0, width: 200, height: 100 }

    /* Turned right, the top-left corner of the picture becomes its top-right. */
    expect(turnCrop(crop, bounds, 1)).toEqual({ x: 500, y: 0, width: 100, height: 200 })
    expect(turnCrop(crop, bounds, -1)).toEqual({ x: 0, y: 600, width: 100, height: 200 })
  })

  it('comes back to where it started after four turns', () => {
    const crop = { x: 120, y: 40, width: 200, height: 100 }

    let next = crop
    let space = bounds
    for (let turn = 0; turn < 4; turn += 1) {
      next = turnCrop(next, space, 1)
      space = { width: space.height, height: space.width }
    }

    expect(next).toEqual(crop)
  })
})

describe('mirrorCrop', () => {
  it('mirrors the crop with the picture', () => {
    const crop = { x: 100, y: 50, width: 200, height: 100 }

    expect(mirrorCrop(crop, bounds, 'x')).toEqual({ x: 500, y: 50, width: 200, height: 100 })
    expect(mirrorCrop(crop, bounds, 'y')).toEqual({ x: 100, y: 450, width: 200, height: 100 })
  })
})

describe('fitInside', () => {
  it('leaves a picture that already fits alone', () => {
    expect(fitInside(800, 600, 1200)).toEqual({ width: 800, height: 600 })
  })

  it('scales down to the tighter of the two caps', () => {
    expect(fitInside(800, 600, 400)).toEqual({ width: 400, height: 300 })
    expect(fitInside(800, 600, 400, 150)).toEqual({ width: 200, height: 150 })
  })
})
