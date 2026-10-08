import { afterEach, describe, expect, it } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import WxInput from './Input.vue'

describe('WxInput', () => {
  it('renders an input with the model value', () => {
    const wrapper = mount(WxInput, { props: { modelValue: 'hello' } })

    expect(wrapper.get('input').element.value).toBe('hello')
    expect(wrapper.classes()).toContain('wx-input')
    expect(wrapper.classes()).toContain('wx-input--md')
  })

  it('updates the model and emits input on typing', async () => {
    const wrapper = mount(WxInput, { props: { modelValue: '' } })

    await wrapper.get('input').setValue('webx')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['webx'])
    expect(wrapper.emitted('input')?.at(-1)).toEqual(['webx'])
  })

  it('emits focus and blur and toggles the focused class', async () => {
    const wrapper = mount(WxInput)
    const input = wrapper.get('input')

    await input.trigger('focus')
    expect(wrapper.classes()).toContain('is-focused')

    await input.trigger('blur')
    expect(wrapper.classes()).not.toContain('is-focused')
    expect(wrapper.emitted('focus')).toHaveLength(1)
    expect(wrapper.emitted('blur')).toHaveLength(1)
  })

  it('shows the clear button only when clearable and filled', async () => {
    const wrapper = mount(WxInput, { props: { modelValue: '', clearable: true } })
    expect(wrapper.find('.wx-input__clear').exists()).toBe(false)

    await wrapper.setProps({ modelValue: 'text' })
    expect(wrapper.find('.wx-input__clear').exists()).toBe(true)

    await wrapper.get('.wx-input__clear').trigger('click')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([''])
    expect(wrapper.emitted('clear')).toHaveLength(1)
  })

  it('marks the error status on the wrapper and the input', () => {
    const wrapper = mount(WxInput, { props: { status: 'error' } })

    expect(wrapper.classes()).toContain('wx-input--error')
    expect(wrapper.get('input').attributes('aria-invalid')).toBe('true')
  })

  it('renders a counter when showCount and maxlength are set', () => {
    const wrapper = mount(WxInput, {
      props: { modelValue: 'abc', showCount: true, maxlength: 10 },
    })

    expect(wrapper.get('.wx-input__count').text()).toBe('3/10')
  })

  it('forwards attributes to the inner input, not the wrapper', () => {
    const wrapper = mount(WxInput, { attrs: { name: 'title', id: 'title' } })

    expect(wrapper.get('input').attributes('name')).toBe('title')
    expect(wrapper.attributes('name')).toBeUndefined()
  })

  it('does not clear while disabled', async () => {
    const wrapper = mount(WxInput, {
      props: { modelValue: 'text', clearable: true, disabled: true },
    })

    expect(wrapper.find('.wx-input__clear').exists()).toBe(false)
    expect(wrapper.get('input').attributes('disabled')).toBeDefined()
  })
})

describe('WxInput placeholders', () => {
  const tokens = [
    { name: 'phone', value: '+1 555 0100' },
    { name: 'email', value: 'hello@example.com' },
    { name: 'year', value: '2026' },
  ]

  afterEach(() => {
    document.body.innerHTML = ''
  })

  function options(): HTMLElement[] {
    return Array.from(document.querySelectorAll<HTMLElement>('.wx-token-menu__option'))
  }

  async function type(wrapper: VueWrapper, value: string) {
    const input = wrapper.get('input').element as HTMLInputElement
    input.value = value
    input.setSelectionRange(value.length, value.length)
    await wrapper.get('input').trigger('input')
    await flushPromises()
  }

  it('opens the list on a bracket and narrows it by what follows', async () => {
    const wrapper = mount(WxInput, { props: { tokens }, attachTo: document.body })

    await type(wrapper, 'Call [')
    expect(options().map((option) => option.textContent)).toEqual([
      '[phone]+1 555 0100',
      '[email]hello@example.com',
      '[year]2026',
    ])

    await type(wrapper, 'Call [p')
    expect(options()).toHaveLength(1)

    // Nothing starts like this: the bracket is just a bracket.
    await type(wrapper, 'Call [x')
    expect(options()).toHaveLength(0)
    wrapper.unmount()
  })

  it('replaces what was typed with the chosen placeholder on Enter', async () => {
    const wrapper = mount(WxInput, { props: { tokens }, attachTo: document.body })

    await type(wrapper, 'Call [e')
    await wrapper.get('input').trigger('keydown', { key: 'Enter' })

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['Call [email]'])
    expect((wrapper.get('input').element as HTMLInputElement).selectionStart).toBe(12)
    await flushPromises()
    expect(options()).toHaveLength(0)
    wrapper.unmount()
  })

  it('walks the list with the arrows and closes on Escape', async () => {
    const wrapper = mount(WxInput, { props: { tokens }, attachTo: document.body })

    await type(wrapper, '[')
    await wrapper.get('input').trigger('keydown', { key: 'ArrowDown' })
    expect(options()[1]?.classList.contains('is-active')).toBe(true)
    expect(wrapper.get('input').attributes('aria-activedescendant')).toBe(options()[1]?.id)

    await wrapper.get('input').trigger('keydown', { key: 'Escape' })
    await flushPromises()
    expect(options()).toHaveLength(0)
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['['])
    wrapper.unmount()
  })

  it('does not open after a second bracket, which is the escaped form', async () => {
    const wrapper = mount(WxInput, { props: { tokens }, attachTo: document.body })

    await type(wrapper, '[[')
    expect(options()).toHaveLength(0)
    wrapper.unmount()
  })

  it('draws known placeholders as chips and leaves the rest as text', () => {
    const wrapper = mount(WxInput, {
      props: { tokens, modelValue: 'Call [phone], [fax] or [[phone]] in [year format=short]' },
    })

    const chips = wrapper.findAll('.wx-input__mirror .wx-token').map((chip) => chip.text())
    expect(chips).toEqual(['[phone]', '[year format=short]'])
    expect(wrapper.classes()).toContain('is-tokenized')
    // The value itself is untouched.
    expect(wrapper.get('input').element.value).toBe(
      'Call [phone], [fax] or [[phone]] in [year format=short]',
    )
  })

  it('has no mirror and no button without placeholders', () => {
    const wrapper = mount(WxInput, { props: { modelValue: 'Call [phone]' } })

    expect(wrapper.find('.wx-input__mirror').exists()).toBe(false)
    expect(wrapper.find('.wx-token-button').exists()).toBe(false)
    expect(wrapper.classes()).not.toContain('is-tokenized')
  })

  it('lists every placeholder from the button and inserts the one clicked at the caret', async () => {
    const wrapper = mount(WxInput, {
      props: { tokens, modelValue: 'Call  today', tokensTitle: 'Shortcodes' },
      attachTo: document.body,
    })
    const input = wrapper.get('input').element as HTMLInputElement
    input.focus()
    input.setSelectionRange(5, 5)

    await wrapper.get('.wx-token-button').trigger('click')
    await flushPromises()

    expect(document.querySelector('.wx-token-menu__title')?.textContent).toBe('Shortcodes')
    expect(options()).toHaveLength(3)

    options()[0]!.click()
    await flushPromises()

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['Call [phone] today'])
    expect(options()).toHaveLength(0)
    wrapper.unmount()
  })
})
