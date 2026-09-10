import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxSwitch from './Switch.vue'

describe('WxSwitch', () => {
  it('renders a checkbox with the switch role', () => {
    const wrapper = mount(WxSwitch, { props: { modelValue: false } })
    const input = wrapper.get('input')

    expect(input.attributes('type')).toBe('checkbox')
    expect(input.attributes('role')).toBe('switch')
  })

  it('toggles between true and false', async () => {
    const wrapper = mount(WxSwitch, { props: { modelValue: false } })

    await wrapper.get('input').setValue(true)

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([true])
    expect(wrapper.emitted('change')?.at(-1)).toEqual([true])
  })

  it('supports custom active and inactive values', async () => {
    const wrapper = mount(WxSwitch, {
      props: { modelValue: 'off', activeValue: 'on', inactiveValue: 'off' },
    })

    await wrapper.get('input').setValue(true)
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['on'])

    await wrapper.setProps({ modelValue: 'on' })
    expect(wrapper.get('input').element.checked).toBe(true)

    await wrapper.get('input').setValue(false)
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['off'])
  })

  it('marks the checked state on the root', async () => {
    const wrapper = mount(WxSwitch, { props: { modelValue: true } })
    expect(wrapper.classes()).toContain('is-checked')

    await wrapper.setProps({ modelValue: false })
    expect(wrapper.classes()).not.toContain('is-checked')
  })

  it('renders the label', () => {
    const wrapper = mount(WxSwitch, { props: { modelValue: false, label: 'Published' } })

    expect(wrapper.get('.wx-switch__label').text()).toBe('Published')
  })

  it('disables the native input', () => {
    const wrapper = mount(WxSwitch, { props: { modelValue: false, disabled: true } })

    expect(wrapper.get('input').attributes('disabled')).toBeDefined()
    expect(wrapper.classes()).toContain('is-disabled')
  })
})
