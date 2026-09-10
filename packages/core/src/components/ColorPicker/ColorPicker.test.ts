import { afterEach, describe, expect, it } from 'vitest'
import { enableAutoUnmount, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import WxColorPicker from './ColorPicker.vue'
import { VueDatePicker } from '@vuepic/vue-datepicker'
import WxDateRangePicker from '../DateRangePicker/DateRangePicker.vue'

// Teleported panels outlive their wrapper otherwise, and leak into the next test.
enableAutoUnmount(afterEach)

function mountPicker(props: Record<string, unknown> = {}) {
  return mount(WxColorPicker, { props, attachTo: document.body })
}

describe('WxColorPicker', () => {
  it('shows the colour in the swatch', () => {
    const wrapper = mountPicker({ modelValue: '#427edd' })

    expect(wrapper.get('.wx-color-picker__swatch').attributes('style')).toContain(
      'rgb(66, 126, 221)',
    )
    expect((wrapper.get('input').element as HTMLInputElement).value).toBe('#427edd')
  })

  it('marks an empty field rather than painting it black', () => {
    const wrapper = mountPicker({ modelValue: null })

    expect(wrapper.get('.wx-color-picker__swatch').classes()).toContain('is-empty')
  })

  it('takes a complete hex as it is typed', async () => {
    const wrapper = mountPicker({ modelValue: null })

    await wrapper.get('input').setValue('#21C36D')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['#21c36d'])
  })

  it('holds back an incomplete hex instead of wiping the value', async () => {
    const wrapper = mountPicker({ modelValue: '#427edd' })

    await wrapper.get('input').setValue('#42')

    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('adds a missing hash on blur', async () => {
    const wrapper = mountPicker({ modelValue: null })

    await wrapper.get('input').setValue('21c36d')
    await wrapper.get('input').trigger('blur')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['#21c36d'])
  })

  it('puts the last colour back when the text cannot be read', async () => {
    const wrapper = mountPicker({ modelValue: '#427edd' })

    await wrapper.get('input').setValue('not a colour')
    await wrapper.get('input').trigger('blur')

    expect((wrapper.get('input').element as HTMLInputElement).value).toBe('#427edd')
  })

  it('empties the model when the field is cleared', async () => {
    const wrapper = mountPicker({ modelValue: '#427edd' })

    await wrapper.get('input').setValue('')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([null])
  })

  it('clears through the button', async () => {
    const wrapper = mountPicker({ modelValue: '#427edd', clearable: true })

    await wrapper.get('.wx-color-picker__clear').trigger('click')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([null])
  })

  it('offers the presets once the panel is open', async () => {
    const wrapper = mountPicker({ modelValue: null, presets: ['#427edd', '#21c36d'] })

    await wrapper.get('.wx-color-picker__field').trigger('click')
    await nextTick()

    const presets = [...document.querySelectorAll('.wx-color-picker__preset')] as HTMLElement[]
    expect(presets).toHaveLength(2)

    presets[1].click()
    await nextTick()
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['#21c36d'])
  })

  it('keeps the hue when the colour is dragged into grey and back', async () => {
    const wrapper = mountPicker({ modelValue: '#427edd' })
    await wrapper.get('.wx-color-picker__field').trigger('click')
    await nextTick()

    const area = wrapper.findComponent({ name: 'ColorAreaRoot' })

    // Straight through the desaturated corner, where a hex has no hue to parse back.
    area.vm.$emit('update:color', { space: 'hsb', h: 219, s: 4, b: 60, alpha: 1 })
    await nextTick()
    area.vm.$emit('update:color', { space: 'hsb', h: 219, s: 80, b: 90, alpha: 1 })
    await nextTick()

    const hue = wrapper.findComponent({ name: 'ColorSliderRoot' }).props('modelValue') as {
      h: number
    }
    expect(hue.h).toBe(219)
  })

  it('draws the same square before and after the first drag', async () => {
    const wrapper = mountPicker({ modelValue: '#427edd' })
    await wrapper.get('.wx-color-picker__field').trigger('click')
    await nextTick()

    const surface = () =>
      document.querySelector('.wx-color-picker__area-surface')?.getAttribute('style') ?? ''

    // Black at the bottom, white at the left: the HSB square, not the RGB one flipped on its head.
    expect(surface()).toContain('linear-gradient(to top, rgb(0, 0, 0), transparent)')

    wrapper
      .findComponent({ name: 'ColorAreaRoot' })
      .vm.$emit('update:color', { space: 'hsb', h: 219, s: 70, b: 87, alpha: 1 })
    await nextTick()

    // The first drag used to be what fixed the orientation; it now changes nothing.
    expect(surface()).toContain('linear-gradient(to top, rgb(0, 0, 0), transparent)')
  })

  it('parses a preset back into the picker rather than only the field', async () => {
    const wrapper = mountPicker({ modelValue: null, presets: ['#21c36d'] })
    await wrapper.get('.wx-color-picker__field').trigger('click')
    await nextTick()

    const preset = document.querySelector('.wx-color-picker__preset') as HTMLElement
    preset.click()
    await nextTick()

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['#21c36d'])
    expect(wrapper.findComponent({ name: 'ColorAreaRoot' }).props('modelValue')).not.toBe('#427edd')
  })

  it('does not open while disabled', async () => {
    const wrapper = mountPicker({ modelValue: '#427edd', disabled: true })

    await wrapper.get('.wx-color-picker__field').trigger('click')

    expect(wrapper.get('.wx-color-picker').classes()).toContain('is-disabled')
    expect(document.querySelector('.wx-color-picker__panel')).toBeNull()
  })
})

describe('WxDateRangePicker', () => {
  it('stores a pair in the format a Laravel date column expects', () => {
    const wrapper = mount(WxDateRangePicker, {
      props: { modelValue: ['2026-03-02', '2026-04-08'] },
    })
    const picker = wrapper.findComponent(VueDatePicker)

    expect(picker.props('range')).toBe(true)
    expect(picker.props('modelType')).toBe('yyyy-MM-dd')
    expect(picker.props('modelValue')).toEqual(['2026-03-02', '2026-04-08'])
  })

  it('shows two months at once so a period across a boundary takes one pass', () => {
    const wrapper = mount(WxDateRangePicker)

    expect(wrapper.findComponent(VueDatePicker).props('multiCalendars')).toBe(2)
  })

  it('clears to null', async () => {
    const wrapper = mount(WxDateRangePicker, {
      props: { modelValue: ['2026-03-02', '2026-04-08'] },
    })

    wrapper.findComponent(VueDatePicker).vm.$emit('cleared')
    await wrapper.vm.$nextTick()

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([null])
    expect(wrapper.emitted('clear')).toHaveLength(1)
  })
})
