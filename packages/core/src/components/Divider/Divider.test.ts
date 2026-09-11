import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxDivider from './Divider.vue'

describe('WxDivider', () => {
  it('is a separator when it carries nothing', () => {
    const wrapper = mount(WxDivider)

    expect(wrapper.attributes('role')).toBe('separator')
    expect(wrapper.classes()).toContain('wx-divider--horizontal')
    expect(wrapper.find('.wx-divider__label').exists()).toBe(false)
  })

  it('marks a vertical rule as one', () => {
    const wrapper = mount(WxDivider, { props: { direction: 'vertical' } })

    expect(wrapper.attributes('aria-orientation')).toBe('vertical')
    expect(wrapper.classes()).toContain('wx-divider--vertical')
  })

  it('drops the separator role once it has a label', () => {
    const wrapper = mount(WxDivider, { props: { label: 'Or' } })

    expect(wrapper.attributes('role')).toBeUndefined()
    expect(wrapper.get('.wx-divider__label').text()).toBe('Or')
    expect(wrapper.classes()).toContain('wx-divider--labelled')
    expect(wrapper.classes()).toContain('wx-divider--align-center')
  })

  it('applies the variant and spacing modifiers', () => {
    const wrapper = mount(WxDivider, { props: { variant: 'dashed', spacing: 'lg' } })

    expect(wrapper.classes()).toContain('wx-divider--dashed')
    expect(wrapper.classes()).toContain('wx-divider--spacing-lg')
  })

  it('aligns the label only when there is one', () => {
    const plain = mount(WxDivider, { props: { align: 'start' } })
    expect(plain.classes()).not.toContain('wx-divider--align-start')

    const labelled = mount(WxDivider, { props: { align: 'start' }, slots: { default: 'Meta' } })
    expect(labelled.classes()).toContain('wx-divider--align-start')
  })
})
