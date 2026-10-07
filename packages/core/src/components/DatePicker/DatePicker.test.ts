import { afterAll, beforeAll, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick, ref } from 'vue'
import { VueDatePicker } from '@vuepic/vue-datepicker'
import WxDatePicker from './DatePicker.vue'
import WxDateTimePicker from '../DateTimePicker/DateTimePicker.vue'
import WxTimePicker from '../TimePicker/TimePicker.vue'
import WxDateRangePicker from '../DateRangePicker/DateRangePicker.vue'
import { dateLocaleKey } from '../../composables/useDateLocale'
import { dateTimezoneKey } from '../../composables/useDateTimezone'
import type { DateFnsLocale } from '../../internal/dateLocale'

type DateFnsLocalize = DateFnsLocale['localize']

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

  it('leaves the overlay height alone for a calendar', () => {
    const wrapper = mount(WxDatePicker)

    expect(picker(wrapper).props('config')).toBeUndefined()
  })

  it('starts the week on Monday', () => {
    const wrapper = mount(WxDatePicker)

    expect(picker(wrapper).props('weekStart')).toBe(1)
  })
})

/**
 * The picker bundles `en-US` and nothing else, so a calendar left to itself heads a
 * Russian screen with "Sep 2026" and a "Mo Tu We" row.
 */
describe('WxDatePicker language', () => {
  it('draws the calendar in the asked-for language', () => {
    const wrapper = mount(WxDatePicker, { props: { locale: 'ru' } })
    const locale = picker(wrapper).props('locale') as { localize: DateFnsLocalize }

    expect(locale.localize.month(8, { width: 'wide', context: 'standalone' })).toBe('сентябрь')
  })

  it('takes the application’s language when the field says nothing', () => {
    const wrapper = mount(WxDatePicker, {
      global: { provide: { [dateLocaleKey as symbol]: ref('ru') } },
    })
    const locale = picker(wrapper).props('locale') as { code: string }

    expect(locale.code).toBe('ru')
  })

  it('follows the application when it switches language', async () => {
    const locale = ref('ru')
    const wrapper = mount(WxDatePicker, {
      global: { provide: { [dateLocaleKey as symbol]: locale } },
    })

    locale.value = 'de'
    await wrapper.vm.$nextTick()

    expect((picker(wrapper).props('locale') as { code: string }).code).toBe('de')
  })

  it('lets the field override the application', () => {
    const wrapper = mount(WxDatePicker, {
      props: { locale: 'de' },
      global: { provide: { [dateLocaleKey as symbol]: ref('ru') } },
    })

    expect((picker(wrapper).props('locale') as { code: string }).code).toBe('de')
  })

  it.each([
    ['WxDateTimePicker', WxDateTimePicker],
    ['WxTimePicker', WxTimePicker],
    ['WxDateRangePicker', WxDateRangePicker],
  ])('%s passes the language through', (_name, Component) => {
    const wrapper = mount(Component, { props: { locale: 'ru' } })

    expect((picker(wrapper).props('locale') as { code: string }).code).toBe('ru')
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

  it('shrinks the menu, which is otherwise sized for a calendar it does not show', () => {
    const wrapper = mount(WxTimePicker)

    expect(picker(wrapper).props('config')).toMatchObject({ modeHeight: 125 })
  })
})

/**
 * Vue casts an absent boolean prop to `false`. A preset that forwards its whole
 * prop object therefore hands the real component an explicit `false` and silently
 * overrides its defaults — which is how `is24`, `clearable`, `autoApply` and
 * `teleport` all ended up off.
 */
describe.each([
  ['WxDateTimePicker', WxDateTimePicker],
  ['WxTimePicker', WxTimePicker],
])('%s defaults', (_name, Component) => {
  it('keeps the 24-hour clock', () => {
    const wrapper = mount(Component)

    expect(picker(wrapper).props('timeConfig')).toMatchObject({ is24: true })
  })

  it('keeps the menu teleported and clearable, autoApply on', () => {
    const wrapper = mount(Component)
    const attrs = picker(wrapper).props('inputAttrs') as Record<string, unknown>

    expect(picker(wrapper).props('teleport')).toBe(true)
    expect(picker(wrapper).props('autoApply')).toBe(true)
    expect(attrs.clearable).toBe(true)
  })

  it('still lets an explicit false through', () => {
    const wrapper = mount(Component, { props: { is24: false, teleport: false } })

    expect(picker(wrapper).props('timeConfig')).toMatchObject({ is24: false })
    expect(picker(wrapper).props('teleport')).toBe(false)
  })
})

/*
 * The machine running the tests is put in New York for these: a zone far from the site's, on
 * the other side of UTC, where a moment drawn on the reader's clock lands on another day.
 */
describe("WxDatePicker in the site's timezone", () => {
  const moment = "yyyy-MM-dd'T'HH:mm:ssXXX"
  let saved: string | undefined

  beforeAll(() => {
    saved = process.env.TZ
    process.env.TZ = 'America/New_York'
  })

  afterAll(() => {
    if (saved === undefined) delete process.env.TZ
    else process.env.TZ = saved
  })

  async function pickDay(wrapper: ReturnType<typeof mount>, day: string): Promise<void> {
    await wrapper.find('input').trigger('click')
    await new Promise((resolve) => setTimeout(resolve, 50))

    const cell = [...document.querySelectorAll<HTMLElement>('.dp--cell-inner')].find(
      (element) => element.textContent?.trim() === day,
    )

    expect(cell).toBeDefined()
    cell?.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await nextTick()
  }

  it("shows the wall clock of the provided zone and sends that zone's offset back", async () => {
    const wrapper = mount(WxDatePicker, {
      props: {
        type: 'datetime',
        valueFormat: moment,
        teleport: false,
        modelValue: '2026-10-12T09:30:00+08:00',
      },
      global: { provide: { [dateTimezoneKey as symbol]: 'Asia/Hong_Kong' } },
      attachTo: document.body,
    })
    await nextTick()

    expect(picker(wrapper).props('timezone')).toBe('Asia/Hong_Kong')
    expect((wrapper.find('input').element as HTMLInputElement).value).toBe(
      '12.10.2026 09:30 GMT+08:00',
    )

    await pickDay(wrapper, '15')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['2026-10-15T09:30:00+08:00'])
    wrapper.unmount()
  })

  it('keeps the day of a date-only moment', async () => {
    const wrapper = mount(WxDatePicker, {
      props: {
        type: 'date',
        valueFormat: moment,
        teleport: false,
        timezone: 'Asia/Hong_Kong',
        modelValue: '2026-10-12T00:00:00+08:00',
      },
      attachTo: document.body,
    })
    await nextTick()

    expect((wrapper.find('input').element as HTMLInputElement).value).toBe('12.10.2026')

    await pickDay(wrapper, '14')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['2026-10-14T00:00:00+08:00'])
    wrapper.unmount()
  })

  it('leaves a value without an offset alone: it is a wall clock already', () => {
    const wrapper = mount(WxDatePicker, {
      props: { type: 'datetime', timezone: 'Asia/Hong_Kong', modelValue: '2026-10-12 09:30' },
    })

    expect(picker(wrapper).props('timezone')).toBeUndefined()
    expect(picker(wrapper).props('formats')).toEqual({ input: 'dd.MM.yyyy HH:mm' })
  })

  it("names no zone when it is the reader's own", () => {
    const wrapper = mount(WxDatePicker, {
      props: { type: 'datetime', valueFormat: moment, timezone: 'America/New_York' },
    })

    expect(picker(wrapper).props('timezone')).toBe('America/New_York')
    expect(picker(wrapper).props('formats')).toEqual({ input: 'dd.MM.yyyy HH:mm' })
  })
})
