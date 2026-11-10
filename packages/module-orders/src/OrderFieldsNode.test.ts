import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import { provideOrderCard } from './card'
import OrderFieldsNode from './OrderFieldsNode.vue'
import type { OrderCardData, OrderFieldRow } from './types'

const rows: OrderFieldRow[] = [
  {
    key: 'installation',
    type: 'checkbox',
    label: 'Installation needed',
    value: true,
    text: 'Yes',
    known: true,
    options: [],
  },
  {
    key: 'old_note',
    type: 'text',
    label: 'Old question',
    value: 'Kept as it was',
    text: 'Kept as it was',
    known: false,
    options: [],
  },
  {
    key: 'delivery_time',
    type: 'select',
    label: 'Delivery time',
    value: null,
    text: null,
    known: true,
    options: [{ value: 'evening', label: '18:00–21:00' }],
  },
]

function order(fields: OrderFieldRow[], manage = true): OrderCardData {
  return {
    id: 7,
    number: '1049',
    fields,
    actions: { details: manage },
  } as unknown as OrderCardData
}

function card(data: OrderCardData, answer: true | Record<string, string[]> = true) {
  const i18n = createI18n()
  const admin = { apiPath: '/api/cms', http: {}, i18n, assetUrls: null } as unknown as AdminContext
  const saveFields = vi.fn<
    (fields: Record<string, boolean | string | null>) => Promise<true | Record<string, string[]>>
  >(() => Promise.resolve(answer))

  const Host = defineComponent({
    setup() {
      provideOrderCard({
        order: ref(data),
        busy: ref(false),
        changeStatus: () => Promise.resolve(),
        mark: () => Promise.resolve(),
        recordPayment: () => Promise.resolve(),
        editItems: () => Promise.resolve(),
        cancel: () => Promise.resolve(),
        refund: () => Promise.resolve(),
        reverseRefund: () => Promise.resolve(),
        resend: () => Promise.resolve(),
        note: () => Promise.resolve(true),
        pin: () => Promise.resolve(true),
        saveFields,
        linkVisitor: () => Promise.resolve(),
        unlinkVisitor: () => Promise.resolve(),
      })

      return () => h(OrderFieldsNode)
    },
  })

  const wrapper = mount(Host, {
    attachTo: document.body,
    global: { provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n } },
  })

  return { wrapper, saveFields }
}

/**
 * The card «Additional» (WEBX_UI_ORDER_FIELDS.md §6.1): «label — value» from the order's rows, a
 * pencil for whoever may manage the order, and only the rows changed sent back.
 */
describe('WxOrdersFields', () => {
  it('draws the rows, «not filled» for a field with no answer, and marks a field that is gone', () => {
    const { wrapper } = card(order(rows))

    const text = wrapper.text()
    expect(text).toContain('Installation needed')
    expect(text).toContain('Yes')
    expect(text).toContain('Kept as it was')
    expect(text).toContain('No longer asked at the checkout')
    expect(text).toContain('Delivery time')
    expect(text).toContain('Not filled')
    wrapper.unmount()
  })

  it('has no card without rows, and no pencil without the right to manage', () => {
    expect(card(order([])).wrapper.find('.wx-orders-fields').exists()).toBe(false)

    const { wrapper } = card(order(rows, false))
    expect(wrapper.find('.wx-orders-fields').exists()).toBe(true)
    expect(wrapper.find('.wx-orders-fields__pencil').exists()).toBe(false)
    wrapper.unmount()
  })

  it('sends only what changed — a tick taken away as null, a line as typed', async () => {
    const { wrapper, saveFields } = card(order(rows))

    await wrapper.find('.wx-orders-fields__pencil').trigger('click')
    await wrapper.find('input[type="checkbox"]').setValue(false)
    await wrapper.find('input[type="text"]').setValue('  Said by phone  ')
    await wrapper
      .findAll('button')
      .find((b) => b.text() === 'Save')!
      .trigger('click')
    await flushPromises()

    expect(saveFields).toHaveBeenCalledWith({ installation: null, old_note: 'Said by phone' })
    expect(wrapper.find('.wx-orders-fields__form').exists()).toBe(false)
    wrapper.unmount()
  })

  it('stays open with the errors of the server under their rows', async () => {
    const { wrapper, saveFields } = card(order(rows), {
      'fields.old_note': ['Too long.'],
    })

    await wrapper.find('.wx-orders-fields__pencil').trigger('click')
    await wrapper.find('input[type="text"]').setValue('Another')
    await wrapper
      .findAll('button')
      .find((b) => b.text() === 'Save')!
      .trigger('click')
    await flushPromises()

    expect(saveFields).toHaveBeenCalledOnce()
    expect(wrapper.find('.wx-orders-fields__form').exists()).toBe(true)
    expect(wrapper.text()).toContain('Too long.')
    wrapper.unmount()
  })

  it('closes without a request when nothing changed', async () => {
    const { wrapper, saveFields } = card(order(rows))

    await wrapper.find('.wx-orders-fields__pencil').trigger('click')
    await wrapper
      .findAll('button')
      .find((b) => b.text() === 'Save')!
      .trigger('click')
    await flushPromises()

    expect(saveFields).not.toHaveBeenCalled()
    expect(wrapper.find('.wx-orders-fields__form').exists()).toBe(false)
    wrapper.unmount()
  })
})
