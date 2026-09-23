import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import WxActionBar from './ActionBar.vue'

describe('WxActionBar', () => {
  it('sticks to the bottom of the window unless told not to', () => {
    const wrapper = mount(WxActionBar, { slots: { default: 'Save' } })

    expect(wrapper.classes()).toContain('wx-action-bar--sticky')
    expect(wrapper.classes()).toContain('wx-action-bar--bordered')
    expect(wrapper.find('.wx-action-bar__actions').text()).toBe('Save')
  })

  it('gives up both when the screen wants the row plain', () => {
    const wrapper = mount(WxActionBar, { props: { sticky: false, bordered: false } })

    expect(wrapper.classes()).not.toContain('wx-action-bar--sticky')
    expect(wrapper.classes()).not.toContain('wx-action-bar--bordered')
  })

  it('puts the state of the work on the left', () => {
    const wrapper = mount(WxActionBar, {
      slots: { state: 'Draft 3', default: 'Publish' },
    })

    expect(wrapper.find('.wx-action-bar__state').text()).toBe('Draft 3')
    expect(wrapper.find('.wx-action-bar__actions').text()).toBe('Publish')
  })

  describe('the room it takes', () => {
    afterEach(() => vi.unstubAllGlobals())

    function stubObserver() {
      vi.stubGlobal(
        'ResizeObserver',
        class {
          observe() {}
          disconnect() {}
        },
      )
    }

    it('tells its parent how much of the window it takes while it sticks', () => {
      stubObserver()
      const wrapper = mount(WxActionBar, { attachTo: document.body })
      const parent = wrapper.element.parentElement as HTMLElement

      expect(parent.style.getPropertyValue('--wx-action-bar-room')).toMatch(/px$/)

      wrapper.unmount()
      expect(parent.style.getPropertyValue('--wx-action-bar-room')).toBe('')
    })

    it('takes none when it stays in the flow', () => {
      stubObserver()
      const wrapper = mount(WxActionBar, { props: { sticky: false }, attachTo: document.body })
      const parent = wrapper.element.parentElement as HTMLElement

      expect(parent.style.getPropertyValue('--wx-action-bar-room')).toBe('')
      wrapper.unmount()
    })
  })
})
