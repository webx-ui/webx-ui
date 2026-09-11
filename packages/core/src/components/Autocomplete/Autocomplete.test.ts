import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import WxAutocomplete from './Autocomplete.vue'

// The list is teleported, so it would outlive its wrapper and leak into the next test.
enableAutoUnmount(afterEach)

const options = [
  { value: 'Kyiv', description: 'Ukraine' },
  { value: 'Kharkiv' },
  { value: 'Krakow', disabled: true },
]

function factory(props: Record<string, unknown> = {}) {
  return mount(WxAutocomplete, {
    props: { options, ...props },
    attachTo: document.body,
  })
}

describe('WxAutocomplete', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('renders an input carrying the model text', async () => {
    const wrapper = factory({ modelValue: 'Ky', placeholder: 'City' })
    const input = wrapper.get('input')

    expect(input.element.value).toBe('Ky')
    expect(input.attributes('placeholder')).toBe('City')
    expect(input.attributes('role')).toBe('combobox')
  })

  it('emits the search term once typing settles', async () => {
    const wrapper = factory({ debounce: 200 })

    await wrapper.get('input').setValue('Kyi')

    expect(wrapper.emitted('change')?.at(-1)).toEqual(['Kyi'])
    expect(wrapper.emitted('search')).toBeUndefined()

    vi.advanceTimersByTime(200)
    expect(wrapper.emitted('search')).toEqual([['Kyi']])
  })

  it('collapses a burst of keystrokes into one search', async () => {
    const wrapper = factory({ debounce: 200 })
    const input = wrapper.get('input')

    await input.setValue('K')
    vi.advanceTimersByTime(100)
    await input.setValue('Ky')
    vi.advanceTimersByTime(100)
    await input.setValue('Kyi')
    vi.advanceTimersByTime(200)

    expect(wrapper.emitted('search')).toEqual([['Kyi']])
  })

  it('waits for min-length before searching', async () => {
    const wrapper = factory({ minLength: 3, debounce: 0 })

    await wrapper.get('input').setValue('Ky')
    vi.advanceTimersByTime(50)
    expect(wrapper.emitted('search')).toBeUndefined()

    await wrapper.get('input').setValue('Kyi')
    vi.advanceTimersByTime(50)
    expect(wrapper.emitted('search')).toEqual([['Kyi']])
  })

  it('clears the field and says so', async () => {
    const wrapper = factory({ modelValue: 'Kyiv', clearable: true })

    await wrapper.get('.wx-autocomplete__clear').trigger('click')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([''])
    expect(wrapper.emitted('clear')).toHaveLength(1)
  })

  it('has no clear button when the field is empty or not clearable', () => {
    expect(
      factory({ modelValue: '', clearable: true }).find('.wx-autocomplete__clear').exists(),
    ).toBe(false)
    expect(factory({ modelValue: 'Kyiv' }).find('.wx-autocomplete__clear').exists()).toBe(false)
  })

  it('shows a spinner while a request is in flight', () => {
    const wrapper = factory({ loading: true, modelValue: 'Ky', clearable: true })

    expect(wrapper.find('.wx-autocomplete__spinner').exists()).toBe(true)
    // The spinner takes the clear button's place rather than crowding beside it.
    expect(wrapper.find('.wx-autocomplete__clear').exists()).toBe(false)
  })

  it('puts the picked suggestion in the field without searching for it again', async () => {
    const wrapper = factory({ debounce: 0 })

    await wrapper.get('input').setValue('Ky')
    vi.advanceTimersByTime(50)
    await nextTick()
    await nextTick()

    const option = document.body.querySelector('.wx-autocomplete__option') as HTMLElement
    option.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await nextTick()

    expect(wrapper.emitted('select')?.at(-1)).toEqual([options[0]])
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['Kyiv'])

    // The field now holds the suggestion; asking the backend for it would be pointless.
    vi.advanceTimersByTime(500)
    expect(wrapper.emitted('search')).toEqual([['Ky']])
  })

  it('carries size and status through to the field', () => {
    const wrapper = factory({ size: 'sm', status: 'error' })

    // The root is the combobox primitive, so the styled element is queried by class.
    const root = wrapper.get('.wx-autocomplete')
    expect(root.classes()).toContain('wx-autocomplete--sm')
    expect(root.classes()).toContain('wx-autocomplete--error')
    expect(wrapper.get('input').attributes('aria-invalid')).toBe('true')
  })

  it('offers the options when the field is focused', async () => {
    const wrapper = factory({ modelValue: 'K' })

    await wrapper.get('input').trigger('focus')
    await nextTick()
    await nextTick()

    const list = document.body.querySelectorAll('.wx-autocomplete__option')
    expect(list).toHaveLength(3)
    expect(list[0].textContent).toContain('Kyiv')
    expect(list[0].textContent).toContain('Ukraine')
    expect(wrapper.emitted('open')).toHaveLength(1)
  })
})
