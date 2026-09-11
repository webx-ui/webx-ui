import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import WxLoading from './Loading.vue'

describe('WxLoading', () => {
  it('covers what is there rather than replacing it', () => {
    const wrapper = mount(WxLoading, {
      props: { loading: true },
      slots: { default: '<table class="rows">rows</table>' },
    })

    // The rows you were reading are still the rows you were reading.
    expect(wrapper.find('.rows').exists()).toBe(true)
    expect(wrapper.find('.wx-loading__veil').exists()).toBe(true)
  })

  it('has no veil when nothing is happening', () => {
    expect(mount(WxLoading).find('.wx-loading__veil').exists()).toBe(false)
  })

  it('holds what is underneath out of reach', () => {
    const wrapper = mount(WxLoading, {
      props: { loading: true },
      slots: { default: '<button>Save</button>' },
    })

    // A veil that only looks like it blocks invites the second click.
    expect(wrapper.get('.wx-loading__content').attributes('inert')).toBeDefined()
  })

  it('waits before appearing, so a quick answer shows nothing', async () => {
    vi.useFakeTimers()
    const wrapper = mount(WxLoading, { props: { loading: true, delay: 200 } })

    expect(wrapper.find('.wx-loading__veil').exists()).toBe(false)

    vi.advanceTimersByTime(250)
    await nextTick()
    expect(wrapper.find('.wx-loading__veil').exists()).toBe(true)

    vi.useRealTimers()
  })

  it('never waits to go away', async () => {
    vi.useFakeTimers()
    const wrapper = mount(WxLoading, { props: { loading: true, delay: 200 } })

    vi.advanceTimersByTime(250)
    await nextTick()

    await wrapper.setProps({ loading: false })
    expect(wrapper.find('.wx-loading__veil').exists()).toBe(false)

    vi.useRealTimers()
  })

  it('says what is being waited for', () => {
    const wrapper = mount(WxLoading, { props: { loading: true, text: 'Fetching orders' } })

    expect(wrapper.get('.wx-loading__text').text()).toBe('Fetching orders')
    expect(wrapper.get('[role=status]').attributes('aria-label')).toBe('Loading')
  })
})
