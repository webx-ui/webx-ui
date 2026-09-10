import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxRadio from './Radio.vue'
import WxRadioGroup from '../RadioGroup/RadioGroup.vue'

describe('WxRadio', () => {
  it('renders a native radio with the label', () => {
    const wrapper = mount(WxRadio, { props: { value: 'a', label: 'Option A' } })

    expect(wrapper.get('input').attributes('type')).toBe('radio')
    expect(wrapper.get('.wx-radio__label').text()).toBe('Option A')
  })

  it('writes its own value into the model when picked', async () => {
    const wrapper = mount(WxRadio, { props: { value: 'a', modelValue: undefined } })

    await wrapper.get('input').setValue(true)

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['a'])
    expect(wrapper.emitted('change')?.at(-1)).toEqual(['a'])
  })

  it('is checked only when the model equals its value', async () => {
    const wrapper = mount(WxRadio, { props: { value: 'a', modelValue: 'a' } })
    expect(wrapper.get('input').element.checked).toBe(true)

    await wrapper.setProps({ modelValue: 'b' })
    expect(wrapper.get('input').element.checked).toBe(false)
  })
})

describe('WxRadioGroup', () => {
  const options = [
    { label: 'Draft', value: 'draft' },
    { label: 'Published', value: 'published' },
    { label: 'Archived', value: 'archived', disabled: true },
  ]

  it('renders one radio per option inside a radiogroup', () => {
    const wrapper = mount(WxRadioGroup, { props: { options, modelValue: 'draft' } })

    expect(wrapper.findAll('input')).toHaveLength(3)
    expect(wrapper.attributes('role')).toBe('radiogroup')
  })

  it('gives every radio the same name, which is what makes arrow keys work', () => {
    const wrapper = mount(WxRadioGroup, { props: { options, modelValue: 'draft' } })
    const names = wrapper.findAll('input').map((input) => input.attributes('name'))

    expect(new Set(names).size).toBe(1)
    expect(names[0]).toBeTruthy()
  })

  it('honours an explicit name', () => {
    const wrapper = mount(WxRadioGroup, { props: { options, modelValue: 'draft', name: 'state' } })

    expect(wrapper.get('input').attributes('name')).toBe('state')
  })

  it('selects a value', async () => {
    const wrapper = mount(WxRadioGroup, { props: { options, modelValue: 'draft' } })

    await wrapper.findAll('input')[1].setValue(true)

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['published'])
    expect(wrapper.emitted('change')?.at(-1)).toEqual(['published'])
  })

  it('does not re-emit when the same value is picked again', async () => {
    const wrapper = mount(WxRadioGroup, { props: { options, modelValue: 'draft' } })

    await wrapper.findAll('input')[0].setValue(true)

    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('checks the radio named by the model', () => {
    const wrapper = mount(WxRadioGroup, { props: { options, modelValue: 'published' } })
    const inputs = wrapper.findAll('input')

    expect(inputs[0].element.checked).toBe(false)
    expect(inputs[1].element.checked).toBe(true)
  })

  it('disables every child when the group is disabled', () => {
    const wrapper = mount(WxRadioGroup, { props: { options, modelValue: 'draft', disabled: true } })

    for (const input of wrapper.findAll('input')) {
      expect(input.attributes('disabled')).toBeDefined()
    }
  })
})
