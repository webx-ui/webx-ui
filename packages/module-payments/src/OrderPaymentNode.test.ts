import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { WebxUI } from '@webx-ui/core'
import {
  adminKey,
  createI18n,
  i18nKey,
  provideHistorySubject,
  type AdminContext,
} from '@webx-ui/module-admin'
import EventList from './EventList.vue'
import OrderPaymentNode from './OrderPaymentNode.vue'
import type { EventLine, HoldPreview, OrderAttempt, OrderPayment } from './types'

function event(patch: Partial<EventLine> = {}): EventLine {
  return {
    id: 1,
    trace: 'T1',
    direction: 'in',
    kind: 'notify',
    gateway: 'gateway-a',
    method_key: 'gateway-a-card',
    payment_id: 5,
    order_id: 7,
    order_number: '1031',
    payment_uuid: 'uuid-5',
    source: 'notify',
    http_method: 'POST',
    status: 400,
    transport_error: null,
    request_size: 10,
    response_size: 10,
    truncated: false,
    at: '2026-11-03T10:00:00+00:00',
    duration_ms: 3,
    signature: 'invalid',
    reported: null,
    status_before: 'authorized',
    status_after: 'authorized',
    outcome: 'rejected',
    message: 'The signature does not match',
    error: false,
    claimed: true,
    ...patch,
  }
}

function attempt(patch: Partial<OrderAttempt> = {}): OrderAttempt {
  return {
    id: 5,
    uuid: 'uuid-5',
    order_id: 7,
    order_number: '1031',
    method_key: 'gateway-a-card',
    method_title: 'Card (gateway A)',
    type: 'gateway-a.card',
    amount: 18190,
    amount_formatted: '181,90 €',
    currency: 'EUR',
    status: 'authorized',
    problem: null,
    resolved: false,
    gateway_ref: 'ga_5',
    paid_amount: null,
    paid_amount_formatted: null,
    paid_at: null,
    hold: true,
    authorized_amount: 18190,
    authorized_amount_formatted: '181,90 €',
    hold_expires_at: '2026-11-10T10:00:00+00:00',
    failure_reason: null,
    reference: null,
    source: 'pay-page',
    created_at: '2026-11-03T09:00:00+00:00',
    unfinished: true,
    events: [
      event(),
      event({
        id: 2,
        signature: 'valid',
        status: 200,
        outcome: 'applied',
        claimed: false,
        message: null,
      }),
    ],
    can_resolve: false,
    can_capture: true,
    partial: true,
    amount_decimal: '181,90',
    ...patch,
  }
}

function tab(patch: Partial<OrderPayment> = {}): OrderPayment {
  return {
    order_id: 7,
    number: '1031',
    currency: 'EUR',
    block: {
      method: 'gateway-a-card',
      type: 'gateway-a.card',
      kind: 'online',
      title: 'Card (gateway A)',
      instructions: [],
    },
    state: 'held',
    paid_at: null,
    due: 18190,
    due_formatted: '181,90 €',
    paid: 0,
    paid_formatted: '0,00 €',
    top_up: false,
    attempts: [attempt()],
    other_events: [],
    paid_by: null,
    bodies: true,
    can_manage: true,
    pay_link: null,
    can_mark_paid: false,
    can_check: true,
    can_switch: false,
    methods: [],
    ...patch,
  }
}

function preview(patch: Partial<HoldPreview> = {}): HoldPreview {
  return {
    dry_run: true,
    amount: 10000,
    amount_formatted: '100,00 €',
    held: 18190,
    held_formatted: '181,90 €',
    released: 8190,
    released_formatted: '81,90 €',
    covers: false,
    owes: 18190,
    owes_formatted: '181,90 €',
    left: 8190,
    left_formatted: '81,90 €',
    ...patch,
  }
}

async function render(data: OrderPayment, hold: HoldPreview = preview()) {
  const i18n = createI18n()
  const get = vi.fn().mockResolvedValue({ data })
  const post = vi.fn().mockResolvedValue({ data: hold })
  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, post },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
  } as unknown as AdminContext
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/orders/:id', name: 'webx.orders.show', component: { template: '<div />' } },
      { path: '/:all(.*)', component: { template: '<div />' } },
    ],
  })

  const Host = defineComponent({
    setup() {
      provideHistorySubject({ id: ref(7) })

      return () => h(OrderPaymentNode)
    },
  })

  const wrapper = mount(Host, {
    attachTo: document.body,
    global: {
      plugins: [WebxUI, router],
      provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
    },
  })
  await flushPromises()

  return { wrapper, post }
}

afterEach(() => {
  document.body.innerHTML = ''
})

describe('the tab «Payment»', () => {
  it('marks a claimed notification on its line, not on the head of the attempt', async () => {
    const { wrapper } = await render(tab())
    const head = wrapper.get('[data-test="attempt-5"]')

    // A bad signature later followed by a good one: the attempt's head stays calm.
    expect(head.text()).not.toContain('signature did not match')
    expect(head.find('[data-test="attempt-claimed"]').exists()).toBe(false)
    expect(wrapper.findAll('.wx-payments-events__claimed')).toHaveLength(1)
    wrapper.unmount()
  })

  it('says «captured in part» of an attempt paid short of its amount, «Paid» only when covered', async () => {
    const paid = {
      status: 'paid' as const,
      hold: false,
      can_capture: false,
      amount: 9290,
      amount_formatted: '92,90 €',
    }
    const { wrapper } = await render(
      tab({
        state: 'awaiting',
        attempts: [
          attempt({ ...paid, paid_amount: 5000, paid_amount_formatted: '50,00 €' }),
          attempt({ ...paid, id: 6, paid_amount: 9290, paid_amount_formatted: '92,90 €' }),
        ],
      }),
    )
    const part = wrapper.get('[data-test="attempt-5"]')
    const whole = wrapper.get('[data-test="attempt-6"]')

    expect(part.get('[data-test="attempt-part"]').text()).toBe('Captured 50,00 € of 92,90 €')
    expect(part.text()).not.toContain('Paid')
    expect(whole.find('[data-test="attempt-part"]').exists()).toBe(false)
    expect(whole.text()).toContain('Paid')
    wrapper.unmount()
  })

  it('warns that a part short of the order leaves it unpaid', async () => {
    const { wrapper, post } = await render(tab())

    await wrapper.get('[data-test="attempt-capture"]').trigger('click')
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/payments/orders/7/attempts/5/capture', {
      amount: '181,90',
      dry_run: true,
    })
    const field = document.querySelector('[data-test="hold-amount"]')
    const input = field instanceof HTMLInputElement ? field : field?.querySelector('input')
    expect(input?.value).toBe('181,90')
    const warning = document.querySelector('[data-test="hold-short"]')
    expect(warning?.textContent).toContain(
      'The order is 181,90 €, 100,00 € is captured — 81,90 € will stay unpaid',
    )
    expect(warning?.textContent).toContain('Lower the order’s items first.')
    wrapper.unmount()
  })

  it('says the mark «Paid» where the capture covers the order', async () => {
    const { wrapper } = await render(
      tab(),
      preview({ amount: 18190, amount_formatted: '181,90 €', released: 0, covers: true, left: 0 }),
    )

    await wrapper.get('[data-test="attempt-capture"]').trigger('click')
    await flushPromises()

    expect(document.querySelector('[data-test="hold-short"]')).toBeNull()
    expect(document.querySelector('[data-test="hold-preview"]')?.textContent).toContain(
      'the order gets the mark «Paid»',
    )
    wrapper.unmount()
  })
})

describe('the journal', () => {
  it('links the order of a line to its tab «Payment» with the attempt open', async () => {
    const i18n = createI18n()
    const admin = { apiPath: '/api/cms', http: {}, i18n } as unknown as AdminContext
    const router = createRouter({
      history: createMemoryHistory('/cms'),
      routes: [
        { path: '/orders/:id', name: 'webx.orders.show', component: { template: '<div />' } },
        { path: '/:all(.*)', component: { template: '<div />' } },
      ],
    })
    const wrapper = mount(EventList, {
      props: { events: [event()], context: true },
      global: {
        plugins: [WebxUI, router],
        provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
      },
    })
    await flushPromises()

    const link = wrapper.get('[data-test="event-order"]')
    expect(link.text()).toBe('1031')
    expect(link.attributes('href')).toBe('/cms/orders/7?tab=payment&attempt=uuid-5')
    wrapper.unmount()
  })
})
