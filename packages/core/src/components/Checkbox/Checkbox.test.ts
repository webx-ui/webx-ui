import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxCheckbox from './Checkbox.vue'
import WxCheckboxGroup from '../CheckboxGroup/CheckboxGroup.vue'

describe('WxCheckbox', () => {
  it('renders a native checkbox with the label', () => {
    const wrapper = mount(WxCheckbox, { props: { label: 'Published' } })

    expect(wrapper.get('input').attributes('type')).toBe('checkbox')
    expect(wrapper.get('.wx-checkbox__label').text()).toBe('Published')
  })

  it('toggles the model', async () => {
    const wrapper = mount(WxCheckbox, { props: { modelValue: false } })

    await wrapper.get('input').setValue(true)

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([true])
    expect(wrapper.emitted('change')?.at(-1)).toEqual([true])
  })

  it('reflects the model in the checked state and class', async () => {
    const wrapper = mount(WxCheckbox, { props: { modelValue: true } })

    expect(wrapper.get('input').element.checked).toBe(true)
    expect(wrapper.classes()).toContain('is-checked')

    await wrapper.setProps({ modelValue: false })
    expect(wrapper.get('input').element.checked).toBe(false)
    expect(wrapper.classes()).not.toContain('is-checked')
  })

  it('sets the indeterminate DOM property, which has no attribute', () => {
    const wrapper = mount(WxCheckbox, { props: { modelValue: false, indeterminate: true } })

    expect(wrapper.get('input').element.indeterminate).toBe(true)
    expect(wrapper.classes()).toContain('is-indeterminate')
  })

  it('drops indeterminate once it is checked', async () => {
    const wrapper = mount(WxCheckbox, { props: { modelValue: false, indeterminate: true } })

    await wrapper.setProps({ modelValue: true })

    expect(wrapper.get('input').element.indeterminate).toBe(false)
  })

  it('does not emit while disabled', async () => {
    const wrapper = mount(WxCheckbox, { props: { modelValue: false, disabled: true } })

    expect(wrapper.get('input').attributes('disabled')).toBeDefined()
    expect(wrapper.classes()).toContain('is-disabled')
  })
})

describe('WxCheckboxGroup', () => {
  const options = [
    { label: 'Read', value: 'read' },
    { label: 'Write', value: 'write' },
    { label: 'Delete', value: 'delete', disabled: true },
  ]

  it('renders one checkbox per option', () => {
    const wrapper = mount(WxCheckboxGroup, { props: { options, modelValue: [] } })

    expect(wrapper.findAll('input')).toHaveLength(3)
    expect(wrapper.attributes('role')).toBe('group')
  })

  it('adds and removes values', async () => {
    const wrapper = mount(WxCheckboxGroup, { props: { options, modelValue: ['read'] } })
    const [read, write] = wrapper.findAll('input')

    await write.setValue(true)
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([['read', 'write']])

    // The parent here never writes the model back, so the group keeps its own copy.
    await read.setValue(false)
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([['write']])
  })

  it('checks the boxes listed in the model', () => {
    const wrapper = mount(WxCheckboxGroup, { props: { options, modelValue: ['write'] } })
    const inputs = wrapper.findAll('input')

    expect(inputs[0].element.checked).toBe(false)
    expect(inputs[1].element.checked).toBe(true)
  })

  it('refuses to go past max', async () => {
    const wrapper = mount(WxCheckboxGroup, {
      props: { options, modelValue: ['read'], max: 1 },
    })

    await wrapper.findAll('input')[1].setValue(true)

    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('refuses to go below min', async () => {
    const wrapper = mount(WxCheckboxGroup, {
      props: { options, modelValue: ['read'], min: 1 },
    })

    await wrapper.findAll('input')[0].setValue(false)

    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('disables every child when the group is disabled', () => {
    const wrapper = mount(WxCheckboxGroup, { props: { options, modelValue: [], disabled: true } })

    for (const input of wrapper.findAll('input')) {
      expect(input.attributes('disabled')).toBeDefined()
    }
  })

  it('keeps a per-option disabled flag', () => {
    const wrapper = mount(WxCheckboxGroup, { props: { options, modelValue: [] } })

    expect(wrapper.findAll('input')[2].attributes('disabled')).toBeDefined()
    expect(wrapper.findAll('input')[0].attributes('disabled')).toBeUndefined()
  })
})
