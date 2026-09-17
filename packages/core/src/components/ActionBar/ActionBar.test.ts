import { describe, expect, it } from 'vitest'
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
})
