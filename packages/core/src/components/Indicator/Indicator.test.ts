import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxIndicator from './Indicator.vue'

describe('WxIndicator', () => {
  it('marks the element it wraps', () => {
    const wrapper = mount(WxIndicator, {
      props: { value: 2 },
      slots: { default: '<button>Cart</button>' },
    })

    expect(wrapper.classes()).toContain('wx-indicator--attached')
    expect(wrapper.get('button').text()).toBe('Cart')
    expect(wrapper.get('.wx-indicator__mark').text()).toBe('2')
    expect(wrapper.get('.wx-indicator__mark').classes()).toContain('wx-indicator__mark--top-right')
  })

  it('stands alone without a wrapped element', () => {
    const wrapper = mount(WxIndicator, { props: { value: 7 } })

    expect(wrapper.classes()).not.toContain('wx-indicator--attached')
    expect(wrapper.get('.wx-indicator__mark').classes()).not.toContain(
      'wx-indicator__mark--attached',
    )
  })

  it('caps the number at max', () => {
    expect(mount(WxIndicator, { props: { value: 99 } }).text()).toBe('99')
    expect(mount(WxIndicator, { props: { value: 128 } }).text()).toBe('99+')
    expect(mount(WxIndicator, { props: { value: 128, max: 999 } }).text()).toBe('128')
  })

  it('hides a zero unless show-zero is set', () => {
    expect(
      mount(WxIndicator, { props: { value: 0 } })
        .find('.wx-indicator__mark')
        .exists(),
    ).toBe(false)
    expect(
      mount(WxIndicator, { props: { value: 0, showZero: true } })
        .find('.wx-indicator__mark')
        .exists(),
    ).toBe(true)
  })

  it('hides when there is nothing to show, or when asked', () => {
    expect(mount(WxIndicator).find('.wx-indicator__mark').exists()).toBe(false)
    expect(
      mount(WxIndicator, { props: { value: 3, hidden: true } })
        .find('.wx-indicator__mark')
        .exists(),
    ).toBe(false)
  })

  it('draws a dot with no text, even at zero', () => {
    const wrapper = mount(WxIndicator, { props: { dot: true, value: 0 } })
    const mark = wrapper.get('.wx-indicator__mark')

    expect(mark.classes()).toContain('wx-indicator__mark--dot')
    expect(mark.text()).toBe('')
  })

  it('takes a pixel offset and a colour', () => {
    const wrapper = mount(WxIndicator, {
      props: { value: 1, offset: [-4, 6], type: 'primary' },
      slots: { default: '<span>icon</span>' },
    })
    const mark = wrapper.get('.wx-indicator__mark')

    expect(mark.classes()).toContain('wx-indicator__mark--primary')
    expect(mark.attributes('style')).toContain('--wx-indicator-offset-x: -4px')
    expect(mark.attributes('style')).toContain('--wx-indicator-offset-y: 6px')
  })

  it('lets the mark slot replace the text', () => {
    const wrapper = mount(WxIndicator, {
      props: { value: 5 },
      slots: { mark: '<b>new</b>' },
    })

    expect(wrapper.get('.wx-indicator__mark').html()).toContain('<b>new</b>')
  })
})
