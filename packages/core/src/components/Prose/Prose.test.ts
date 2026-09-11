import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxProse from './Prose.vue'

describe('WxProse', () => {
  it('renders the html prop as markup', () => {
    const wrapper = mount(WxProse, {
      props: { html: '<h2>Title</h2><p>Body <a href="/x">link</a></p>' },
    })

    expect(wrapper.get('h2').text()).toBe('Title')
    expect(wrapper.get('a').attributes('href')).toBe('/x')
  })

  it('falls back to the slot when no html is given', () => {
    const wrapper = mount(WxProse, { slots: { default: '<p>Written in the template</p>' } })

    expect(wrapper.get('p').text()).toBe('Written in the template')
  })

  it('prefers an empty html prop over the slot, so clearing a field clears the block', () => {
    const wrapper = mount(WxProse, { props: { html: '' }, slots: { default: '<p>Fallback</p>' } })

    expect(wrapper.text()).toBe('')
  })

  it('takes a size and an element', () => {
    const wrapper = mount(WxProse, { props: { size: 'sm', as: 'article' } })

    expect(wrapper.element.tagName).toBe('ARTICLE')
    expect(wrapper.classes()).toContain('wx-prose--sm')
  })
})
