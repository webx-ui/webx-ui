import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import WxCountdown from './Countdown.vue'
import { formatCountdown, toTimestamp } from './format'

describe('formatCountdown', () => {
  it('fills only the tokens the pattern contains', () => {
    const span = 2 * 3600_000 + 5 * 60_000 + 9 * 1000

    expect(formatCountdown(span)).toBe('02:05:09')
    // No HH in the pattern, so the hours stay in the minutes rather than vanishing.
    expect(formatCountdown(span, 'mm:ss')).toBe('125:09')
  })

  it('counts days and milliseconds when asked', () => {
    const span = 19 * 86_400_000 + 15 * 3600_000 + 10 * 60_000 + 20 * 1000 + 45

    expect(formatCountdown(span, 'DD HH:mm:ss')).toBe('19 15:10:20')
    expect(formatCountdown(span, 'ss.SSS')).toMatch(/\.045$/)
  })

  it('never goes negative', () => {
    expect(formatCountdown(-5000)).toBe('00:00:00')
  })
})

describe('toTimestamp', () => {
  it('takes a number, a Date or a parsable string', () => {
    const date = new Date('2026-10-01T00:00:00Z')

    expect(toTimestamp(1_700_000_000_000)).toBe(1_700_000_000_000)
    expect(toTimestamp(date)).toBe(date.getTime())
    expect(toTimestamp('2026-10-01T00:00:00Z')).toBe(date.getTime())
    expect(toTimestamp('not a date')).toBe(0)
  })
})

describe('WxCountdown', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-03-12T10:00:00Z'))
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('shows the time left and ticks it down', async () => {
    const wrapper = mount(WxCountdown, { props: { value: Date.now() + 10_000 } })

    expect(wrapper.get('.wx-statistic__number').text()).toBe('00:00:10')

    vi.advanceTimersByTime(3000)
    await wrapper.vm.$nextTick()

    expect(wrapper.get('.wx-statistic__number').text()).toBe('00:00:07')
    expect(wrapper.emitted('change')?.at(-1)).toEqual([7000])
  })

  it('finishes once, at zero', async () => {
    const wrapper = mount(WxCountdown, { props: { value: Date.now() + 2000 } })

    vi.advanceTimersByTime(5000)
    await wrapper.vm.$nextTick()

    expect(wrapper.get('.wx-statistic__number').text()).toBe('00:00:00')
    expect(wrapper.emitted('finish')).toHaveLength(1)
  })

  it('restarts when the target moves', async () => {
    const wrapper = mount(WxCountdown, { props: { value: Date.now() + 5000 } })

    vi.advanceTimersByTime(2000)
    await wrapper.setProps({ value: Date.now() + 60_000 })

    expect(wrapper.get('.wx-statistic__number').text()).toBe('00:01:00')
  })

  it('stops its timer when unmounted', () => {
    const wrapper = mount(WxCountdown, { props: { value: Date.now() + 60_000 } })
    expect(vi.getTimerCount()).toBe(1)

    wrapper.unmount()
    expect(vi.getTimerCount()).toBe(0)
  })

  it('carries the statistic props through', () => {
    const wrapper = mount(WxCountdown, {
      props: { value: Date.now() + 1000, title: 'Remaining VIP time', size: 'lg' },
    })

    expect(wrapper.get('.wx-statistic__title').text()).toBe('Remaining VIP time')
    expect(wrapper.get('.wx-statistic').classes()).toContain('wx-statistic--lg')
  })
})
