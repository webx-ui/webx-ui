import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxMain from './Main.vue'

describe('WxMain', () => {
  it('renders a padded <main> around its content', () => {
    const wrapper = mount(WxMain, { slots: { default: 'Body' } })

    expect(wrapper.element.tagName).toBe('MAIN')
    expect(wrapper.classes()).toContain('wx-main--padding-md')
    expect(wrapper.get('.wx-main__inner').text()).toBe('Body')
  })

  it('caps the content width without capping the column', () => {
    const wrapper = mount(WxMain, { props: { maxWidth: 720 } })

    expect(wrapper.attributes('style')).toContain('--wx-main-max-width: 720px')
  })

  it('scrolls on its own when asked', () => {
    const wrapper = mount(WxMain, { props: { scroll: true, padding: 'none' } })

    expect(wrapper.classes()).toContain('wx-main--scroll')
    expect(wrapper.classes()).toContain('wx-main--padding-none')
  })
})
