import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxSkeleton from './Skeleton.vue'

describe('WxSkeleton', () => {
  it('stands in for as many lines as it was asked for', () => {
    const wrapper = mount(WxSkeleton, { props: { rows: 4 } })

    expect(wrapper.findAll('.wx-skeleton-item--text')).toHaveLength(4)
  })

  it('shows the real thing once it has arrived', () => {
    const wrapper = mount(WxSkeleton, {
      props: { loading: false },
      slots: { default: '<p class="real">Ada</p>' },
    })

    expect(wrapper.find('.wx-skeleton').exists()).toBe(false)
    expect(wrapper.get('.real').text()).toBe('Ada')
  })

  it('says it is busy', () => {
    expect(mount(WxSkeleton).get('.wx-skeleton').attributes('aria-busy')).toBe('true')
  })

  it('takes a shape of its own instead of the rows', () => {
    const wrapper = mount(WxSkeleton, {
      slots: { template: '<div class="custom">…</div>' },
    })

    expect(wrapper.find('.custom').exists()).toBe(true)
    expect(wrapper.find('.wx-skeleton-item--text').exists()).toBe(false)
  })

  it('adds a circle and a heading when asked', () => {
    const wrapper = mount(WxSkeleton, { props: { avatar: true, title: true } })

    expect(wrapper.find('.wx-skeleton-item--circle').exists()).toBe(true)
    expect(wrapper.find('.wx-skeleton-item--title').exists()).toBe(true)
  })
})
