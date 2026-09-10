import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxInput from './Input.vue'

describe('WxInput', () => {
  it('renders an input with the model value', () => {
    const wrapper = mount(WxInput, { props: { modelValue: 'hello' } })

    expect(wrapper.get('input').element.value).toBe('hello')
    expect(wrapper.classes()).toContain('wx-input')
    expect(wrapper.classes()).toContain('wx-input--md')
  })

  it('updates the model and emits input on typing', async () => {
    const wrapper = mount(WxInput, { props: { modelValue: '' } })

    await wrapper.get('input').setValue('webx')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['webx'])
    expect(wrapper.emitted('input')?.at(-1)).toEqual(['webx'])
  })

  it('emits focus and blur and toggles the focused class', async () => {
    const wrapper = mount(WxInput)
    const input = wrapper.get('input')

    await input.trigger('focus')
    expect(wrapper.classes()).toContain('is-focused')

    await input.trigger('blur')
    expect(wrapper.classes()).not.toContain('is-focused')
    expect(wrapper.emitted('focus')).toHaveLength(1)
    expect(wrapper.emitted('blur')).toHaveLength(1)
  })

  it('shows the clear button only when clearable and filled', async () => {
    const wrapper = mount(WxInput, { props: { modelValue: '', clearable: true } })
    expect(wrapper.find('.wx-input__clear').exists()).toBe(false)

    await wrapper.setProps({ modelValue: 'text' })
    expect(wrapper.find('.wx-input__clear').exists()).toBe(true)

    await wrapper.get('.wx-input__clear').trigger('click')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([''])
    expect(wrapper.emitted('clear')).toHaveLength(1)
  })

  it('marks the error status on the wrapper and the input', () => {
    const wrapper = mount(WxInput, { props: { status: 'error' } })

    expect(wrapper.classes()).toContain('wx-input--error')
    expect(wrapper.get('input').attributes('aria-invalid')).toBe('true')
  })

  it('renders a counter when showCount and maxlength are set', () => {
    const wrapper = mount(WxInput, {
      props: { modelValue: 'abc', showCount: true, maxlength: 10 },
    })

    expect(wrapper.get('.wx-input__count').text()).toBe('3/10')
  })

  it('forwards attributes to the inner input, not the wrapper', () => {
    const wrapper = mount(WxInput, { attrs: { name: 'title', id: 'title' } })

    expect(wrapper.get('input').attributes('name')).toBe('title')
    expect(wrapper.attributes('name')).toBeUndefined()
  })

  it('does not clear while disabled', async () => {
    const wrapper = mount(WxInput, {
      props: { modelValue: 'text', clearable: true, disabled: true },
    })

    expect(wrapper.find('.wx-input__clear').exists()).toBe(false)
    expect(wrapper.get('input').attributes('disabled')).toBeDefined()
  })
})
