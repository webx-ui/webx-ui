import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import { provideOrderCard } from './card'
import OrderTimelineNode from './OrderTimelineNode.vue'
import type { OrderCardData, OrderDelivery } from './types'

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
      paid: null,
      paid_first: null,
      paid_topups: null,
      balance: null,
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
  const resend = vi.fn<(id: number) => Promise<void>>(() => Promise.resolve())

  const Host = defineComponent({
    setup() {
      provideOrderCard({
        order: ref(data),
        busy: ref(false),
        changeStatus,
        mark: () => Promise.resolve(),
        recordPayment: () => Promise.resolve(),
        editItems: () => Promise.resolve(),
        cancel: () => Promise.resolve(),
        refund: () => Promise.resolve(),
        reverseRefund,
        resend,
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

  return { wrapper, changeStatus, reverseRefund, resend }
}

function delivery(patch: Partial<OrderDelivery> = {}): OrderDelivery {
  return {
    id: 31,
    event: 'orders.placed',
    event_label: 'Order placed',
    channel_id: 2,
    channel: 'Letter to the buyer',
    provider: 'email',
    recipient: 'buyer-a@example.com',
    recipient_label: null,
    status: 'sent',
    trap: null,
    status_label: 'Sent',
    reason: null,
    reason_label: null,
    error: null,
    created_at: '2026-10-30T10:00:00+00:00',
    sent_at: '2026-10-30T10:00:05+00:00',
    resendable: true,
    manual: false,
    manual_by: null,
    ...patch,
  }
}

function withActions(resend: boolean): OrderCardData['actions'] {
  return { ...order().actions, resend }
}

describe('WxOrdersTimeline — «Notifications»', () => {
  it('sends a row that went again from its own button, by the row', async () => {
    const { wrapper, resend } = card(
      OrderTimelineNode,
      order({ notifications: [delivery()], actions: withActions(true) }),
    )

    const button = wrapper.find('[data-test="delivery-resend"]')
    expect(button.exists()).toBe(true)
    expect(wrapper.text()).toContain('Order placed')

    await button.trigger('click')
    expect(resend).toHaveBeenCalledWith(31)
  })

  it('offers nothing to a row that had nobody to go to, nor without the right', () => {
    const skipped = card(
      OrderTimelineNode,
      order({
        notifications: [delivery({ status: 'skipped', recipient: '', resendable: false })],
        actions: withActions(true),
      }),
    )
    expect(skipped.wrapper.find('[data-test="delivery-resend"]').exists()).toBe(false)

    const reader = card(
      OrderTimelineNode,
      order({ notifications: [delivery()], actions: withActions(false) }),
    )
    expect(reader.wrapper.find('[data-test="delivery-resend"]').exists()).toBe(false)
  })

  it('marks a row sent by hand, with who sent it', () => {
    const { wrapper } = card(
      OrderTimelineNode,
      order({
        notifications: [delivery({ manual: true, manual_by: 'Manager A' })],
        actions: withActions(true),
      }),
    )

    const mark = wrapper.find('[data-test="delivery-manual"]')
    expect(mark.text()).toBe('Sent by hand')
    expect(mark.attributes('title')).toBe('Sent by hand: Manager A')
  })

  it('names a message sent again in the history, with its addressee', () => {
    const { wrapper } = card(
      OrderTimelineNode,
      order({
        timeline: [
          {
            key: 'history-5',
            kind: 'notification_sent',
            at: '2026-10-30T12:00:00+00:00',
            source: 'panel',
            admin: 'Manager A',
            note: 'buyer-a@example.com',
            event: 'orders.placed',
            event_label: 'Order placed',
          },
        ],
      }),
    )

    expect(wrapper.text()).toContain('Message sent again: Order placed')
    expect(wrapper.text()).toContain('buyer-a@example.com')
    expect(wrapper.text()).toContain('Manager A')
  })

  it('tells an edit of the lines: what changed, the total, the balance, the reason', () => {
    const { wrapper } = card(
      OrderTimelineNode,
      order({
        timeline: [
          {
            key: 'history-8',
            kind: 'items',
            at: '2026-10-30T12:00:00+00:00',
            source: 'panel',
            admin: 'Manager A',
            note: 'The buyer called back',
            revision: {
              number: 2,
              changes: [
                {
                  op: 'add',
                  item_id: 41,
                  name: 'Item D',
                  options_label: 'Red',
                  price: '$15.00',
                  qty_from: 0,
                  qty_to: 1,
                  total_from: '$0.00',
                  total_to: '$15.00',
                },
                {
                  op: 'qty',
                  item_id: 40,
                  name: 'Item A',
                  options_label: null,
                  price: '$10.00',
                  qty_from: 2,
                  qty_to: 3,
                  total_from: '$20.00',
                  total_to: '$30.00',
                },
                {
                  op: 'remove',
                  item_id: 39,
                  name: 'Item B',
                  options_label: null,
                  price: '$5.00',
                  qty_from: 1,
                  qty_to: 0,
                  total_from: '$5.00',
                  total_to: '$0.00',
                },
              ],
              total_from: '$120.00',
              total_to: '$135.00',
              balance: { kind: 'due', amount: '$15.00' },
              message: null,
            },
          },
        ],
      }),
    )

    const text = wrapper.text()

    expect(text).toContain('Lines changed (edit 2)')
    expect(text).toContain('Added: Item D (Red) × 1')
    expect(text).toContain('Item A: 2 → 3')
    expect(text).toContain('Taken out: Item B')
    expect(text).toContain('Total: $120.00 → $135.00')
    expect(text).toContain('Owed after it: $15.00')
    expect(wrapper.find('[data-test="timeline-reason"]').text()).toContain('The buyer called back')
  })
})
