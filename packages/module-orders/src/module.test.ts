// @vitest-environment node
import { describe, expect, it } from 'vitest'
import { orderSearch } from './api'
import { orders } from './module'
import { sampleNumber } from './settings'

describe('orders()', () => {
  it('mounts the list, the card, the statuses, the settings and «New order» under the path it is given', () => {
    const section = orders({ path: '/shop/orders' })

    expect(section.id).toBe('orders')
    expect((section.routes ?? []).map((route) => route.path)).toEqual([
      '/shop/orders',
      '/shop/orders/statuses',
      '/shop/orders/settings',
      '/shop/orders/new',
      '/shop/orders/:id(\\d+)',
    ])
  })

  /* The screen `orders.show` names these, and the patches of the server with the visitors (the
     account of the order, the tab and the count on `users.form`); a type it names and the panel
     does not know is drawn as a loud placeholder, so the two lists have to stay one. */
  it('registers every node type of the card and the settings', () => {
    expect(Object.keys(orders().types ?? {}).sort()).toEqual([
      'wx-orders-count',
      'wx-orders-items',
      'wx-orders-layout',
      'wx-orders-list',
      'wx-orders-notes',
      'wx-orders-number-sample',
      'wx-orders-status',
      'wx-orders-summary',
      'wx-orders-timeline',
      'wx-orders-visitor',
    ])
  })
})

describe('orderSearch()', () => {
  it('leaves out what is not set and writes a flag as 1 or 0', () => {
    expect(
      orderSearch({ view: 'new', search: '', paid: false, cancelled: true, page: 2 }).toString(),
    ).toBe('view=new&paid=0&cancelled=1&page=2')
  })
})

describe('sampleNumber()', () => {
  /* The same number the server's `OrderNumbers::compose()` makes, so the trial number is true. */
  it('is the prefix, then start + id padded with zeros', () => {
    expect(sampleNumber({}, 1)).toBe('1001')
    expect(
      sampleNumber(
        { 'orders.number.prefix': 'A-', 'orders.number.start': 0, 'orders.number.pad': 6 },
        42,
      ),
    ).toBe('A-000042')
    expect(sampleNumber({ 'orders.number.start': '', 'orders.number.pad': 99 }, 7)).toBe(
      '00000000000000001007',
    )
  })
})
