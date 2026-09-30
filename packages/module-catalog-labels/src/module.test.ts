import { describe, expect, it } from 'vitest'
import { catalogLabelsMessages } from './messages'
import { catalogLabels, catalogLabelsOptions } from './module'

describe('catalogLabels()', () => {
  it('is one section: the list of labels and the page of one', () => {
    const [section, ...rest] = catalogLabels()

    expect(rest).toEqual([])
    expect(section?.id).toBe('catalog-labels')
    expect(section?.path).toBe('/catalog/labels')
    expect(section?.routes?.map((route) => [route.path, route.name])).toEqual([
      ['/catalog/labels', 'webx.catalog-labels'],
      ['/catalog/labels/:id(\\d+)', 'webx.catalog-labels.edit'],
    ])
  })

  it('moves with the catalogue', () => {
    expect(catalogLabels({ path: '/shop' })[0]?.path).toBe('/shop/labels')
    expect(catalogLabelsOptions({ path: '/shop' }).items?.(3)).toEqual({
      path: '/shop/products',
      query: { 'f.label': '3' },
    })
  })

  it('asks the server where the labels answer, with the rights of the catalogue', () => {
    const options = catalogLabelsOptions()

    expect(options.api).toBe('catalog/labels')
    expect(options.screen).toBe('catalog.label-form')
    expect(options.manage).toBe('catalog.manage')
    expect(options.count).toBe('products_count')
  })

  it('shows the products of a label as the list narrowed by it', () => {
    expect(catalogLabelsOptions().items?.(7)).toEqual({
      path: '/catalog/products',
      query: { 'f.label': '7' },
    })
  })

  /* A word pointing at a key nobody ships is the key itself on screen — in English and Russian alike. */
  it('says «label» with lines it ships', () => {
    const words = catalogLabelsOptions().words ?? {}

    expect(Object.keys(words).length).toBeGreaterThan(0)

    for (const key of Object.values(words)) {
      const [, path = ''] = key.split('::')
      const [group = '', line = ''] = path.split('.')

      expect(catalogLabelsMessages[group]?.[line], key).toBeTypeOf('string')
    }
  })
})
