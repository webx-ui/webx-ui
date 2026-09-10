import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxCard from './Card.vue'

describe('WxCard', () => {
  it('renders only the body when nothing else is provided', () => {
    const wrapper = mount(WxCard, { slots: { default: 'Body' } })

    expect(wrapper.get('.wx-card__body').text()).toBe('Body')
    expect(wrapper.find('.wx-card__header').exists()).toBe(false)
    expect(wrapper.find('.wx-card__footer').exists()).toBe(false)
  })

  it('renders the title prop in the header', () => {
    const wrapper = mount(WxCard, { props: { title: 'Settings' } })

    expect(wrapper.get('.wx-card__title').text()).toBe('Settings')
  })

  it('lets the header slot override the title prop', () => {
    const wrapper = mount(WxCard, {
      props: { title: 'Settings' },
      slots: { header: '<b>Custom</b>' },
    })

    expect(wrapper.get('.wx-card__title').html()).toContain('<b>Custom</b>')
    expect(wrapper.get('.wx-card__title').text()).toBe('Custom')
  })

  it('renders extra and footer slots', () => {
    const wrapper = mount(WxCard, {
      slots: { extra: 'More', footer: 'Footer' },
    })

    expect(wrapper.get('.wx-card__extra').text()).toBe('More')
    expect(wrapper.get('.wx-card__footer').text()).toBe('Footer')
    expect(wrapper.find('.wx-card__header').exists()).toBe(true)
  })

  it('applies shadow, padding and bordered modifiers', () => {
    const wrapper = mount(WxCard, {
      props: { shadow: 'hover', padding: 'lg', bordered: true },
    })

    expect(wrapper.classes()).toContain('wx-card--shadow-hover')
    expect(wrapper.classes()).toContain('wx-card--padding-lg')
    expect(wrapper.classes()).toContain('wx-card--bordered')
  })
  it('renders the body directly when there is no sidebar', () => {
    const wrapper = mount(WxCard, { slots: { default: 'Body' } })

    expect(wrapper.find('.wx-card__layout').exists()).toBe(false)
    expect(wrapper.classes()).not.toContain('wx-card--with-sidebar')
  })

  it('splits the body into sidebar and content when the sidebar slot is used', () => {
    const wrapper = mount(WxCard, {
      slots: { sidebar: 'Navigation', default: 'Body' },
    })

    expect(wrapper.classes()).toContain('wx-card--with-sidebar')
    expect(wrapper.get('.wx-card__sidebar').text()).toBe('Navigation')
    expect(wrapper.get('.wx-card__content').text()).toBe('Body')
    expect(wrapper.get('.wx-card__sidebar').element.tagName).toBe('ASIDE')
  })

  it('keeps the sidebar layout inside the padded body', () => {
    const wrapper = mount(WxCard, { slots: { sidebar: 'Nav', default: 'Body' } })

    expect(wrapper.find('.wx-card__body > .wx-card__layout').exists()).toBe(true)
  })
})
