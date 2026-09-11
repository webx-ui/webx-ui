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

  it('gives the far end a group of its own', () => {
    const wrapper = mount(WxFooter, {
      slots: { default: 'Docs', end: 'v0.6.0' },
    })

    expect(wrapper.get('.wx-footer__end').text()).toBe('v0.6.0')
    expect(mount(WxFooter).find('.wx-footer__end').exists()).toBe(false)
  })

  it('takes a height and a padding scale', () => {
    const wrapper = mount(WxFooter, { props: { height: 40, padding: 'lg' } })

    expect(wrapper.attributes('style')).toContain('--wx-footer-height: 40px')
    expect(wrapper.classes()).toContain('wx-footer--padding-lg')
  })
})
