import { describe, expect, it } from 'vitest'
import { cache } from './module'

describe('cache()', () => {
  it('is «System → Cache», under the id the frame answers to', () => {
    const section = cache()

    expect(section.id).toBe('cache')
    expect(section.path).toBe('/cache')
    expect(section.routes?.map((route) => [route.path, route.name])).toEqual([
      ['/cache', 'webx.cache'],
    ])
  })

  it('lives where it is told to', () => {
    expect(cache({ path: '/system/cache' }).path).toBe('/system/cache')
  })
})
