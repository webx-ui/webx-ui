import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import OrdersStats from './OrdersStats.vue'
import OrderGlanceRow from './OrderGlanceRow.vue'
import type { OrderGlance, OrdersSummaryData } from './types'

const money = (
  currency: string,
  placed: number,
  paid: number,
  revenue: string,
  average: string,
) => ({
  currency,
  placed,
  paid,
  revenue: 0,
  revenue_formatted: revenue,
  average: 0,
  average_formatted: average,
})

const summary: OrdersSummaryData = {
  awaiting_payment: 3,
  payment_failed: 1,
  paid_status_waits: 0,
  awaiting_by_currency: [
    { currency: 'EUR', orders: 2, amount: 9998, amount_formatted: '€99.98' },
    { currency: 'USD', orders: 1, amount: 1000, amount_formatted: '$10.00' },
  ],
  periods: [
    { days: 1, by_currency: [money('EUR', 2, 1, '€40.00', '€40.00')] },
    {
      days: 7,
      by_currency: [money('EUR', 5, 3, '€120.00', '€40.00'), money('USD', 1, 0, '$0.00', '$0.00')],
    },
    { days: 30, by_currency: [] },
  ],
  daily: Array.from({ length: 30 }, (_, index) => ({
    date: `2026-10-${String(index + 1).padStart(2, '0')}`,
    orders: index === 29 ? 4 : index % 7 === 0 ? 1 : 0,
  })),
}

function mountStats(answer: Promise<OrdersSummaryData>) {
  const i18n = createI18n()
  const get = vi.fn(() => answer.then((data) => ({ data })))
  const admin = {
    apiPath: '/api/cms',
    http: { get },
    i18n,
    state: { user: { id: 7 } },
  } as unknown as AdminContext

  const wrapper = mount(OrdersStats, {
    global: { provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n } },
  })

  return { wrapper, get }
}

afterEach(() => localStorage.clear())

/** The figures over «Orders» (decision 93): the agent's summary, drawn per period and currency. */
describe('WxOrdersStats', () => {
  it('asks the summary once and draws the period chosen, money per currency', async () => {
    const { wrapper, get } = mountStats(Promise.resolve(summary))
    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/orders/summary')

    const figures = wrapper.findAll('.wx-orders-stats__figure')
    expect(figures).toHaveLength(4)
    // Seven days by default: both currencies' orders together, the money one line a currency.
    expect(figures[0]!.find('.wx-statistic__number').text()).toBe('6')
    expect(figures[1]!.findAll('.wx-orders-stats__money').map((one) => one.text())).toEqual([
      '€120.00',
      '$0.00',
    ])
    // The average only of a currency with a paid order.
    expect(figures[2]!.findAll('.wx-orders-stats__money').map((one) => one.text())).toEqual([
      '€40.00',
    ])
    expect(figures[3]!.find('.wx-statistic__number').text()).toBe('3')
    expect(wrapper.findAll('.wx-orders-stats__bars rect')).toHaveLength(30)
  })

  it('switches the period, a period without orders showing a dash', async () => {
    const { wrapper } = mountStats(Promise.resolve(summary))
    await flushPromises()

    const segments = wrapper.findAll('.wx-segmented button, .wx-segmented [role="radio"]')
    await segments[segments.length - 1]!.trigger('click')

    const figures = wrapper.findAll('.wx-orders-stats__figure')
    expect(figures[0]!.find('.wx-statistic__number').text()).toBe('0')
    expect(figures[1]!.find('.wx-statistic__number').text()).toBe('—')
  })

  it('folds, and remembers it for the manager', async () => {
    const { wrapper } = mountStats(Promise.resolve(summary))
    await flushPromises()

    await wrapper.find('.wx-orders-stats__toggle').trigger('click')
    expect(wrapper.find('.wx-orders-stats__grid').exists()).toBe(false)
    expect(JSON.parse(localStorage.getItem('webx-orders:stats:7') ?? '{}')).toMatchObject({
      folded: true,
    })

    const again = mountStats(Promise.resolve(summary))
    await flushPromises()
    expect(again.wrapper.find('.wx-orders-stats__grid').exists()).toBe(false)
    expect(again.wrapper.find('.wx-orders-stats__toggle').attributes('aria-expanded')).toBe('false')
  })

  it('says so when the figures did not come, and nothing else breaks', async () => {
    const { wrapper } = mountStats(Promise.reject(new Error('down')))
    await flushPromises()

    expect(wrapper.text()).toContain('The figures could not be loaded.')
  })
})

const glance: OrderGlance = {
  id: 7,
  items: [{ id: 1, name: 'Item A', options: 'Size M', qty: 2, total: '€99.98' }],
  count: { lines: 1, units: 2, refunded_units: 0 },
  totals: {
    subtotal: '€99.98',
    adjustments: null,
    total: '€99.98',
    tenders: null,
    payable: '€99.98',
    refunded: null,
  },
  paid: true,
  awaiting_payment: false,
  source: null,
  customer: { name: 'Alex Doe', phone: '+800 1234 5678', email: 'alex@example.com' },
  recipient: { name: 'Sam Roe', phone: null },
  comment: 'Ring twice.',
  manager_note: 'Regular buyer.',
  last_note: {
    id: 3,
    body: 'Called.',
    source: 'mcp',
    author: { id: 1, name: 'Ada', avatar: null },
    created_at: '2026-10-30T10:00:00+00:00',
  },
  blocks: [{ key: 'delivery', summary: 'Courier, tomorrow' }],
  milestones: { placed: '2026-10-30T09:00:00+00:00', paid: '2026-10-30T09:30:00+00:00' },
}

/** An opened row of the list (decision 94): it draws what it is given, the pinned note first. */
describe('WxOrdersGlance', () => {
  function mountGlance(props: { glance: OrderGlance | null; failed?: boolean }) {
    const i18n = createI18n()

    return mount(OrderGlanceRow, {
      props,
      global: {
        provide: {
          [adminKey as symbol]: { i18n } as unknown as AdminContext,
          [i18nKey as symbol]: i18n,
        },
      },
    })
  }

  it('shows the lines, the contacts, the notes, the blocks and the milestones', () => {
    const wrapper = mountGlance({ glance })
    const text = wrapper.text()

    for (const part of ['Item A', 'Size M', '× 2', '€99.98', 'Alex Doe', '+800 1234 5678']) {
      expect(text).toContain(part)
    }

    expect(text).toContain('Recipient: Sam Roe')
    expect(text).toContain('Courier, tomorrow')
    expect(wrapper.findAll('.wx-orders-glance__marks dt').map((one) => one.text())).toEqual([
      'Placed',
      'Paid',
    ])

    // The pinned note before the latest of the feed, the agent named as the history names it.
    const html = wrapper.html()
    expect(html.indexOf('Regular buyer.')).toBeLessThan(html.indexOf('Called.'))
    expect(text).toContain('Agent: Ada')
  })

  it('waits with a skeleton, and says so when the answer did not come', () => {
    expect(mountGlance({ glance: null }).find('.wx-skeleton').exists()).toBe(true)
    expect(mountGlance({ glance: null, failed: true }).text()).toContain(
      'Could not load the order.',
    )
  })
})
