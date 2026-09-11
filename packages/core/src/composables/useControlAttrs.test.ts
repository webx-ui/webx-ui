import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxSelect from '../components/Select/Select.vue'
import WxInput from '../components/Input/Input.vue'
import WxSwitch from '../components/Switch/Switch.vue'
import WxDatePicker from '../components/DatePicker/DatePicker.vue'

/**
 * The caller writes `class` on the control, meaning the control. It has to land on
 * the element they can see and style — the root — rather than on the input inside
 * it, which a parent's scoped CSS cannot reach anyway.
 */
describe('class and style on a control', () => {
  it('puts a class on the root of a select that has no search field', () => {
    const wrapper = mount(WxSelect, {
      props: { options: [{ label: 'One', value: 1 }] },
      attrs: { class: 'w-60' },
    })

    // It used to be bound to the search field, which a plain select never renders.
    expect(wrapper.get('.wx-select').classes()).toContain('w-60')
  })

  it('puts a class on the root rather than the input', () => {
    const wrapper = mount(WxInput, { attrs: { class: 'w-60' } })

    expect(wrapper.get('.wx-input').classes()).toContain('w-60')
    expect(wrapper.get('input').classes()).not.toContain('w-60')
  })

  it('carries style the same way', () => {
    const wrapper = mount(WxDatePicker, { attrs: { style: 'width: 260px' } })

    expect(wrapper.get('.wx-datepicker').attributes('style')).toContain('260px')
  })

  it('still gives everything else to the control', () => {
    const wrapper = mount(WxInput, {
      attrs: { class: 'w-60', autocomplete: 'off', 'data-test': 'email' },
    })

    const input = wrapper.get('input')
    expect(input.attributes('autocomplete')).toBe('off')
    expect(input.attributes('data-test')).toBe('email')
  })

  it('works where the root is the label', () => {
    const wrapper = mount(WxSwitch, { attrs: { class: 'mt-2' } })

    expect(wrapper.get('.wx-switch').classes()).toContain('mt-2')
  })
})
