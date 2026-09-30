import { describe, expect, it } from 'vitest'
import { catalogBrandsMessages } from './messages'
import { catalogBrands, catalogBrandsOptions } from './module'

describe('catalogBrands()', () => {
  it('is one section: the list of brands and the page of one', () => {
    const [section, ...rest] = catalogBrands()

    expect(rest).toEqual([])
    expect(section?.id).toBe('catalog-brands')
    expect(section?.path).toBe('/catalog/brands')
    expect(section?.routes?.map((route) => [route.path, route.name])).toEqual([
      ['/catalog/brands', 'webx.catalog-brands'],
      ['/catalog/brands/:id(\\d+)', 'webx.catalog-brands.edit'],
    ])
  })

  it('moves with the catalogue', () => {
    expect(catalogBrands({ path: '/shop' })[0]?.path).toBe('/shop/brands')
    expect(catalogBrandsOptions({ path: '/shop' }).items?.(3)).toEqual({
      path: '/shop/products',
      query: { 'f.brand': '3' },
    })
  })

  it('asks the server where the brands answer, with the rights of the catalogue', () => {
    const options = catalogBrandsOptions()

    expect(options.api).toBe('catalog/brands')
    expect(options.screen).toBe('catalog.brand-form')
    expect(options.manage).toBe('catalog.manage')
    expect(options.count).toBe('products_count')
  })

  it('shows the products of a brand as the list narrowed by it', () => {
    expect(catalogBrandsOptions().items?.(7)).toEqual({
      path: '/catalog/products',
      query: { 'f.brand': '7' },
    })
  })

  /* A word pointing at a key nobody ships is the key itself on screen — in English and Russian alike. */
  it('says «brand» with lines it ships', () => {
    const words = catalogBrandsOptions().words ?? {}

    expect(Object.keys(words).length).toBeGreaterThan(0)

    for (const key of Object.values(words)) {
      const [, path = ''] = key.split('::')
      const [group = '', line = ''] = path.split('.')

      expect(catalogBrandsMessages[group]?.[line], key).toBeTypeOf('string')
    }
  })
})
