import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxEmpty from './Empty.vue'

describe('WxEmpty', () => {
  it('shows a title and a description', () => {
    const wrapper = mount(WxEmpty, {
      props: { title: 'No orders yet', description: 'They will appear here as they come in.' },
    })

    expect(wrapper.get('.wx-empty__title').text()).toBe('No orders yet')
    expect(wrapper.get('.wx-empty__description').text()).toBe(
      'They will appear here as they come in.',
    )
  })

  it('leaves out what it was not given', () => {
    const wrapper = mount(WxEmpty)

    expect(wrapper.find('.wx-empty__title').exists()).toBe(false)
    expect(wrapper.find('.wx-empty__description').exists()).toBe(false)
    expect(wrapper.find('.wx-empty__actions').exists()).toBe(false)
  })

  it('draws the glyph unless asked not to', () => {
    expect(mount(WxEmpty).find('.wx-empty__icon').exists()).toBe(true)
    expect(
      mount(WxEmpty, { props: { plain: true } })
        .find('.wx-empty__icon')
        .exists(),
    ).toBe(false)
  })

  it('keeps the glyph out of the reading order', () => {
    expect(mount(WxEmpty).get('.wx-empty__icon').attributes('aria-hidden')).toBe('true')
  })

  it('takes the description from the default slot', () => {
    const wrapper = mount(WxEmpty, {
      props: { description: 'Ignored' },
      slots: { default: 'Nothing matches <b>paid</b>' },
    })

    expect(wrapper.get('.wx-empty__description').text()).toContain('Nothing matches')
  })

  it('renders what to do about it', () => {
    const wrapper = mount(WxEmpty, { slots: { actions: '<button>Clear filters</button>' } })

    expect(wrapper.get('.wx-empty__actions button').text()).toBe('Clear filters')
  })

  it('takes a size', () => {
    expect(mount(WxEmpty, { props: { size: 'lg' } }).classes()).toContain('wx-empty--lg')
  })
})
