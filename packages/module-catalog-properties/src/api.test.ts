import { describe, expect, it } from 'vitest'
import { propertySearch } from './api'

describe('propertySearch()', () => {
  it('sends what narrows the list and nothing that does not', () => {
    expect(propertySearch({}).toString()).toBe('')
    expect(propertySearch({ type: 'number', group: 4, page: 1 }).toString()).toBe(
      'type=number&group=4',
    )
  })

  it('asks for properties by id the way Laravel reads a list', () => {
    expect(decodeURIComponent(propertySearch({ ids: [3, 7] }).toString())).toBe('ids[]=3&ids[]=7')
  })

  it('asks for the bin', () => {
    expect(propertySearch({ trashed: true }).get('trashed')).toBe('1')
  })
})
