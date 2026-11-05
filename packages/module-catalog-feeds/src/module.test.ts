// @vitest-environment node
import { describe, expect, it } from 'vitest'
import { catalogFeeds } from './module'

describe('catalogFeeds()', () => {
  it('is one section with the list of feeds', () => {
    const [section, ...rest] = catalogFeeds()

    expect(rest).toEqual([])
    expect(section?.id).toBe('catalog-feeds')
    expect(section?.path).toBe('/catalog/feeds')
    expect(section?.routes?.map((route) => [route.path, route.name])).toEqual([
      ['/catalog/feeds', 'webx.catalog-feeds'],
    ])
  })

  it('moves with the catalogue', () => {
    expect(catalogFeeds({ path: '/shop' })[0]?.path).toBe('/shop/feeds')
  })
})
