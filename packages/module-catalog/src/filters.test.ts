import { describe, expect, it } from 'vitest'
import { productSearch } from './api'
import { readFacets, writeFacet } from './filters'
import { firstError } from './errors'
import { lastSegment } from './paths'
import type { FacetInfo } from './types'

const facets: FacetInfo[] = [
  { key: 'category', code: 'category', kind: 'tree', label: 'Category' },
  { key: 'price', code: 'price', kind: 'range', label: 'Price' },
  { key: 'brand', code: 'brand', kind: 'terms', label: 'Brand' },
  { key: 'in-stock', code: 'in-stock', kind: 'toggle', label: 'In stock' },
]

describe('the filter in the address', () => {
  it('reads each kind of facet back out of its parameter', () => {
    expect(
      readFacets(
        { 'f.category': '2,5', 'f.price': '100-', 'f.brand': 'acme', 'f.in-stock': '1', q: 'x' },
        facets,
      ),
    ).toEqual({
      category: ['2', '5'],
      price: { min: 100, max: null },
      brand: ['acme'],
      'in-stock': true,
    })
  })

  it('ignores what no facet of the registry answers to, and empty ranges', () => {
    expect(readFacets({ 'f.colour': 'red', 'f.price': '-' }, facets)).toEqual({})
  })

  it('writes a choice so that reading it gives it back', () => {
    for (const choice of [
      ['2', '5'],
      { min: 100, max: 500 },
      { min: null, max: 50 },
      true,
    ] as const) {
      const written = writeFacet(choice as never)
      const key = Array.isArray(choice) ? 'category' : choice === true ? 'in-stock' : 'price'

      expect(readFacets({ [`f.${key}`]: written ?? null }, facets)[key]).toEqual(choice)
    }
  })

  it('takes the parameter away for a choice of nothing', () => {
    expect(writeFacet([])).toBeUndefined()
    expect(writeFacet({ min: null, max: null })).toBeUndefined()
    expect(writeFacet(null)).toBeUndefined()
  })
})

describe('the query the list is asked with', () => {
  it('is what Laravel reads as nested parameters, and nothing that was not chosen', () => {
    const search = productSearch({
      q: 'kettle',
      state: 'no-category',
      sort: 'default',
      page: 1,
      facets: { category: ['2', '5'], price: { min: 100, max: null }, 'in-stock': true },
    })

    expect([...search.entries()]).toEqual([
      ['q', 'kettle'],
      ['state', 'no-category'],
      ['facets[category][]', '2'],
      ['facets[category][]', '5'],
      ['facets[price][min]', '100'],
      ['facets[in-stock]', '1'],
    ])
  })
})

describe('small things the screens lean on', () => {
  it('finds a refusal under the field or under one of its languages', () => {
    expect(firstError({ 'name.en': ['Required.'] }, 'name')).toBe('Required.')
    expect(firstError({ name: ['Taken.'] }, 'name')).toBe('Taken.')
    // `name.en` is a language of `name`, and not a field `nam` would own.
    expect(firstError({ 'name.en': ['Required.'] }, 'nam')).toBeUndefined()
  })

  it('reads the last segment of an address on the site', () => {
    expect(lastSegment('https://shop.test/en/laptops/')).toBe('laptops')
    expect(lastSegment('https://shop.test/lamp-12')).toBe('lamp-12')
    expect(lastSegment(null)).toBeNull()
  })
})
