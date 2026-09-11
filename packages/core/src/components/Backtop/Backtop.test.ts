import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import WxBacktop from './Backtop.vue'

describe('WxBacktop', () => {
  it('stays away until the page has moved', () => {
    expect(mount(WxBacktop).find('.wx-backtop').exists()).toBe(false)
  })

  it('appears once the scroll is far enough down', async () => {
    const wrapper = mount(WxBacktop, { props: { visibilityHeight: 100 } })

    Object.defineProperty(window, 'scrollY', { value: 400, configurable: true })
    window.dispatchEvent(new Event('scroll'))
    await nextTick()

    expect(wrapper.find('.wx-backtop').exists()).toBe(true)
  })

  it('watches the element it was given, not the window', async () => {
    const column = document.createElement('div')
    column.id = 'main'
    document.body.append(column)
    const scrollTo = vi.fn()
    column.scrollTo = scrollTo
    Object.defineProperty(column, 'scrollTop', { value: 500, configurable: true })

    const wrapper = mount(WxBacktop, { props: { target: '#main', visibilityHeight: 100 } })
    column.dispatchEvent(new Event('scroll'))
    await nextTick()

    await wrapper.get('.wx-backtop').trigger('click')

    expect(scrollTo).toHaveBeenCalledWith({ top: 0, behavior: 'smooth' })
    column.remove()
  })

  it('jumps rather than glides when asked', async () => {
    const scrollTo = vi.fn()
    window.scrollTo = scrollTo
    Object.defineProperty(window, 'scrollY', { value: 400, configurable: true })

    const wrapper = mount(WxBacktop, { props: { smooth: false } })
    window.dispatchEvent(new Event('scroll'))
    await nextTick()

    await wrapper.get('.wx-backtop').trigger('click')

    expect(scrollTo).toHaveBeenCalledWith({ top: 0, behavior: 'auto' })
  })
})
