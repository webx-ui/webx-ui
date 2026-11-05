import { describe, expect, it } from 'vitest'
import { catalogFeeds } from './module'

describe('catalogFeeds()', () => {
  it('is one section with the list, a new feed and a feed', () => {
    const [section, ...rest] = catalogFeeds()

    expect(rest).toEqual([])
    expect(section?.id).toBe('catalog-feeds')
    expect(section?.path).toBe('/catalog/feeds')
    expect(section?.routes?.map((route) => [route.path, route.name])).toEqual([
      ['/catalog/feeds', 'webx.catalog-feeds'],
      ['/catalog/feeds/new', 'webx.catalog-feeds.new'],
      ['/catalog/feeds/:id(\\d+)', 'webx.catalog-feeds.edit'],
    ])
  })

  it('brings the types of its form', () => {
    expect(Object.keys(catalogFeeds()[0]?.types ?? {}).sort()).toEqual([
      'wx-catalog-feed-address',
      'wx-catalog-feed-builds',
      'wx-catalog-feed-categories',
      'wx-catalog-feed-exclusions',
      'wx-catalog-feed-filter',
      'wx-catalog-feed-labels',
      'wx-catalog-feed-locale',
      'wx-catalog-feed-stock-map',
      'wx-catalog-feed-template',
    ])
  })

  it('moves with the catalogue', () => {
    expect(catalogFeeds({ path: '/shop' })[0]?.path).toBe('/shop/feeds')
  })
})
