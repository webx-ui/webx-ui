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

  it('gives the far end a group of its own, and only when it is used', () => {
    expect(
      mount(WxHeader, { slots: { default: 'Admin' } })
        .find('.wx-header__end')
        .exists(),
    ).toBe(false)

    const wrapper = mount(WxHeader, {
      slots: { default: 'Admin', end: '<button>Account</button>' },
    })

    expect(wrapper.get('.wx-header__end').text()).toBe('Account')
    // The group comes last, so what was in the header keeps its place.
    expect(wrapper.element.lastElementChild?.className).toContain('wx-header__end')
  })
})
