import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxBreadcrumb from './Breadcrumb.vue'
import WxBreadcrumbItem from '../BreadcrumbItem/BreadcrumbItem.vue'

function mountTrail(props: Record<string, unknown> = {}) {
  return mount(WxBreadcrumb, {
    props,
    slots: {
      default: `
        <wx-breadcrumb-item href="/">Dashboard</wx-breadcrumb-item>
        <wx-breadcrumb-item href="/posts">Posts</wx-breadcrumb-item>
        <wx-breadcrumb-item>Edit</wx-breadcrumb-item>
      `,
    },
    global: { components: { WxBreadcrumbItem } },
  })
}

describe('WxBreadcrumb', () => {
  it('renders a labelled navigation landmark around an ordered list', () => {
    const wrapper = mountTrail()

    expect(wrapper.element.tagName).toBe('NAV')
    expect(wrapper.attributes('aria-label')).toBe('Breadcrumb')
    expect(wrapper.get('.wx-breadcrumb__list').element.tagName).toBe('OL')
    expect(wrapper.findAll('.wx-breadcrumb-item')).toHaveLength(3)
  })

  it('marks only the crumb that links nowhere as the current page', () => {
    const wrapper = mountTrail()
    const items = wrapper.findAll('.wx-breadcrumb-item')

    expect(items[0].get('a').attributes('href')).toBe('/')
    expect(items[0].attributes('class')).not.toContain('is-current')
    expect(items[2].get('.wx-breadcrumb-item__link').attributes('aria-current')).toBe('page')
    expect(items[2].get('.wx-breadcrumb-item__link').element.tagName).toBe('SPAN')
  })

  it('hands the separator down to the items', () => {
    const wrapper = mountTrail({ separator: '›' })

    expect(wrapper.get('.wx-breadcrumb-item__separator').text()).toBe('›')
  })

  it('draws an icon separator when one is given', () => {
    const wrapper = mountTrail({ separatorIcon: 'chevron-right' })
    const separator = wrapper.get('.wx-breadcrumb-item__separator')

    expect(separator.findComponent({ name: 'WxIcon' }).props('name')).toBe('chevron-right')
    expect(separator.text()).toBe('')
  })

  it('sizes the trail', () => {
    expect(mountTrail({ size: 'sm' }).classes()).toContain('wx-breadcrumb--sm')
  })
})

describe('WxBreadcrumbItem', () => {
  it('can be told it is the current page while still linking', () => {
    const wrapper = mount(WxBreadcrumbItem, {
      props: { href: '/posts', current: true },
      slots: { default: 'Posts' },
    })

    expect(wrapper.classes()).toContain('is-current')
    expect(wrapper.get('a').attributes('aria-current')).toBe('page')
  })

  it('adds a safe rel to a crumb that opens a new tab', () => {
    const wrapper = mount(WxBreadcrumbItem, { props: { href: '/docs', target: '_blank' } })

    expect(wrapper.get('a').attributes('rel')).toBe('noopener noreferrer')
  })

  it('renders through another component and still emits clicks', async () => {
    const wrapper = mount(WxBreadcrumbItem, {
      props: { as: 'button', icon: 'home' },
      slots: { default: 'Home' },
    })

    expect(wrapper.get('.wx-breadcrumb-item__link').element.tagName).toBe('BUTTON')
    expect(wrapper.findComponent({ name: 'WxIcon' }).props('name')).toBe('home')

    await wrapper.get('button').trigger('click')
    expect(wrapper.emitted('click')).toHaveLength(1)
  })
})
