import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import CheckoutFieldsNode from './CheckoutFieldsNode.vue'
import type { CheckoutField, CheckoutFieldsData } from './types'

function field(key: string, place: string, extra: Partial<CheckoutField> = {}): CheckoutField {
  return {
    id: key.length,
    key,
    type: 'checkbox',
    place,
    place_known: true,
    name: key,
    label: { en: key },
    hint: {},
    options: [],
    required: false,
    active: true,
    parent: null,
    position: 0,
    ...extra,
  }
}

const data: CheckoutFieldsData = {
  places: [
    { key: 'contacts', step: 'contacts', label: 'Contacts' },
    { key: 'extra-before-comment', step: 'comment', label: 'Before the comment' },
  ],
  fields: [
    field('for_company', 'contacts'),
    field('company_name', 'contacts', { type: 'text', parent: 'for_company', required: true }),
    field('installation', 'extra-before-comment'),
    field('no_callback', 'extra-before-comment'),
    field('gone', 'delivery-after', { place_known: false }),
  ],
  limits: { fields: 30, options: 50, label: 190, hint: 500 },
}

function mountNode(can = true) {
  const i18n = createI18n()
  const put = vi.fn((_url: string, body: { keys: string[] }) =>
    Promise.resolve({
      data: {
        ...data,
        fields: body.keys.map((key) => data.fields.find((one) => one.key === key)!),
      },
    }),
  )
  const http = { get: vi.fn(() => Promise.resolve({ data })), put }
  const admin = {
    apiPath: '/api/cms',
    http,
    i18n,
    assetUrls: null,
    can: () => can,
  } as unknown as AdminContext

  const wrapper = mount(CheckoutFieldsNode, {
    attachTo: document.body,
    global: { provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n } },
  })

  return { wrapper, http }
}

/**
 * The extra fields of the checkout (WEBX_UI_ORDER_FIELDS.md §7.1): grouped by the places of the
 * registry, the fields behind a checkbox under it, a field of a place the site lost listed apart —
 * and a drag sends every key once, a checkbox's fields right after it.
 */
describe('WxOrdersCheckoutFields', () => {
  it('groups the fields by place, the ones behind a checkbox under it', async () => {
    const { wrapper } = mountNode()
    await flushPromises()

    const titles = wrapper.findAll('.wx-sortable-list__title').map((one) => one.text())
    expect(titles).toEqual(['Contacts', 'When «for_company» is ticked', 'Before the comment'])

    const behind = wrapper.find('.wx-orders-checkout-fields__behind')
    expect(behind.text()).toContain('company_name')
    expect(behind.text()).toContain('Required')

    const lost = wrapper.find('.wx-orders-checkout-fields__lost')
    expect(lost.text()).toContain('Place unavailable: delivery-after')
    wrapper.unmount()
  })

  it('sends the whole order when a row is moved', async () => {
    const { wrapper, http } = mountNode()
    await flushPromises()

    // «no_callback» up above «installation», by the keyboard of the grip.
    const grip = wrapper.findAll('.wx-sortable-list__grip').at(-1)!
    await grip.trigger('keydown', { key: ' ' })
    await grip.trigger('keydown', { key: 'ArrowUp' })
    await flushPromises()

    expect(http.put).toHaveBeenCalledWith('/api/cms/orders/checkout-fields/arrange', {
      keys: ['for_company', 'company_name', 'no_callback', 'installation', 'gone'],
    })
    wrapper.unmount()
  })

  it('offers no adding, dragging or switching without the right', async () => {
    const { wrapper } = mountNode(false)
    await flushPromises()

    expect(wrapper.text()).not.toContain('Add a field')
    expect(
      wrapper
        .findAll('input[type=checkbox]')
        .every((one) => one.attributes('disabled') !== undefined),
    ).toBe(true)
    wrapper.unmount()
  })
})
