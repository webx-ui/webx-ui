// @vitest-environment node
import { describe, expect, it } from 'vitest'
import { delivery } from './module'

describe('delivery()', () => {
  it('is six sections, the methods first with the fields of the screens', () => {
    const sections = delivery()

    expect(sections.map((one) => [one.id, one.path])).toEqual([
      ['delivery', '/delivery'],
      ['delivery-points', '/delivery/points'],
      ['delivery-carriers', '/delivery/carriers'],
      ['delivery-geography', '/delivery/geography'],
      ['delivery-popular', '/delivery/popular'],
      ['delivery-bindings', '/delivery/bindings'],
    ])
    expect(Object.keys(sections[0]?.types ?? {})).toEqual([
      'wx-delivery-locality',
      'wx-delivery-localities',
      'wx-delivery-region',
      'wx-delivery-country',
      'wx-delivery-schedule',
      'wx-delivery-point-kind',
    ])
    expect(sections[0]?.types?.['wx-delivery-region']?.nested).toBe(true)
  })

  it('moves as a whole', () => {
    expect(delivery({ path: '/shipping' }).map((one) => one.routes?.[0]?.path)).toEqual([
      '/shipping',
      '/shipping/points',
      '/shipping/carriers',
      '/shipping/geography',
      '/shipping/popular',
      '/shipping/bindings',
    ])
  })
})
