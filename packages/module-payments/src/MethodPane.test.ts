import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { toast, WebxUI } from '@webx-ui/core'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import MethodPane from './MethodPane.vue'
import type { MethodDetail, SettingField } from './types'

function field(patch: Partial<SettingField> & { name: string }): SettingField {
  return {
    type: 'switch',
    label: patch.name,
    help: null,
    localized: false,
    options: [],
    default: false,
    off_with: null,
    off_help: null,
    ...patch,
  }
}

const instalments = field({
  name: 'instalments',
  label: 'Instalments',
  help: 'Paid in parts',
  off_with: 'hold',
  off_help: 'A hold works for card payments only',
})

function detail(fields: SettingField[], settings: Record<string, unknown>, hold = false) {
  return {
    method: {
      id: 3,
      key: 'gateway-a-card',
      type: 'gateway-a.card',
      type_title: 'Card',
      kind: 'online',
      title: 'Card',
      gateway: 'gateway-a',
      gateway_title: 'Gateway A',
      mode: null,
      credentials: null,
      hold,
      warnings: [],
      enabled: true,
      position: 1,
      group: null,
      group_key: null,
      marks: [],
      similar_to: [],
    },
    values: { titles: {}, enabled: true },
    warnings: [],
    groups: [],
    settings: { fields, values: settings },
    credentials: [],
    credentials_unreadable: false,
    hold: { value: hold, partial: true, days: 7 },
    online: false,
    testable: false,
    notify_url: null,
  } satisfies MethodDetail
}

async function render(data: MethodDetail) {
  const i18n = createI18n()
  const put = vi.fn().mockResolvedValue({ data })
  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get: vi.fn().mockResolvedValue({ data }), put },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
    loadScreen: () =>
      Promise.resolve([
        {
          id: 'group_key',
          type: 'wx-select',
          name: 'group_key',
          label: 'Group',
          props: { options: [] },
        },
        { id: 'settings', type: 'wx-card', visible: false, children: [] },
      ]),
    screenPatch: () => [],
  } as unknown as AdminContext

  const wrapper = mount(MethodPane, {
    attachTo: document.body,
    props: { id: 3 },
    global: {
      plugins: [WebxUI],
      provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
    },
  })
  await flushPromises()

  return { wrapper, put }
}

/** The switches of the type, in the order of the card: its own fields, then the hold. */
function switches(wrapper: Awaited<ReturnType<typeof render>>['wrapper']) {
  const [own, hold] = wrapper.findAll('[role="switch"]')

  return { own: own!, hold: hold! }
}

afterEach(() => {
  document.body.innerHTML = ''
})

describe('a field taken away by a switch', () => {
  it('is off while the hold is on, says why, and comes back when it is off', async () => {
    const { wrapper } = await render(detail([instalments], {}))
    const { own, hold } = switches(wrapper)

    expect(own.attributes('disabled')).toBeUndefined()
    expect(wrapper.text()).toContain('Paid in parts')

    await hold.trigger('click')
    await flushPromises()

    expect(switches(wrapper).own.attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('A hold works for card payments only')
    expect(wrapper.text()).not.toContain('Paid in parts')

    await switches(wrapper).hold.trigger('click')
    await flushPromises()

    expect(switches(wrapper).own.attributes('disabled')).toBeUndefined()
    expect(wrapper.text()).toContain('Paid in parts')
    wrapper.unmount()
  })

  it('is emptied, so the save does not bring back what the server refuses', async () => {
    const { wrapper, put } = await render(detail([instalments], { instalments: true }))

    expect((switches(wrapper).own.element as HTMLInputElement).checked).toBe(true)

    await switches(wrapper).hold.trigger('click')
    await flushPromises()

    expect((switches(wrapper).own.element as HTMLInputElement).checked).toBe(false)

    await wrapper.get('[data-test="method-save"]').trigger('click')
    await flushPromises()

    expect(put.mock.calls[0]?.[1]).toMatchObject({
      settings: { hold: true, instalments: false },
    })
    wrapper.unmount()
  })

  it('opens off when the hold is stored, without a change to save', async () => {
    const { wrapper } = await render(detail([instalments], {}, true))

    expect(switches(wrapper).own.attributes('disabled')).toBeDefined()
    expect(wrapper.get('[data-test="method-save"]').attributes('disabled')).toBeDefined()
    wrapper.unmount()
  })

  it('names the switch in the panel’s own words when the type gave none', async () => {
    const { wrapper } = await render(detail([{ ...instalments, off_help: null }], {}, true))

    expect(wrapper.text()).toContain('Not available while «Hold the money, take it later» is on.')
    wrapper.unmount()
  })

  it('leaves a field that names no switch alone', async () => {
    const { wrapper } = await render(detail([field({ name: 'plain' })], {}))

    await switches(wrapper).hold.trigger('click')
    await flushPromises()

    expect(switches(wrapper).own.attributes('disabled')).toBeUndefined()
    wrapper.unmount()
  })
})

describe('a method like another in its group', () => {
  const words = 'This group already has a method with Google Pay'

  it('says so over the fields, and a save goes through and says it again', async () => {
    const data = { ...detail([], {}), warnings: [words] }
    const warned = vi.spyOn(toast, 'warning')
    const { wrapper, put } = await render(data)

    expect(wrapper.find('[data-test="method-similar"]').text()).toContain(words)

    await switches(wrapper).own.setValue(true)
    await wrapper.find('[data-test="method-save"]').trigger('click')
    await flushPromises()

    expect(put).toHaveBeenCalledOnce()
    expect(wrapper.find('[data-test="method-similar"]').text()).toContain(words)
    expect(warned).toHaveBeenCalledWith(words)
  })

  it('says nothing when there is nothing to say', async () => {
    const { wrapper } = await render(detail([], {}))

    expect(wrapper.find('[data-test="method-similar"]').exists()).toBe(false)
  })
})

describe('the field «Group»', () => {
  const groups = [
    { value: '', label: 'Default (Pay online)' },
    { value: 'transfer', label: 'Bank transfer' },
  ]

  it('stands in the form of a site with groups, and not of a site without', async () => {
    const grouped = await render({ ...detail([], {}), groups })

    expect(grouped.wrapper.find('.wx-select').exists()).toBe(true)

    const flat = await render(detail([], {}))

    expect(flat.wrapper.find('.wx-select').exists()).toBe(false)
  })
})
