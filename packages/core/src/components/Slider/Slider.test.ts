import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { SliderRoot } from 'reka-ui'
import WxSlider from './Slider.vue'

function root(wrapper: ReturnType<typeof mount>) {
  return wrapper.findComponent(SliderRoot)
}

describe('WxSlider', () => {
  it('hands a single value down as a one-element array', () => {
    const wrapper = mount(WxSlider, { props: { modelValue: 40 } })

    expect(root(wrapper).props('modelValue')).toEqual([40])
    expect(wrapper.findAll('.wx-slider__thumb')).toHaveLength(1)
  })

  it('keeps a plain number in the model, not an array', async () => {
    const wrapper = mount(WxSlider, { props: { modelValue: 40 } })

    root(wrapper).vm.$emit('update:modelValue', [55])
    await wrapper.vm.$nextTick()

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([55])
    expect(wrapper.emitted('change')?.at(-1)).toEqual([55])
  })

  it('renders two thumbs and keeps a pair in range mode', async () => {
    const wrapper = mount(WxSlider, { props: { range: true, modelValue: [20, 80] } })

    expect(wrapper.findAll('.wx-slider__thumb')).toHaveLength(2)

    root(wrapper).vm.$emit('update:modelValue', [30, 70])
    await wrapper.vm.$nextTick()

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([[30, 70]])
  })

  it('falls back to the bounds when the range model is empty', () => {
    const wrapper = mount(WxSlider, { props: { range: true, modelValue: null, min: 10, max: 90 } })

    expect(root(wrapper).props('modelValue')).toEqual([10, 90])
  })

  it('starts a single slider at the minimum when the model is empty', () => {
    const wrapper = mount(WxSlider, { props: { modelValue: null, min: 5 } })

    expect(root(wrapper).props('modelValue')).toEqual([5])
  })

  it('passes the bounds and stepping through', () => {
    const wrapper = mount(WxSlider, {
      props: { min: 0, max: 1000, step: 50, minStepsBetweenThumbs: 2 },
    })

    expect(root(wrapper).props('min')).toBe(0)
    expect(root(wrapper).props('max')).toBe(1000)
    expect(root(wrapper).props('step')).toBe(50)
    expect(root(wrapper).props('minStepsBetweenThumbs')).toBe(2)
  })

  it('shows the value, and both values in range mode', () => {
    const single = mount(WxSlider, { props: { modelValue: 40, showValue: true } })
    expect(single.get('.wx-slider__value').text()).toBe('40')

    const pair = mount(WxSlider, { props: { range: true, modelValue: [20, 80], showValue: true } })
    expect(pair.get('.wx-slider__value').text()).toBe('20 — 80')
  })

  it('places marks along the track', () => {
    const wrapper = mount(WxSlider, {
      props: { min: 0, max: 100, marks: { 0: 'Free', 50: 'Half', 100: 'Max' } },
    })
    const marks = wrapper.findAll('.wx-slider__mark')

    expect(marks.map((mark) => mark.text())).toEqual(['Free', 'Half', 'Max'])
    expect(marks[1].attributes('style')).toContain('left: 50%')
  })

  it('disables the underlying slider', () => {
    const wrapper = mount(WxSlider, { props: { modelValue: 10, disabled: true } })

    expect(root(wrapper).props('disabled')).toBe(true)
    expect(wrapper.classes()).toContain('is-disabled')
  })
})
