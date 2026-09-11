import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxSegmented from './Segmented.vue'

const options = [
  { label: 'Day', value: 'day' },
  { label: 'Week', value: 'week' },
  { label: 'Month', value: 'month', disabled: true },
]

describe('WxSegmented', () => {
  it('renders a segment per option', () => {
    const wrapper = mount(WxSegmented, { props: { options } })

    expect(wrapper.findAll('.wx-segmented__option')).toHaveLength(3)
  })

  it('is a radio group, not a row of buttons', () => {
    const wrapper = mount(WxSegmented, { props: { options, modelValue: 'week' } })

    expect(wrapper.attributes('role')).toBe('radiogroup')
    const segments = wrapper.findAll('[role=radio]')
    expect(segments[1].attributes('aria-checked')).toBe('true')
    expect(segments[0].attributes('aria-checked')).toBe('false')
  })

  it('keeps one tab stop, on the chosen segment', () => {
    const wrapper = mount(WxSegmented, { props: { options, modelValue: 'week' } })
    const segments = wrapper.findAll('.wx-segmented__option')

    expect(segments[1].attributes('tabindex')).toBe('0')
    expect(segments[0].attributes('tabindex')).toBe('-1')
  })

  it('chooses and reports', async () => {
    const wrapper = mount(WxSegmented, { props: { options, modelValue: 'day' } })

    await wrapper.findAll('.wx-segmented__option')[1].trigger('click')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['week'])
    expect(wrapper.emitted('change')?.at(-1)).toEqual(['week'])
  })

  it('says nothing when the chosen one is chosen again', async () => {
    const wrapper = mount(WxSegmented, { props: { options, modelValue: 'day' } })

    await wrapper.findAll('.wx-segmented__option')[0].trigger('click')

    expect(wrapper.emitted('change')).toBeUndefined()
  })

  it('leaves a disabled option alone', async () => {
    const wrapper = mount(WxSegmented, { props: { options, modelValue: 'day' } })

    await wrapper.findAll('.wx-segmented__option')[2].trigger('click')

    expect(wrapper.emitted('change')).toBeUndefined()
  })
})
