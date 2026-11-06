// @vitest-environment node
import { describe, expect, it } from 'vitest'
import {
  blankValues,
  collectionSearch,
  formValues,
  payload,
  presetFields,
  presetOf,
  previewQuestion,
  tagOf,
} from './api'
import { catalogCollections } from './module'
import type { CollectionDetail, CollectionPreset } from './types'

const presets: CollectionPreset[] = [
  { key: 'new', sort: 'new', on_sale: false, facet: null },
  { key: 'on-sale', sort: null, on_sale: true, facet: null },
  { key: 'label', sort: null, on_sale: false, facet: 'label' },
]

describe('catalogCollections()', () => {
  it('is one section: the list, a new collection and the page of one', () => {
    const [section, ...rest] = catalogCollections()

    expect(rest).toEqual([])
    expect(section?.id).toBe('catalog-collections')
    expect(section?.path).toBe('/catalog/collections')
    expect(section?.routes?.map((route) => [route.path, route.name])).toEqual([
      ['/catalog/collections', 'webx.catalog-collections'],
      ['/catalog/collections/new', 'webx.catalog-collections.new'],
      ['/catalog/collections/:id(\\d+)', 'webx.catalog-collections.edit'],
    ])
  })

  it('moves with the catalogue', () => {
    expect(catalogCollections({ path: '/shop' })[0]?.path).toBe('/shop/collections')
  })

  it('brings the field of the block and the node types of its form', () => {
    expect(Object.keys(catalogCollections()[0]?.types ?? {}).sort()).toEqual([
      'wx-catalog-collection',
      'wx-catalog-collection-key',
      'wx-catalog-collection-preset',
      'wx-catalog-collection-preview',
      'wx-catalog-collection-products',
      'wx-catalog-collection-set',
      'wx-catalog-collection-sort',
    ])
  })
})

describe('the API helpers', () => {
  it('sends only the filters that are set, booleans as 1 and 0', () => {
    expect(
      collectionSearch({ q: '', enabled: false, attention: undefined, page: 2 }).toString(),
    ).toBe('enabled=0&page=2')
  })

  it('takes the form fields of a collection, an empty map of PHP as an object', () => {
    const values = formValues({
      id: 1,
      key: 'picks',
      filters: [] as unknown,
      title: [] as unknown,
      link_text: [] as unknown,
      limit: 4,
      product_items: [],
    } as unknown as CollectionDetail)

    expect(values.filters).toEqual({})
    expect(values.title).toEqual({})
    expect(values.link_text).toEqual({})
    expect(values.limit).toBe(4)
    expect('product_items' in values).toBe(false)
  })

  it('saves a set without its half-built facets', () => {
    expect(
      payload({ ...blankValues(), filters: { brand: { values: [] }, color: { values: ['3'] } } })
        .filters,
    ).toEqual({ color: { values: ['3'] } })
  })

  /* A title typed must not ask the server anything: only these fields decide the products. */
  it('asks the preview only what decides the products', () => {
    const asked = previewQuestion({ ...blankValues(), title: { en: 'Picks' } }, 'en')

    expect('title' in asked).toBe(false)
    expect(asked).toMatchObject({ source: 'manual', limit: 8, products: [], locale: 'en' })
  })

  it('copies the whole tag', () => {
    expect(tagOf('cameras')).toBe('<x-webx-catalog-collections::collection key="cameras" />')
  })
})

describe('the presets', () => {
  const filter = { ...blankValues(), source: 'filter' }

  it('writes the fields of a preset and clears the conditions of another', () => {
    const preset = presets[1]!

    expect(
      presetFields(preset, { ...filter, sort: 'new', filters: { a: { values: ['1'] } } }),
    ).toEqual({
      sort: null,
      on_sale: true,
      filters: {},
    })
  })

  it('keeps what was already picked of the facet of a preset', () => {
    const preset = presets[2]!

    expect(
      presetFields(preset, { ...filter, filters: { label: { values: ['7'] } } }).filters,
    ).toEqual({
      label: { values: ['7'] },
    })
    expect(presetFields(preset, filter).filters).toEqual({ label: { values: [] } })
  })

  it('reads the preset off the fields, as the server does', () => {
    expect(presetOf({ ...filter, sort: 'new' }, presets)).toBe('new')
    expect(presetOf({ ...filter, on_sale: true }, presets)).toBe('on-sale')
    expect(presetOf({ ...filter, filters: { label: { values: [] } } }, presets)).toBe('label')
  })

  it('stops being a preset as soon as a field stops matching', () => {
    expect(presetOf({ ...filter, sort: 'new', on_sale: true }, presets)).toBeNull()
    expect(presetOf({ ...filter, sort: 'popular' }, presets)).toBeNull()
    expect(presetOf({ ...filter, sort: 'new', source: 'manual' }, presets)).toBeNull()
  })
})
