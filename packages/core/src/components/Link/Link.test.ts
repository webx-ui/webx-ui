import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { markRaw } from 'vue'
import WxLink from './Link.vue'

describe('WxLink', () => {
  it('renders an anchor with the href', () => {
    const wrapper = mount(WxLink, { props: { href: '/pages' }, slots: { default: 'Pages' } })

    expect(wrapper.element.tagName).toBe('A')
    expect(wrapper.attributes('href')).toBe('/pages')
    expect(wrapper.text()).toBe('Pages')
    expect(wrapper.classes()).toContain('wx-link--underline-hover')
  })

  it('opens an external link safely and marks it with an icon', () => {
    const wrapper = mount(WxLink, { props: { href: 'https://example.com', external: true } })

    expect(wrapper.attributes('target')).toBe('_blank')
    expect(wrapper.attributes('rel')).toBe('noopener noreferrer')
    expect(wrapper.find('.wx-link__external').exists()).toBe(true)
  })

  it('keeps an explicit rel and target', () => {
    const wrapper = mount(WxLink, {
      props: { href: 'https://example.com', external: true, target: '_self', rel: 'nofollow' },
    })

    expect(wrapper.attributes('target')).toBe('_self')
    expect(wrapper.attributes('rel')).toBe('nofollow')
  })

  it('drops the href and swallows clicks when disabled', async () => {
    const wrapper = mount(WxLink, { props: { href: '/pages', disabled: true } })

    expect(wrapper.attributes('href')).toBeUndefined()
    expect(wrapper.attributes('aria-disabled')).toBe('true')
    expect(wrapper.attributes('tabindex')).toBe('-1')

    await wrapper.trigger('click')
    expect(wrapper.emitted('click')).toBeUndefined()
  })

  it('emits click when it is live', async () => {
    const wrapper = mount(WxLink, { props: { href: '#' } })

    await wrapper.trigger('click')
    expect(wrapper.emitted('click')).toHaveLength(1)
  })

  it('renders through another component', () => {
    const RouterLinkStub = markRaw({
      props: ['to'],
      template: '<a class="router-link" :href="to"><slot /></a>',
    })
    const wrapper = mount(WxLink, {
      props: { as: RouterLinkStub, to: '/admin/pages' },
      slots: { default: 'Pages' },
    })

    expect(wrapper.get('a').classes()).toContain('router-link')
    expect(wrapper.get('a').attributes('href')).toBe('/admin/pages')
  })

  it('takes type, size and underline modifiers', () => {
    const wrapper = mount(WxLink, {
      props: { type: 'danger', size: 'sm', underline: 'always', weight: 'semibold' },
    })

    expect(wrapper.classes()).toEqual(
      expect.arrayContaining([
        'wx-link--danger',
        'wx-link--sm',
        'wx-link--underline-always',
        'wx-link--weight-semibold',
      ]),
    )
  })
})
