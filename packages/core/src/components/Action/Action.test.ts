import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { markRaw } from 'vue'
import WxAction from './Action.vue'

describe('WxAction', () => {
  it('is a button with the icon, the colour and the name of its type', () => {
    const wrapper = mount(WxAction, { props: { type: 'remove' } })

    expect(wrapper.element.tagName).toBe('BUTTON')
    expect(wrapper.attributes('type')).toBe('button')
    expect(wrapper.classes()).toContain('wx-action--danger')
    expect(wrapper.attributes('aria-label')).toBe('Delete')
    expect(wrapper.find('svg.wx-icon').exists()).toBe(true)
  })

  it('takes the title as its accessible name, and the label over both', () => {
    expect(
      mount(WxAction, { props: { type: 'edit', title: 'Редагувати' } }).attributes('aria-label'),
    ).toBe('Редагувати')

    const wrapper = mount(WxAction, {
      props: { type: 'edit', title: 'Редагувати', label: 'Edit the page' },
    })
    expect(wrapper.attributes('aria-label')).toBe('Edit the page')
    expect(wrapper.attributes('title')).toBe('Редагувати')
  })

  it('lets the icon and the tone be overridden', () => {
    const wrapper = mount(WxAction, { props: { type: 'add', icon: 'star', tone: 'warning' } })

    expect(wrapper.classes()).toContain('wx-action--warning')
    expect(wrapper.classes()).not.toContain('wx-action--primary')
  })

  it('renders a link when it has an href', () => {
    const wrapper = mount(WxAction, { props: { type: 'goto', href: '/pages/12' } })

    expect(wrapper.element.tagName).toBe('A')
    expect(wrapper.attributes('href')).toBe('/pages/12')
  })

  it('renders through another component', () => {
    const RouterLinkStub = markRaw({
      props: ['to'],
      template: '<a class="router-link" :href="to"><slot /></a>',
    })
    const wrapper = mount(WxAction, {
      props: { type: 'edit', as: RouterLinkStub, to: '/admin/pages/12' },
    })

    expect(wrapper.get('a').classes()).toContain('router-link')
    expect(wrapper.get('a').attributes('href')).toBe('/admin/pages/12')
  })

  it('swallows clicks while disabled', async () => {
    const wrapper = mount(WxAction, { props: { type: 'remove', disabled: true } })

    expect(wrapper.attributes('disabled')).toBeDefined()
    await wrapper.trigger('click')
    expect(wrapper.emitted('click')).toBeUndefined()
  })

  it('emits click when it is live', async () => {
    const wrapper = mount(WxAction, { props: { type: 'edit' } })

    await wrapper.trigger('click')
    expect(wrapper.emitted('click')).toHaveLength(1)
  })

  it('keeps its square but draws nothing when hidden', () => {
    const wrapper = mount(WxAction, { props: { type: 'remove', hidden: true } })

    expect(wrapper.element.tagName).toBe('SPAN')
    expect(wrapper.classes()).toContain('wx-action--placeholder')
    expect(wrapper.attributes('aria-hidden')).toBe('true')
    expect(wrapper.find('svg').exists()).toBe(false)
  })

  it('replaces the icon with the default slot', () => {
    const wrapper = mount(WxAction, {
      props: { type: 'more' },
      slots: { default: '<span class="dots">…</span>' },
    })

    expect(wrapper.find('.dots').exists()).toBe(true)
    expect(wrapper.find('svg.wx-icon').exists()).toBe(false)
  })

  it('sizes itself, md by default', () => {
    expect(mount(WxAction).classes()).toContain('wx-action--md')
    expect(mount(WxAction, { props: { size: 'sm' } }).classes()).toContain('wx-action--sm')
  })
})
