import { describe, expect, it } from 'vitest'
import { catalogStockMessages } from './messages'
import { catalogStock, catalogStockOptions } from './module'

describe('catalogStock()', () => {
  it('is one section: the list of stock statuses and the page of one', () => {
    const [section, ...rest] = catalogStock()

    expect(rest).toEqual([])
    expect(section?.id).toBe('catalog-stock')
    expect(section?.path).toBe('/catalog/stock')
    expect(section?.routes?.map((route) => [route.path, route.name])).toEqual([
      ['/catalog/stock', 'webx.catalog-stock'],
      ['/catalog/stock/:id(\\d+)', 'webx.catalog-stock.edit'],
    ])
  })

  it('moves with the catalogue', () => {
    expect(catalogStock({ path: '/shop' })[0]?.path).toBe('/shop/stock')
    expect(catalogStockOptions({ path: '/shop' }).items?.(3)).toEqual({
      path: '/shop/products',
      query: { 'f.stock': '3' },
    })
  })

  it('asks the server where the statuses answer, with the rights of the catalogue', () => {
    const options = catalogStockOptions()

    expect(options.api).toBe('catalog/stock')
    expect(options.screen).toBe('catalog.stock-status-form')
    expect(options.manage).toBe('catalog.manage')
    expect(options.count).toBe('products_count')
  })

  it('shows the products in a status as the list narrowed by it', () => {
    expect(catalogStockOptions().items?.(7)).toEqual({
      path: '/catalog/products',
      query: { 'f.stock': '7' },
    })
  })

  /* A word pointing at a key nobody ships is the key itself on screen — in English and Russian alike. */
  it('says «status» with lines it ships', () => {
    const words = catalogStockOptions().words ?? {}

    expect(Object.keys(words).length).toBeGreaterThan(0)

    for (const key of Object.values(words)) {
      const [, path = ''] = key.split('::')
      const [group = '', line = ''] = path.split('.')

      expect(catalogStockMessages[group]?.[line], key).toBeTypeOf('string')
    }
  })
})
