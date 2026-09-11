import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxFooter from './Footer.vue'

describe('WxFooter', () => {
  it('renders a bordered <footer>', () => {
    const wrapper = mount(WxFooter, { slots: { default: '© 2026' } })

    expect(wrapper.element.tagName).toBe('FOOTER')
    expect(wrapper.classes()).toContain('wx-footer--bordered')
    expect(wrapper.text()).toBe('© 2026')
  })

  it('takes a height and a padding scale', () => {
    const wrapper = mount(WxFooter, { props: { height: 40, padding: 'lg' } })

    expect(wrapper.attributes('style')).toContain('--wx-footer-height: 40px')
    expect(wrapper.classes()).toContain('wx-footer--padding-lg')
  })
})
