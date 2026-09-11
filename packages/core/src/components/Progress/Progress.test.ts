import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxProgress from './Progress.vue'

describe('WxProgress', () => {
  it('fills to the share that is done', () => {
    const wrapper = mount(WxProgress, { props: { value: 40 } })

    expect(wrapper.get('.wx-progress__fill').attributes('style')).toContain('width: 40%')
  })

  it('measures against a max that is not a hundred', () => {
    const wrapper = mount(WxProgress, { props: { value: 3, max: 8, showValue: true } })

    expect(wrapper.get('.wx-progress__fill').attributes('style')).toContain('width: 38%')
    expect(wrapper.get('.wx-progress__value').text()).toBe('38%')
  })

  it('refuses to go past either end', () => {
    expect(
      mount(WxProgress, { props: { value: 140 } })
        .get('.wx-progress__fill')
        .attributes('style'),
    ).toContain('width: 100%')
    expect(
      mount(WxProgress, { props: { value: -20 } })
        .get('.wx-progress__fill')
        .attributes('style'),
    ).toContain('width: 0%')
  })

  it('reports itself as a progressbar with the numbers on it', () => {
    const bar = mount(WxProgress, { props: { value: 25, ariaLabel: 'Upload' } }).get(
      '[role=progressbar]',
    )

    expect(bar.attributes('aria-valuenow')).toBe('25')
    expect(bar.attributes('aria-valuemin')).toBe('0')
    expect(bar.attributes('aria-valuemax')).toBe('100')
    expect(bar.attributes('aria-label')).toBe('Upload')
  })

  it('names no number when it does not have one', () => {
    const bar = mount(WxProgress, { props: { indeterminate: true } }).get('[role=progressbar]')

    // A progressbar with no `aria-valuenow` is exactly how "busy, cannot say" reads.
    expect(bar.attributes('aria-valuenow')).toBeUndefined()
    expect(bar.attributes('aria-valuetext')).toBeUndefined()
  })

  it('takes a formatter for the figure', () => {
    const wrapper = mount(WxProgress, {
      props: {
        value: 3,
        max: 8,
        showValue: true,
        formatter: (v: number, m: number) => `${v} of ${m}`,
      },
    })

    expect(wrapper.get('.wx-progress__value').text()).toBe('3 of 8')
  })

  it('draws a ring when asked', () => {
    const wrapper = mount(WxProgress, { props: { type: 'circle', value: 50 } })

    expect(wrapper.find('.wx-progress__ring').exists()).toBe(true)
    expect(wrapper.find('.wx-progress__fill').exists()).toBe(false)
    // Half done is half the circumference left undrawn.
    const ring = wrapper.get('.wx-progress__ring-fill')
    const total = Number(ring.attributes('stroke-dasharray'))
    expect(Number(ring.attributes('stroke-dashoffset'))).toBeCloseTo(total / 2, 1)
  })

  it('shows nothing beside the bar unless asked', () => {
    expect(
      mount(WxProgress, { props: { value: 10 } })
        .find('.wx-progress__value')
        .exists(),
    ).toBe(false)
  })
})
