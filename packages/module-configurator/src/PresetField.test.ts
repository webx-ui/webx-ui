import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import type { RenderContext } from '@webx-ui/schema'
import PresetField from './PresetField.vue'
import RequestEnclosure from './RequestEnclosure.vue'
import type { ConfiguratorCandidate } from './types'

/* What `ConfiguratorTarget` answers: the row with the steps and sizes of the published schema. */
const SHOWER: ConfiguratorCandidate = {
  id: 1,
  title: 'Showers',
  subtitle: 'shower',
  thumb: null,
  visible: true,
  key: 'shower',
  choices: [
    {
      key: 'construction',
      kind: 'choice',
      label: 'Construction',
      initial: 'corner',
      options: [
        { value: 'corner', label: 'Corner' },
        { value: 'niche', label: 'Niche' },
      ],
    },
    {
      key: 'h',
      kind: 'size',
      label: 'Height',
      unit: 'mm',
      min: 1300,
      max: 2400,
      step: 50,
      initial: null,
    },
  ],
}

function admin() {
  const i18n = createI18n()
  const get = vi.fn(() => Promise.resolve({ data: [SHOWER] }))

  return {
    i18n,
    get,
    context: { apiPath: '/api/cms', http: { get }, i18n } as unknown as AdminContext,
  }
}

function preset(configurator: string | null, value: Record<string, string | number> | null = null) {
  const { i18n, get, context } = admin()
  const render = { model: { configurator } } as unknown as RenderContext

  const wrapper = mount(PresetField, {
    props: { modelValue: value, context: render },
    global: { provide: { [adminKey as symbol]: context, [i18nKey as symbol]: i18n } },
  })

  const last = () => wrapper.emitted('update:modelValue')?.at(-1)?.[0]

  return { wrapper, get, last }
}

describe('WxConfiguratorPresetField', () => {
  it('asks to choose a configurator first', async () => {
    const { wrapper, get } = preset(null)
    await flushPromises()

    expect(get).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Choose a configurator above first.')
  })

  it('offers the steps and sizes of the configurator beside it', async () => {
    const { wrapper, get } = preset('shower')
    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/relations/configurator', { query: { q: 'shower' } })
    expect(wrapper.findAll('.wx-configurator-preset__label').map((one) => one.text())).toEqual([
      'Construction',
      'Height, mm',
    ])
  })

  it('keeps what was chosen and drops what is back on the start', async () => {
    const { wrapper, last } = preset('shower', { construction: 'niche', gone: 'x' })
    await flushPromises()

    const field = wrapper.findComponent(PresetField)
    const [select] = field.findAllComponents({ name: 'WxSelect' })
    select!.vm.$emit('update:modelValue', 'corner')
    expect(last()).toEqual({ construction: 'corner', gone: 'x' })

    // «As the configurator starts» is no value: the block follows the schema there.
    select!.vm.$emit('update:modelValue', '')
    expect(last()).toEqual({ gone: 'x' })
  })

  it('says a configurator that is not on the site has nothing to start from', async () => {
    const { i18n, context } = admin()
    ;(context.http.get as ReturnType<typeof vi.fn>).mockResolvedValue({ data: [] })

    const wrapper = mount(PresetField, {
      props: {
        modelValue: null,
        context: { model: { configurator: 'draft' } } as unknown as RenderContext,
      },
      global: { provide: { [adminKey as symbol]: context, [i18nKey as symbol]: i18n } },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('not on the site yet')
  })
})

describe('WxConfiguratorRequest', () => {
  it('draws the configuration of a request as the server counted it', () => {
    const { i18n, context } = admin()

    const wrapper = mount(RequestEnclosure, {
      props: {
        payload: {
          kind: 'configurator',
          version: 3,
          selection: [
            { step: 'construction', label: 'Construction', value: 'corner', option: 'Corner' },
          ],
          dimensions: [{ key: 'h', label: 'Height', value: 2000, unit: 'mm' }],
          area: 3.6,
          currency: 'UAH',
          total_before_discount: 23900,
          discount: { percent: 10, amount: -2391.2, label: 'Autumn' },
          total: 21500,
          lines: [
            { kind: 'fixed', amount: 8000, line: 'Construction: Corner' },
            { kind: 'discount', amount: -2391.2, line: 'Autumn, −10%' },
          ],
        },
      },
      global: { provide: { [adminKey as symbol]: context, [i18nKey as symbol]: i18n } },
    })

    expect(wrapper.text()).toContain('Priced by version 3')
    expect(wrapper.findAll('dt').map((one) => one.text())).toEqual([
      'Construction',
      'Height',
      'Area',
    ])
    expect(wrapper.find('.is-discount').text()).toContain('Autumn, −10%')
    expect(wrapper.find('.wx-configurator-request__before s').text()).toContain('23,900')
    expect(wrapper.find('.wx-configurator-request__total strong').text()).toContain('21,500')
  })
})
