import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import { provideOrderCard } from './card'
import OrderStatusNode from './OrderStatusNode.vue'
import OrderSummaryNode from './OrderSummaryNode.vue'
import type { OrderCardData } from './types'

function order(patch: Partial<OrderCardData> = {}): OrderCardData {
  return {
    id: 7,
    number: '1049',
    placed_at: '2026-10-30T10:00:00+00:00',
    locale: 'en',
    currency: 'USD',
    exponent: 2,
    guest: true,
    user_id: null,
    visitor: null,
    status: {
      id: 9,
      name: 'Cancelled',
      color: 'neutral',
      deleted: false,
      public: { id: 5, name: 'Cancelled' },
    },
    statuses: [],
    awaiting_payment: false,
    payment_failed_at: null,
    milestones: {
      placed: { at: '2026-10-30T10:00:00+00:00', source: 'panel', reference: null, note: null },
      cancelled: {
        at: '2026-10-30T11:00:00+00:00',
        source: 'panel',
        reference: null,
        note: 'The buyer changed their mind',
      },
    },
    items: [],
    adjustments: [],
    tenders: [],
    benefits: [],
    totals: {
      subtotal: '$50.00',
      adjustments: null,
      total: '$50.00',
      tenders: null,
      payable: '$50.00',
      refunded: null,
      refundable: '$50.00',
      refundable_minor: 5000,
    },
    count: { lines: 2, units: 5, refunded_units: 0 },
    refunds: [],
    blocks: [],
    timeline: [],
    notes: [],
    notifications: null,
    actions: {
      status: true,
      paid: false,
      received: false,
      cancel: false,
      details: true,
      note: true,
      refund: false,
      refund_reverse: false,
      visitor: false,
      visitor_search: false,
    },
    ...patch,
  } as OrderCardData
}

function card(node: object, data: OrderCardData) {
  const i18n = createI18n()
  const admin = { apiPath: '/api/cms', http: {}, i18n } as unknown as AdminContext
  const changeStatus = vi.fn(() => Promise.resolve())
  const reverseRefund = vi.fn<(id: number) => Promise<void>>(() => Promise.resolve())

  const Host = defineComponent({
    setup() {
      provideOrderCard({
        order: ref(data),
        busy: ref(false),
        changeStatus,
        mark: () => Promise.resolve(),
        cancel: () => Promise.resolve(),
        refund: () => Promise.resolve(),
        reverseRefund,
        note: () => Promise.resolve(true),
        pin: () => Promise.resolve(true),
        linkVisitor: () => Promise.resolve(),
        unlinkVisitor: () => Promise.resolve(),
      })

      return () => h(node)
    },
  })

  const wrapper = mount(Host, {
    global: { provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n } },
  })

  return { wrapper, changeStatus, reverseRefund }
}

describe('WxOrdersSummary', () => {
  it('counts the lines and the units, and the source is a line of its own under «Placed»', () => {
    const { wrapper } = card(OrderSummaryNode, order())

    expect(wrapper.find('.wx-orders-summary__count').text()).toBe('Lines: 2 · units: 5')

    const source = wrapper.find('.wx-orders-summary__source')
    expect(source.text()).toBe('Created in the panel')
    // Not in the cell of the date: a row of its own after it.
    expect(source.element.previousElementSibling?.tagName).toBe('DD')
    expect(source.element.previousElementSibling?.textContent).not.toContain('Created')
  })

  it('says the reason of a cancellation with its label, on a plate', () => {
    const { wrapper } = card(OrderSummaryNode, order())

    const reason = wrapper.find('[data-test="cancel-reason"]')
    expect(reason.text()).toBe('Reason: The buyer changed their mind')
    expect(reason.classes()).toContain('wx-orders-summary__note--danger')
  })

  it('says the units given back, and each refund with its reason and its reference', () => {
    const { wrapper } = card(
      OrderSummaryNode,
      order({
        count: { lines: 2, units: 5, refunded_units: 1 },
        totals: { ...order().totals, refunded: '$10.00' },
        refunds: [
          {
            id: 1,
            at: null,
            amount: '$10.00',
            reason: 'Broken in the box',
            reference: 'TR-1',
            reversed: null,
          },
        ],
      }),
    )

    expect(wrapper.find('.wx-orders-summary__count').text()).toBe(
      'Lines: 2 · units: 5 · returned: 1',
    )

    const note = wrapper.find('[data-test="refund-note"]')
    expect(note.text()).toContain('Reason: Broken in the box')
    expect(note.text()).toContain('Operation number: TR-1')
    // One refund: its amount is the row's already.
    expect(note.find('.wx-orders-summary__sum').exists()).toBe(false)
    // Without the right to give money back, no record is reversed from here.
    expect(note.find('[data-test="refund-reverse"]').exists()).toBe(false)
  })

  it('reverses a record from its plate, and a reversed one stays struck through with why', async () => {
    const { wrapper, reverseRefund } = card(
      OrderSummaryNode,
      order({
        totals: { ...order().totals, refunded: '$10.00' },
        refunds: [
          {
            id: 1,
            at: null,
            amount: '$50.00',
            reason: 'Broken in the box',
            reference: null,
            reversed: { at: null, reason: 'Typed the whole order' },
          },
          { id: 2, at: null, amount: '$10.00', reason: null, reference: null, reversed: null },
        ],
        actions: { ...order().actions, refund_reverse: true },
      }),
    )

    const [wrong, right] = wrapper.findAll('[data-test="refund-note"]')
    expect(wrong?.classes()).toContain('is-reversed')
    expect(wrong?.find('s').text()).toBe('−$50.00')
    expect(wrong?.find('[data-test="refund-reversed"]').text()).toBe(
      'Reversed: Typed the whole order',
    )
    expect(wrong?.find('[data-test="refund-reverse"]').exists()).toBe(false)

    // A record with nothing to say is still on the plate: the reader may reverse it.
    expect(right?.text()).toContain('−$10.00')
    await right?.find('[data-test="refund-reverse"]').trigger('click')
    expect(reverseRefund).toHaveBeenCalledWith(2)
  })

  it('keeps the row «Given back» while every record is reversed', () => {
    const { wrapper } = card(
      OrderSummaryNode,
      order({
        refunds: [
          {
            id: 1,
            at: null,
            amount: '$50.00',
            reason: null,
            reference: null,
            reversed: { at: null, reason: 'Wrong order' },
          },
        ],
      }),
    )

    expect(wrapper.text()).toContain('Given back')
    expect(wrapper.find('[data-test="refund-reversed"]').exists()).toBe(true)
  })
})

describe('WxOrdersStatus', () => {
  it('changes the status with a pencil that names itself', async () => {
    const { wrapper, changeStatus } = card(OrderStatusNode, order())

    const pencil = wrapper.find('[data-test="status-change"]')
    expect(pencil.attributes('aria-label')).toBe('Change status')
    expect(pencil.text()).toBe('')

    await pencil.trigger('click')
    expect(changeStatus).toHaveBeenCalledOnce()
  })

  it('has no pencil without the right to manage', () => {
    const { wrapper } = card(
      OrderStatusNode,
      order({ actions: { ...order().actions, status: false } }),
    )

    expect(wrapper.find('[data-test="status-change"]').exists()).toBe(false)
  })
})
