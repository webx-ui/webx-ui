import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxResult from './Result.vue'

describe('WxResult', () => {
  it('picks a glyph and a colour from the status', () => {
    expect(mount(WxResult, { props: { status: 'success' } }).classes()).toContain(
      'wx-result--success',
    )
    // The three numbers borrow a colour rather than having one of their own.
    expect(mount(WxResult, { props: { status: '404' } }).classes()).toContain('wx-result--info')
    expect(mount(WxResult, { props: { status: '500' } }).classes()).toContain('wx-result--danger')
  })

  it('shows the outcome and what it means', () => {
    const wrapper = mount(WxResult, {
      props: { status: 'success', title: 'Order placed', subtitle: 'A receipt is on its way.' },
    })

    expect(wrapper.get('.wx-result__title').text()).toBe('Order placed')
    expect(wrapper.get('.wx-result__subtitle').text()).toBe('A receipt is on its way.')
  })

  it('keeps the glyph out of the reading order', () => {
    expect(mount(WxResult).get('.wx-result__icon').attributes('aria-hidden')).toBe('true')
  })

  it('renders the way out', () => {
    const wrapper = mount(WxResult, { slots: { actions: '<button>Back to orders</button>' } })

    expect(wrapper.get('.wx-result__actions button').text()).toBe('Back to orders')
  })
})
