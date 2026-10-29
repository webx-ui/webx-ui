// @vitest-environment node
import { describe, expect, it } from 'vitest'
import { orderSearch } from './api'
import { orders } from './module'

describe('orders()', () => {
  it('mounts the list and the card under the path it is given', () => {
    const section = orders({ path: '/shop/orders' })

    expect(section.id).toBe('orders')
    expect((section.routes ?? []).map((route) => route.path)).toEqual([
      '/shop/orders',
      '/shop/orders/:id(\\d+)',
    ])
  })

  /* The screen `orders.show` names these; a type it names and the panel does not know is drawn
     as a loud placeholder, so the two lists have to stay one. */
  it('registers every node type of the card', () => {
    expect(Object.keys(orders().types ?? {}).sort()).toEqual([
      'wx-orders-items',
      'wx-orders-layout',
      'wx-orders-status',
      'wx-orders-summary',
      'wx-orders-timeline',
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
