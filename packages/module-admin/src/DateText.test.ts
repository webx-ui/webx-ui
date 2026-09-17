import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import DateText from './DateText.vue'

beforeEach(() => {
  vi.useFakeTimers({ now: new Date(2026, 8, 17, 9, 30) })
})

afterEach(() => {
  vi.useRealTimers()
})

describe('WxDate', () => {
  it('shows the short line and carries the moment itself in the markup', () => {
    const at = new Date(2026, 8, 17, 8, 10)
    const wrapper = mount(DateText, { props: { value: at } })
    const time = wrapper.get('time')

    expect(time.text()).toBe('today at 08:10')
    // What a machine reads — a screen reader, and anything that would otherwise be tempted to
    // parse the words back into a date.
    expect(time.attributes('datetime')).toBe(at.toISOString())
  })

  it('says the word for a date that never happened, and has no moment to carry', () => {
    const wrapper = mount(DateText, { props: { value: null } })

    expect(wrapper.text()).toBe('never')
    expect(wrapper.find('time').exists()).toBe(false)
  })
})
