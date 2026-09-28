import { describe, expect, it } from 'vitest'
import { tariffs } from './module'
import { priceLine } from './price'

describe('tariffs()', () => {
  /*
   * Two modules, so the panel spreads them — `...tariffs()`, the line the composer half writes
   * into `extra.webx.panel.register` (§5.7). The ids are what the manifest names the sections by.
   */
  it('is the tariffs and their groups, under one path', () => {
    const modules = tariffs()

    expect(modules.map((one) => [one.id, one.path])).toEqual([
      ['tariffs', '/tariffs'],
      ['tariff-groups', '/tariffs/groups'],
    ])
  })

  it('moves both with the path it is given', () => {
    expect(tariffs({ path: '/prices' }).map((one) => one.path)).toEqual([
      '/prices',
      '/prices/groups',
    ])
  })
})

describe('priceLine()', () => {
  const base = { price: null, symbol: null, currency: null, period: null, price_text: null }

  it('puts the symbol before the number and the period after it', () => {
    expect(
      priceLine({ ...base, price: 750, symbol: '$', currency: 'USD', period: '/mo' }, 'en'),
    ).toBe('$750 /mo')
  })

  it('shows cents only when there are any, with the separators of the language', () => {
    expect(priceLine({ ...base, price: 12.5, symbol: '€', currency: 'EUR' }, 'en')).toBe('€12.50')
    expect(priceLine({ ...base, price: 1380, symbol: '$', currency: 'USD' }, 'en')).toBe('$1,380')
  })

  it('keeps zero a number (decision 16)', () => {
    expect(priceLine({ ...base, price: 0, symbol: '$', currency: 'USD' }, 'en')).toBe('$0')
  })

  it('prints the code of a currency the config dropped', () => {
    expect(priceLine({ ...base, price: 5, currency: 'CHF' }, 'en')).toBe('CHF5')
  })

  it('prints the words without a number, and nothing without either', () => {
    expect(priceLine({ ...base, price_text: 'On request' }, 'en')).toBe('On request')
    expect(priceLine(base, 'en')).toBe('')
  })
})
