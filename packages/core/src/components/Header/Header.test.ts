import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxHeader from './Header.vue'

describe('WxHeader', () => {
  it('renders a bordered <header>', () => {
    const wrapper = mount(WxHeader, { slots: { default: 'Admin' } })

    expect(wrapper.element.tagName).toBe('HEADER')
    expect(wrapper.classes()).toContain('wx-header--bordered')
    expect(wrapper.classes()).toContain('wx-header--padding-md')
  })

  it('takes a height in pixels or as a length', () => {
    expect(mount(WxHeader, { props: { height: 72 } }).attributes('style')).toContain(
      '--wx-header-height: 72px',
    )
    expect(mount(WxHeader, { props: { height: '4rem' } }).attributes('style')).toContain('4rem')
  })

  it('sticks to the top when asked', () => {
    const wrapper = mount(WxHeader, { props: { sticky: true, bordered: false } })

    expect(wrapper.classes()).toContain('wx-header--sticky')
    expect(wrapper.classes()).not.toContain('wx-header--bordered')
  })
})
