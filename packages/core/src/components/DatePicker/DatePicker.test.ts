import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { VueDatePicker } from '@vuepic/vue-datepicker'
import WxDatePicker from './DatePicker.vue'
import WxDateTimePicker from '../DateTimePicker/DateTimePicker.vue'
import WxTimePicker from '../TimePicker/TimePicker.vue'

/** Props the wrapper hands to the underlying picker. */
function picker(wrapper: ReturnType<typeof mount>) {
  return wrapper.findComponent(VueDatePicker)
}

describe('WxDatePicker', () => {
  it('stores dates in the format a Laravel date column expects', () => {
    const wrapper = mount(WxDatePicker, { props: { modelValue: '2026-03-14' } })

    expect(picker(wrapper).props('modelType')).toBe('yyyy-MM-dd')
    expect(picker(wrapper).props('modelValue')).toBe('2026-03-14')
  })

  it('shows dates day-first', () => {
    const wrapper = mount(WxDatePicker)

    expect(picker(wrapper).props('formats')).toEqual({ input: 'dd.MM.yyyy' })
  })

  it('keeps Date objects when valueFormat is "date"', () => {
    const wrapper = mount(WxDatePicker, { props: { valueFormat: 'date' } })

    expect(picker(wrapper).props('modelType')).toBeUndefined()
  })

  it('honours a custom value and display format', () => {
    const wrapper = mount(WxDatePicker, {
      props: { valueFormat: 'dd/MM/yyyy', format: 'yyyy.MM.dd' },
    })

    expect(picker(wrapper).props('modelType')).toBe('dd/MM/yyyy')
    expect(picker(wrapper).props('formats')).toEqual({ input: 'yyyy.MM.dd' })
  })

  it('leaves the time picker off for a plain date', () => {
    const wrapper = mount(WxDatePicker)

    expect(picker(wrapper).props('timeConfig')).toMatchObject({ enableTimePicker: false })
    expect(picker(wrapper).props('timePicker')).toBe(false)
  })

  it('writes the picked value into the model and emits change', async () => {
    const wrapper = mount(WxDatePicker, { props: { modelValue: null } })

    picker(wrapper).vm.$emit('update:model-value', '2026-03-14')
    await wrapper.vm.$nextTick()

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['2026-03-14'])
    expect(wrapper.emitted('change')?.at(-1)).toEqual(['2026-03-14'])
  })

  it('clears to null', async () => {
    const wrapper = mount(WxDatePicker, { props: { modelValue: '2026-03-14' } })

    picker(wrapper).vm.$emit('cleared')
    await wrapper.vm.$nextTick()

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([null])
    expect(wrapper.emitted('clear')).toHaveLength(1)
  })

  it('passes the generated id down so a form label can point at it', () => {
    const wrapper = mount(WxDatePicker)
    const attrs = picker(wrapper).props('inputAttrs') as Record<string, unknown>

    expect(attrs.id).toBeTruthy()
    expect(wrapper.get('input').attributes('id')).toBe(attrs.id)
  })

  it('marks the invalid state the way the library expects', () => {
    const wrapper = mount(WxDatePicker, { props: { status: 'error' } })
    const attrs = picker(wrapper).props('inputAttrs') as Record<string, unknown>

    expect(attrs.state).toBe(false)
    expect(wrapper.classes()).toContain('wx-datepicker--error')
  })

  it('leaves state undefined when the field is fine', () => {
    const wrapper = mount(WxDatePicker)
    const attrs = picker(wrapper).props('inputAttrs') as Record<string, unknown>

    expect(attrs.state).toBeUndefined()
  })

  it('disables the picker', () => {
    const wrapper = mount(WxDatePicker, { props: { disabled: true } })

    expect(picker(wrapper).props('disabled')).toBe(true)
    expect(wrapper.classes()).toContain('is-disabled')
  })

  it('teleports the menu by default so it escapes overflow: hidden', () => {
    const wrapper = mount(WxDatePicker)

    expect(picker(wrapper).props('teleport')).toBe(true)
  })

  it('starts the week on Monday', () => {
    const wrapper = mount(WxDatePicker)

    expect(picker(wrapper).props('weekStart')).toBe(1)
  })
})

describe('WxDateTimePicker', () => {
  it('asks for a date and a time, stored together', () => {
    const wrapper = mount(WxDateTimePicker)

    expect(picker(wrapper).props('modelType')).toBe('yyyy-MM-dd HH:mm')
    expect(picker(wrapper).props('formats')).toEqual({ input: 'dd.MM.yyyy HH:mm' })
    expect(picker(wrapper).props('timeConfig')).toMatchObject({ enableTimePicker: true })
  })

  it('adds seconds when asked', () => {
    const wrapper = mount(WxDateTimePicker, { props: { seconds: true } })

    expect(picker(wrapper).props('modelType')).toBe('yyyy-MM-dd HH:mm:ss')
    expect(picker(wrapper).props('timeConfig')).toMatchObject({ enableSeconds: true })
  })

  it('passes the model through', async () => {
    const wrapper = mount(WxDateTimePicker, { props: { modelValue: null } })

    picker(wrapper).vm.$emit('update:model-value', '2026-03-14 09:30')
    await wrapper.vm.$nextTick()

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['2026-03-14 09:30'])
  })
})

describe('WxTimePicker', () => {
  it('asks for a time only', () => {
    const wrapper = mount(WxTimePicker)

    expect(picker(wrapper).props('timePicker')).toBe(true)
    expect(picker(wrapper).props('modelType')).toBe('HH:mm')
    expect(picker(wrapper).props('formats')).toEqual({ input: 'HH:mm' })
  })
})
