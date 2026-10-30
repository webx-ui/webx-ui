import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { h } from 'vue'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import { contactHref } from './contacts'
import OrderGlanceRow from './OrderGlanceRow.vue'
import type { OrderGlance } from './types'

function glance(over: Partial<OrderGlance> = {}): OrderGlance {
  return {
    id: 7,
    items: [{ id: 1, name: 'Shirt', options: null, qty: 2, total: '€40.00' }],
    count: { lines: 1, units: 2, refunded_units: 0 },
    totals: {
      subtotal: '€40.00',
      adjustments: '−€4.00',
      total: '€36.00',
      tenders: null,
      payable: '€36.00',
      refunded: '€10.00',
    },
    paid: true,
    awaiting_payment: false,
    source: 'quick',
    customer: { name: 'Alex Doe', phone: '+800 1234-5678', email: 'alex@example.com' },
    recipient: { name: 'Sam Roe', phone: '+800 1234 5679' },
    comment: null,
    manager_note: null,
    last_note: null,
    blocks: [],
    milestones: { placed: '2026-10-30T10:00:00+00:00' },
    ...over,
  }
}

function mountGlance(data: OrderGlance) {
  const i18n = createI18n()
  const admin = { apiPath: '/api/cms', http: {}, i18n, assetUrls: null } as unknown as AdminContext

  return mount(OrderGlanceRow, {
    props: { glance: data },
    global: { provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n } },
  })
}

describe('contactHref', () => {
  it('dials the digits and a leading plus only, and writes to an address with an @', () => {
    expect(contactHref('phone', ' +800 (1234) 56-78 ')).toBe('tel:+80012345678')
    expect(contactHref('phone', '800 1234')).toBe('tel:8001234')
    expect(contactHref('phone', 'ask at the desk')).toBeNull()
    expect(contactHref('email', 'alex@example.com')).toBe('mailto:alex@example.com')
    expect(contactHref('email', 'not an address')).toBeNull()
  })
})

describe('OrderGlanceRow', () => {
  it('shows the totals as the card does, with what was paid, given back and where it came from', () => {
    const wrapper = mountGlance(glance())
    const totals = wrapper.get('[data-test="glance-totals"]').text()

    expect(totals).toContain('€40.00')
    expect(totals).toContain('−€4.00')
    expect(totals).toContain('€36.00')
    expect(totals).toContain('−€10.00')
    expect(totals).toContain('Paid')
    expect(wrapper.text()).toContain('One-click order')
  })

  it('says what awaits payment when nothing is paid yet', () => {
    const wrapper = mountGlance(
      glance({
        paid: false,
        awaiting_payment: true,
        source: null,
        totals: { ...glance().totals, adjustments: null, refunded: null },
      }),
    )
    const totals = wrapper.get('[data-test="glance-totals"]').text()

    expect(totals).toContain('Awaiting payment')
    expect(totals).not.toContain('−€10.00')
    expect(wrapper.text()).not.toContain('One-click order')
  })

  it('makes the phones and the e-mail links that do not open the order', () => {
    const rowClick = vi.fn()
    const i18n = createI18n()
    const admin = {
      apiPath: '/api/cms',
      http: {},
      i18n,
      assetUrls: null,
    } as unknown as AdminContext
    const wrapper = mount(
      { render: () => h('div', { onClick: rowClick }, [h(OrderGlanceRow, { glance: glance() })]) },
      {
        attachTo: document.body,
        global: { provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n } },
      },
    )

    const links = wrapper.findAll('a').map((link) => link.attributes('href'))
    expect(links).toEqual(['tel:+80012345678', 'mailto:alex@example.com', 'tel:+80012345679'])

    void wrapper.get('a').trigger('click')
    expect(rowClick).not.toHaveBeenCalled()

    wrapper.unmount()
  })
})
