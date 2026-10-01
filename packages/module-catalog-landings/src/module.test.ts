import { describe, expect, it } from 'vitest'
import { cleanSet, formValues, landingSearch } from './api'
import { catalogLandings } from './module'
import type { LandingDetail } from './types'

describe('catalogLandings()', () => {
  it('is one section: the list, a new landing and the page of one', () => {
    const [section, ...rest] = catalogLandings()

    expect(rest).toEqual([])
    expect(section?.id).toBe('catalog-landings')
    expect(section?.path).toBe('/catalog/landings')
    expect(section?.routes?.map((route) => [route.path, route.name])).toEqual([
      ['/catalog/landings', 'webx.catalog-landings'],
      ['/catalog/landings/new', 'webx.catalog-landings.new'],
      ['/catalog/landings/:id(\\d+)', 'webx.catalog-landings.edit'],
    ])
  })

  it('moves with the catalogue', () => {
    expect(catalogLandings({ path: '/shop' })[0]?.path).toBe('/shop/landings')
  })

  it('brings the node types of its form', () => {
    expect(Object.keys(catalogLandings()[0]?.types ?? {})).toEqual([
      'wx-catalog-landing-set',
      'wx-catalog-landing-slug',
      'wx-catalog-landing-sort',
      'wx-catalog-landing-products',
    ])
  })
})

describe('the API helpers', () => {
  it('sends only the filters that are set, booleans as 1 and 0', () => {
    expect(
      landingSearch({
        q: '',
        category: 'root',
        attention: true,
        published: undefined,
        page: 2,
      }).toString(),
    ).toBe('category=root&attention=1&page=2')
  })

  /* A facet just added has no value yet: it neither counts nor fails a save. */
  it('drops the half-built facets of a set', () => {
    expect(
      cleanSet({
        brand: { values: ['3'] },
        color: { values: [] },
        price: { min: null, max: 500 },
        weight: { min: null, max: null },
      }),
    ).toEqual({ brand: { values: ['3'] }, price: { min: null, max: 500 } })
  })

  it('takes the form fields of a landing, an empty set as an object', () => {
    const values = formValues({
      id: 1,
      category_id: 4,
      filters: [] as unknown,
      name: { en: 'Apple laptops' },
      chips: [],
    } as unknown as LandingDetail)

    expect(values.filters).toEqual({})
    expect(values.category_id).toBe(4)
    expect(values.recommended).toBeNull()
    expect('chips' in values).toBe(false)
  })
})
