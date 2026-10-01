import { describe, expect, it } from 'vitest'
import { catalogManticore } from './module'

describe('catalogManticore()', () => {
  it('is one section, «System → Search index», under the id the server answers to', () => {
    const [section, ...rest] = catalogManticore()

    expect(rest).toEqual([])
    expect(section?.id).toBe('search-index')
    expect(section?.path).toBe('/search-index')
    expect(section?.routes?.map((route) => [route.path, route.name])).toEqual([
      ['/search-index', 'webx.search-index'],
    ])
  })

  it('lives where it is told to', () => {
    expect(catalogManticore({ path: '/index' })[0]?.path).toBe('/index')
  })
})
