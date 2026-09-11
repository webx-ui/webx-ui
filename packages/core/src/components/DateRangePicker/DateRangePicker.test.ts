import { afterEach, describe, expect, it } from 'vitest'
import { enableAutoUnmount, mount } from '@vue/test-utils'
import { VueDatePicker } from '@vuepic/vue-datepicker'
import WxDateRangePicker from './DateRangePicker.vue'

/* The calendar is teleported; unmounting between tests takes it with it. */
enableAutoUnmount(afterEach)

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
