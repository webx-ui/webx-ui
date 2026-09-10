import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxRate from './Rate.vue'

/** The pointer lands on the right half of a star unless a test says otherwise. */
function clickStar(wrapper: ReturnType<typeof mount>, index: number, half = false) {
  const star = wrapper.findAll('.wx-rate__star')[index]
  const element = star.element as HTMLElement
  element.getBoundingClientRect = () => ({ left: 0, width: 20, top: 0, height: 20 }) as DOMRect
  return star.trigger('click', { clientX: half ? 4 : 16 })
}

describe('WxRate', () => {
  it('draws the requested number of stars', () => {
    const wrapper = mount(WxRate, { props: { max: 7 } })

    expect(wrapper.findAll('.wx-rate__star')).toHaveLength(7)
  })

  it('fills the stars up to the value', () => {
    const wrapper = mount(WxRate, { props: { modelValue: 3 } })
    const filled = wrapper.findAll('.wx-rate__star.is-full')

    expect(filled).toHaveLength(3)
  })

  it('sets the rating from a click', async () => {
    const wrapper = mount(WxRate, { props: { modelValue: 0 } })

    await clickStar(wrapper, 2)

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([3])
    expect(wrapper.emitted('change')?.at(-1)).toEqual([3])
  })

  it('clicking the current value clears it', async () => {
    const wrapper = mount(WxRate, { props: { modelValue: 3 } })

    await clickStar(wrapper, 2)

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([0])
  })

  it('keeps the value when clearable is off', async () => {
    const wrapper = mount(WxRate, { props: { modelValue: 3, clearable: false } })

    await clickStar(wrapper, 2)

    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('gives a half star for a click on the left of one', async () => {
    const wrapper = mount(WxRate, { props: { modelValue: 0, allowHalf: true } })

    await clickStar(wrapper, 2, true)

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([2.5])
  })

  it('ignores halves when they are not allowed', async () => {
    const wrapper = mount(WxRate, { props: { modelValue: 0 } })

    await clickStar(wrapper, 2, true)

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([3])
  })

  it('renders a half-filled star for a fractional value', () => {
    const wrapper = mount(WxRate, { props: { modelValue: 3.5, allowHalf: true } })

    expect(wrapper.findAll('.wx-rate__star.is-full')).toHaveLength(3)
    expect(wrapper.findAll('.wx-rate__star.is-half')).toHaveLength(1)
  })

  it('steps with the arrow keys and jumps with Home and End', async () => {
    const wrapper = mount(WxRate, { props: { modelValue: 2 } })

    await wrapper.trigger('keydown', { key: 'ArrowRight' })
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([3])

    await wrapper.setProps({ modelValue: 3 })
    await wrapper.trigger('keydown', { key: 'ArrowLeft' })
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([2])

    await wrapper.trigger('keydown', { key: 'End' })
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([5])

    await wrapper.setProps({ modelValue: 5 })
    await wrapper.trigger('keydown', { key: 'Home' })
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([0])
  })

  it('does not move past the bounds', async () => {
    const wrapper = mount(WxRate, { props: { modelValue: 5 } })
    await wrapper.trigger('keydown', { key: 'ArrowRight' })

    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('ignores clicks and keys while readonly', async () => {
    const wrapper = mount(WxRate, { props: { modelValue: 2, readonly: true } })

    await clickStar(wrapper, 4)
    await wrapper.trigger('keydown', { key: 'ArrowRight' })

    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    expect(wrapper.attributes('aria-readonly')).toBe('true')
  })

  it('exposes the rating to assistive tech', () => {
    const wrapper = mount(WxRate, { props: { modelValue: 4, max: 5 } })

    expect(wrapper.attributes('role')).toBe('slider')
    expect(wrapper.attributes('aria-valuenow')).toBe('4')
    expect(wrapper.attributes('aria-valuemax')).toBe('5')
  })
})
