import { describe, expect, it } from 'vitest'
import { NEUTRAL, applyAdjustments, filterString, isNeutral } from './filters'

/*
 * The arithmetic on its own. The point of these is that the two ways of doing it — the
 * browser's `filter` and our own pass for the browsers without it — agree: a picture
 * adjusted on screen and the file that comes out have to be the same picture.
 */

function pixel(r: number, g: number, b: number, adjust: Partial<typeof NEUTRAL> = {}) {
  const data = new Uint8ClampedArray([r, g, b, 255])
  applyAdjustments(data, { ...NEUTRAL, ...adjust })
  return [data[0], data[1], data[2], data[3]]
}

describe('filterString', () => {
  it('says nothing when nothing was done', () => {
    expect(filterString(NEUTRAL)).toBe('')
    expect(isNeutral(NEUTRAL)).toBe(true)
  })

  it('names only what was moved', () => {
    expect(filterString({ ...NEUTRAL, brightness: 112 })).toBe('brightness(112%)')
    expect(filterString({ ...NEUTRAL, contrast: 90, saturation: 80 })).toBe(
      'contrast(90%) saturate(80%)',
    )
  })

  it('drops the saturation when the picture is black and white anyway', () => {
    expect(filterString({ brightness: 100, contrast: 100, saturation: 40, mono: true })).toBe(
      'grayscale(1)',
    )
  })
})

describe('applyAdjustments', () => {
  it('leaves the picture alone at the neutral reading', () => {
    expect(pixel(10, 120, 230)).toEqual([10, 120, 230, 255])
  })

  it('lightens and darkens', () => {
    expect(pixel(100, 100, 100, { brightness: 150 })).toEqual([150, 150, 150, 255])
    expect(pixel(100, 100, 100, { brightness: 50 })).toEqual([50, 50, 50, 255])
  })

  it('pivots contrast on mid-grey, which is what makes it contrast', () => {
    /* Mid-grey is the pivot, so doubling the contrast leaves it where it was. */
    expect(pixel(128, 128, 128, { contrast: 200 })[0]).toBe(128)
    expect(pixel(200, 200, 200, { contrast: 200 })[0]).toBe(255)
    expect(pixel(60, 60, 60, { contrast: 200 })[0]).toBe(0)
  })

  it('takes the colour out and leaves the brightness', () => {
    const [r, g, b] = pixel(200, 100, 50, { mono: true })

    expect(r).toBe(g)
    expect(g).toBe(b)
    /* 0.2126·200 + 0.7152·100 + 0.0722·50 — the weights CSS uses. */
    expect(r).toBe(118)
  })

  it('reaches the same grey through the saturation slider', () => {
    expect(pixel(200, 100, 50, { saturation: 0 })).toEqual(pixel(200, 100, 50, { mono: true }))
  })

  it('never runs past the ends of the scale', () => {
    expect(pixel(250, 5, 5, { brightness: 200 })).toEqual([255, 10, 10, 255])
    expect(pixel(10, 10, 10, { brightness: 0 })).toEqual([0, 0, 0, 255])
  })

  it('leaves what is see-through see-through', () => {
    const data = new Uint8ClampedArray([200, 100, 50, 0])
    applyAdjustments(data, { ...NEUTRAL, brightness: 150, mono: true })

    expect(data[3]).toBe(0)
  })
})
