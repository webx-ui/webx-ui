import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxButton from './Button.vue'

describe('WxButton', () => {
  it('renders the default slot inside a <button>', () => {
    const wrapper = mount(WxButton, { slots: { default: 'Save' } })

    expect(wrapper.element.tagName).toBe('BUTTON')
    expect(wrapper.attributes('type')).toBe('button')
    expect(wrapper.text()).toBe('Save')
  })

  it('applies type, variant and size modifier classes', () => {
    const wrapper = mount(WxButton, {
      props: { type: 'danger', variant: 'outline', size: 'lg' },
    })

    expect(wrapper.classes()).toContain('wx-button')
    expect(wrapper.classes()).toContain('wx-button--danger')
    expect(wrapper.classes()).toContain('wx-button--outline')
    expect(wrapper.classes()).toContain('wx-button--lg')
  })

  it('emits click when enabled', async () => {
    const wrapper = mount(WxButton)

    await wrapper.trigger('click')

    expect(wrapper.emitted('click')).toHaveLength(1)
  })

  it('does not emit click when disabled', async () => {
    const wrapper = mount(WxButton, { props: { disabled: true } })

    await wrapper.trigger('click')

    expect(wrapper.emitted('click')).toBeUndefined()
    expect(wrapper.attributes('disabled')).toBeDefined()
  })

  it('shows a spinner and blocks clicks while loading', async () => {
    const wrapper = mount(WxButton, { props: { loading: true } })

    await wrapper.trigger('click')

    expect(wrapper.find('.wx-button__spinner').exists()).toBe(true)
    expect(wrapper.attributes('aria-busy')).toBe('true')
    expect(wrapper.emitted('click')).toBeUndefined()
  })

  it('renders an anchor when href is given', () => {
    const wrapper = mount(WxButton, {
      props: { href: 'https://example.com', target: '_blank' },
      slots: { default: 'Docs' },
    })

    expect(wrapper.element.tagName).toBe('A')
    expect(wrapper.attributes('href')).toBe('https://example.com')
    expect(wrapper.attributes('target')).toBe('_blank')
  })

  it('drops the href of a disabled link and marks it aria-disabled', () => {
    const wrapper = mount(WxButton, {
      props: { href: 'https://example.com', disabled: true },
    })

    expect(wrapper.attributes('href')).toBeUndefined()
    expect(wrapper.attributes('aria-disabled')).toBe('true')
  })

  it('passes fallthrough attributes to the root element', () => {
    const wrapper = mount(WxButton, { attrs: { 'data-test': 'submit', id: 'save' } })

    expect(wrapper.attributes('data-test')).toBe('submit')
    expect(wrapper.attributes('id')).toBe('save')
  })
})
