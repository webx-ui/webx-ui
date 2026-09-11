import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxContainer from './Container.vue'

describe('WxContainer', () => {
  it('stacks vertically by default', () => {
    const wrapper = mount(WxContainer, { slots: { default: 'Page' } })

    expect(wrapper.element.tagName).toBe('DIV')
    expect(wrapper.classes()).toContain('wx-container--vertical')
    expect(wrapper.text()).toBe('Page')
  })

  it('goes horizontal for a sidebar beside the page', () => {
    const wrapper = mount(WxContainer, { props: { direction: 'horizontal' } })

    expect(wrapper.classes()).toContain('wx-container--horizontal')
  })

  it('fills the viewport only when asked, and renders through another element', () => {
    expect(mount(WxContainer).classes()).not.toContain('wx-container--full-height')

    const wrapper = mount(WxContainer, { props: { fullHeight: true, as: 'section' } })
    expect(wrapper.classes()).toContain('wx-container--full-height')
    expect(wrapper.element.tagName).toBe('SECTION')
  })
})
