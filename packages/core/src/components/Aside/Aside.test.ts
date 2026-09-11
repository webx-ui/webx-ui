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
})
