import { describe, expect, it } from 'vitest'
import { mount, type VueWrapper } from '@vue/test-utils'
import WxScreenRenderer from './ScreenRenderer.vue'
import type { ScreenModel, ScreenNode } from './types'

const root: ScreenNode[] = [
  {
    id: 'offices',
    type: 'wx-repeater',
    name: 'contacts.offices',
    label: 'Offices',
    props: { itemLabel: 'city', addLabel: 'Add an office' },
    children: [
      { id: 'office-city', type: 'wx-input', name: 'city', label: 'City' },
      { id: 'office-hq', type: 'wx-switch', name: 'hq', label: 'Head office' },
      {
        id: 'office-floor',
        type: 'wx-input',
        name: 'floor',
        label: 'Floor',
        visible: { when: 'hq', is: true },
      },
    ],
  },
]

function mountScreen(model: ScreenModel = {}) {
  const held: { wrapper?: VueWrapper } = {}

  const wrapper = mount(WxScreenRenderer, {
    attachTo: document.body,
    props: {
      root,
      modelValue: model,
      'onUpdate:modelValue': (value: ScreenModel) => held.wrapper?.setProps({ modelValue: value }),
    },
  })

  held.wrapper = wrapper
  return wrapper
}

function offices(wrapper: VueWrapper) {
  const props = wrapper.props() as unknown as { modelValue: ScreenModel }

  return props.modelValue['contacts.offices'] as ScreenModel[]
}

describe('wx-repeater', () => {
  it('draws the children once per item, bound to the keys of that item', () => {
    const wrapper = mountScreen({
      'contacts.offices': [{ city: 'Kyiv' }, { city: 'Lviv' }],
    })

    const inputs = wrapper.findAll('input[name="city"]')
    expect(inputs).toHaveLength(2)
    expect((inputs[1]!.element as HTMLInputElement).value).toBe('Lviv')
    expect(wrapper.findAll('.wx-repeater__title').map((one) => one.text())).toEqual([
      'Kyiv',
      'Lviv',
    ])
  })

  it('writes a field of one item back into the screen model', async () => {
    const before = { 'contacts.offices': [{ city: 'Kyiv' }, { city: 'Lviv' }] }
    const wrapper = mountScreen(before)

    await wrapper.findAll('input[name="city"]')[1]!.setValue('Odesa')

    expect(offices(wrapper)).toEqual([{ city: 'Kyiv' }, { city: 'Odesa' }])
    expect(before['contacts.offices'][1]).toEqual({ city: 'Lviv' })
  })

  it('appends an empty item, and the node props reach the repeater', async () => {
    const wrapper = mountScreen({ 'contacts.offices': [] })

    expect(wrapper.get('.wx-repeater__add').text()).toContain('Add an office')

    await wrapper.get('.wx-repeater__add').trigger('click')

    expect(offices(wrapper)).toEqual([{}])
    expect(wrapper.findAll('input[name="city"]')).toHaveLength(1)
  })

  it('evaluates a condition inside a row against that row', async () => {
    const wrapper = mountScreen({
      'contacts.offices': [{ city: 'Kyiv', hq: true }, { city: 'Lviv' }],
    })

    expect(wrapper.findAll('input[name="floor"]')).toHaveLength(1)

    await wrapper.setProps({
      modelValue: { 'contacts.offices': [{ city: 'Kyiv' }, { city: 'Lviv' }] },
    })

    expect(wrapper.findAll('input[name="floor"]')).toHaveLength(0)
  })

  it('survives a value that is not a list', () => {
    const wrapper = mountScreen({ 'contacts.offices': null })

    expect(wrapper.find('.wx-repeater').exists()).toBe(true)
    expect(wrapper.findAll('.wx-repeater__row')).toHaveLength(0)
  })
})
