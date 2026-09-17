import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxAside from './Aside.vue'

describe('WxAside', () => {
  it('renders an <aside> with a rule on its own side', () => {
    const wrapper = mount(WxAside, { slots: { default: 'Menu' } })

    expect(wrapper.element.tagName).toBe('ASIDE')
    expect(wrapper.classes()).toContain('wx-aside--start')
    expect(wrapper.classes()).toContain('wx-aside--bordered')
  })

  it('takes widths for both states', () => {
    const wrapper = mount(WxAside, { props: { width: 280, collapsedWidth: '56px' } })

    expect(wrapper.attributes('style')).toContain('--wx-aside-width: 280px')
    expect(wrapper.attributes('style')).toContain('--wx-aside-collapsed-width: 56px')
  })

  it('narrows to the rail when collapsed', () => {
    const wrapper = mount(WxAside, { props: { collapsed: true, side: 'end', scroll: true } })

    expect(wrapper.classes()).toContain('wx-aside--collapsed')
    expect(wrapper.classes()).toContain('wx-aside--end')
    expect(wrapper.classes()).toContain('wx-aside--scroll')
  })

  it('keeps the zones around a middle that grows', () => {
    const wrapper = mount(WxAside, {
      slots: { top: 'Brand', default: 'Menu', bottom: 'Account' },
    })

    expect(wrapper.classes()).toContain('wx-aside--zoned')
    expect(wrapper.find('.wx-aside__top').text()).toBe('Brand')
    expect(wrapper.find('.wx-aside__body').text()).toBe('Menu')
    expect(wrapper.find('.wx-aside__bottom').text()).toBe('Account')
  })

  it('wraps the menu only where there are zones to wrap it against', () => {
    const wrapper = mount(WxAside, { slots: { default: 'Menu' } })

    expect(wrapper.classes()).not.toContain('wx-aside--zoned')
    expect(wrapper.find('.wx-aside__body').exists()).toBe(false)
  })

  it('stands still, and draws itself as a card, when asked', () => {
    const wrapper = mount(WxAside, { props: { sticky: true, floating: true, bordered: false } })

    expect(wrapper.classes()).toContain('wx-aside--sticky')
    expect(wrapper.classes()).toContain('wx-aside--floating')
    expect(wrapper.classes()).not.toContain('wx-aside--bordered')
  })
})
