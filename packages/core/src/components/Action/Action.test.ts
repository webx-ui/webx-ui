import { describe, expect, it } from 'vitest'
import { mount, type VueWrapper } from '@vue/test-utils'
import { markRaw } from 'vue'
import WxAction from './Action.vue'

/*
 * The control, not the root: the action carries its own tooltip, so what Vue mounts is a
 * fragment and `wrapper.element` is its anchor rather than the button.
 */
const control = (wrapper: VueWrapper) => wrapper.get('button, a')

describe('WxAction', () => {
  it('is a button with the icon, the colour and the name of its type', () => {
    const wrapper = mount(WxAction, { props: { type: 'remove' } })

    expect(control(wrapper).attributes('type')).toBe('button')
    expect(control(wrapper).classes()).toContain('wx-action--danger')
    expect(control(wrapper).attributes('aria-label')).toBe('Delete')
    expect(wrapper.find('svg.wx-icon').exists()).toBe(true)
  })

  it('takes the title as its accessible name, and the label over both', () => {
    expect(
      control(mount(WxAction, { props: { type: 'edit', title: 'Редагувати' } })).attributes(
        'aria-label',
      ),
    ).toBe('Редагувати')

    const wrapper = mount(WxAction, {
      props: { type: 'edit', title: 'Редагувати', label: 'Edit the page' },
    })
    expect(control(wrapper).attributes('aria-label')).toBe('Edit the page')
    /* The tip is ours now, so the browser's own tooltip is gone from the markup. */
    expect(control(wrapper).attributes('title')).toBeUndefined()
  })

  it('lets the icon and the tone be overridden', () => {
    const wrapper = mount(WxAction, { props: { type: 'add', icon: 'star', tone: 'warning' } })

    expect(control(wrapper).classes()).toContain('wx-action--warning')
    expect(control(wrapper).classes()).not.toContain('wx-action--primary')
  })

  it('renders a link when it has an href', () => {
    const wrapper = mount(WxAction, { props: { type: 'goto', href: '/pages/12' } })

    expect(control(wrapper).element.tagName).toBe('A')
    expect(control(wrapper).attributes('href')).toBe('/pages/12')
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

    /* `aria-disabled`, not the attribute: a disabled button dispatches no pointer events,
       and its tooltip would never come. */
    expect(control(wrapper).attributes('aria-disabled')).toBe('true')
    await control(wrapper).trigger('click')
    expect(wrapper.emitted('click')).toBeUndefined()
  })

  it('emits click when it is live', async () => {
    const wrapper = mount(WxAction, { props: { type: 'edit' } })

    await control(wrapper).trigger('click')
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
    expect(control(mount(WxAction)).classes()).toContain('wx-action--md')
    expect(control(mount(WxAction, { props: { size: 'sm' } })).classes()).toContain('wx-action--sm')
  })
})
