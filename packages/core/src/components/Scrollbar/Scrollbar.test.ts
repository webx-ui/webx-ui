import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import WxScrollbar from './Scrollbar.vue'

describe('WxScrollbar', () => {
  it('scrolls vertically and fades the bar in by default', () => {
    const wrapper = mount(WxScrollbar, { slots: { default: 'Long content' } })

    expect(wrapper.classes()).toContain('wx-scrollbar--y')
    expect(wrapper.classes()).not.toContain('wx-scrollbar--always')
    expect(wrapper.text()).toBe('Long content')
  })

  it('takes a height and a cap as lengths', () => {
    const wrapper = mount(WxScrollbar, { props: { height: 240, maxHeight: '50vh' } })

    expect(wrapper.attributes('style')).toContain('--wx-scrollbar-height: 240px')
    expect(wrapper.attributes('style')).toContain('--wx-scrollbar-max-height: 50vh')
  })

  it('applies axis, size and the always modifier', () => {
    const wrapper = mount(WxScrollbar, { props: { axis: 'both', size: 'sm', always: true } })

    expect(wrapper.classes()).toContain('wx-scrollbar--both')
    expect(wrapper.classes()).toContain('wx-scrollbar--sm')
    expect(wrapper.classes()).toContain('wx-scrollbar--always')
  })

  it('emits the native scroll event', async () => {
    const wrapper = mount(WxScrollbar)

    await wrapper.trigger('scroll')

    expect(wrapper.emitted('scroll')).toHaveLength(1)
  })

  it('exposes the scrolling element and the helpers', () => {
    const wrapper = mount(WxScrollbar)
    const scrollTo = vi.fn()
    const el = wrapper.vm.el as HTMLElement

    expect(el).toBe(wrapper.element)

    el.scrollTo = scrollTo
    Object.defineProperty(el, 'scrollHeight', { value: 900, configurable: true })

    wrapper.vm.scrollToBottom('smooth')
    expect(scrollTo).toHaveBeenCalledWith({ top: 900, behavior: 'smooth' })

    wrapper.vm.scrollToTop()
    expect(scrollTo).toHaveBeenCalledWith({ top: 0, behavior: 'auto' })
  })
})
