import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxInputNumber from './InputNumber.vue'

const decrease = '.wx-input-number__button--decrease'
const increase = '.wx-input-number__button--increase'

describe('WxInputNumber', () => {
  it('renders a spinbutton with both controls', () => {
    const wrapper = mount(WxInputNumber, { props: { modelValue: 3 } })

    expect(wrapper.get('input').attributes('role')).toBe('spinbutton')
    expect(wrapper.get('input').element.value).toBe('3')
    expect(wrapper.find(decrease).exists()).toBe(true)
    expect(wrapper.find(increase).exists()).toBe(true)
  })

  it('hides the controls when asked', () => {
    const wrapper = mount(WxInputNumber, { props: { modelValue: 1, controls: false } })

    expect(wrapper.find(increase).exists()).toBe(false)
  })

  it('steps up and down by step', async () => {
    const wrapper = mount(WxInputNumber, { props: { modelValue: 5, step: 2 } })

    await wrapper.get(increase).trigger('click')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([7])

    await wrapper.setProps({ modelValue: 7 })
    await wrapper.get(decrease).trigger('click')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([5])
  })

  it('does not accumulate floating point noise', async () => {
    const wrapper = mount(WxInputNumber, { props: { modelValue: 0.1, step: 0.2 } })

    await wrapper.get(increase).trigger('click')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([0.3])
  })

  it('clamps to min and max and disables the button at the bound', async () => {
    const wrapper = mount(WxInputNumber, { props: { modelValue: 10, max: 10, min: 0 } })

    expect(wrapper.get(increase).attributes('disabled')).toBeDefined()

    await wrapper.get(increase).trigger('click')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('steps with the arrow keys', async () => {
    const wrapper = mount(WxInputNumber, { props: { modelValue: 4 } })

    await wrapper.get('input').trigger('keydown', { key: 'ArrowUp' })
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([5])

    await wrapper.setProps({ modelValue: 5 })
    await wrapper.get('input').trigger('keydown', { key: 'ArrowDown' })
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([4])
  })

  it('clamps a typed value on blur rather than while typing', async () => {
    const wrapper = mount(WxInputNumber, { props: { modelValue: 50, min: 10, max: 100 } })
    const input = wrapper.get('input')

    await input.setValue('5')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([5])

    await input.trigger('blur')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([10])
    expect(input.element.value).toBe('10')
  })

  it('treats an empty field as null', async () => {
    const wrapper = mount(WxInputNumber, { props: { modelValue: 7 } })
    const input = wrapper.get('input')

    await input.setValue('')
    await input.trigger('blur')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([null])
  })

  it('restores the last good value when the text cannot be parsed', async () => {
    const wrapper = mount(WxInputNumber, { props: { modelValue: 7 } })
    const input = wrapper.get('input')

    await input.setValue('abc')
    await input.trigger('blur')

    expect(input.element.value).toBe('7')
  })

  it('formats to the given precision', async () => {
    const wrapper = mount(WxInputNumber, { props: { modelValue: 2, precision: 2, step: 0.5 } })

    await wrapper.get(increase).trigger('click')

    expect(wrapper.get('input').element.value).toBe('2.50')
  })

  it('does not step while disabled or readonly', async () => {
    const wrapper = mount(WxInputNumber, { props: { modelValue: 1, disabled: true } })
    await wrapper.get(increase).trigger('click')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()

    const readonly = mount(WxInputNumber, { props: { modelValue: 1, readonly: true } })
    await readonly.get('input').trigger('keydown', { key: 'ArrowUp' })
    expect(readonly.emitted('update:modelValue')).toBeUndefined()
  })

  it('exposes the bounds to assistive tech', () => {
    const wrapper = mount(WxInputNumber, { props: { modelValue: 3, min: 1, max: 9 } })
    const input = wrapper.get('input')

    expect(input.attributes('aria-valuenow')).toBe('3')
    expect(input.attributes('aria-valuemin')).toBe('1')
    expect(input.attributes('aria-valuemax')).toBe('9')
  })
})
