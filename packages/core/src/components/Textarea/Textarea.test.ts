import { afterEach, describe, expect, it } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import WxTextarea from './Textarea.vue'

describe('WxTextarea', () => {
  it('renders a textarea with the model value and rows', () => {
    const wrapper = mount(WxTextarea, { props: { modelValue: 'hello', rows: 5 } })

    expect(wrapper.get('textarea').element.value).toBe('hello')
    expect(wrapper.get('textarea').attributes('rows')).toBe('5')
  })

  it('updates the model on input', async () => {
    const wrapper = mount(WxTextarea, { props: { modelValue: '' } })

    await wrapper.get('textarea').setValue('typed')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['typed'])
    expect(wrapper.emitted('input')?.at(-1)).toEqual(['typed'])
  })

  it('tracks focus on the wrapper', async () => {
    const wrapper = mount(WxTextarea)
    const textarea = wrapper.get('textarea')

    await textarea.trigger('focus')
    expect(wrapper.classes()).toContain('is-focused')

    await textarea.trigger('blur')
    expect(wrapper.classes()).not.toContain('is-focused')
  })

  it('renders a counter when showCount and maxlength are set', () => {
    const wrapper = mount(WxTextarea, {
      props: { modelValue: 'abcd', showCount: true, maxlength: 100 },
    })

    expect(wrapper.get('.wx-textarea__count').text()).toBe('4/100')
  })

  it('marks the error status', () => {
    const wrapper = mount(WxTextarea, { props: { status: 'error' } })

    expect(wrapper.classes()).toContain('wx-textarea--error')
    expect(wrapper.get('textarea').attributes('aria-invalid')).toBe('true')
  })

  it('applies the resize preference', () => {
    const wrapper = mount(WxTextarea, { props: { resize: 'none' } })

    expect(wrapper.get('textarea').attributes('style')).toContain('resize: none')
  })

  it('marks itself as autosizing', () => {
    const wrapper = mount(WxTextarea, { props: { autosize: true } })

    expect(wrapper.classes()).toContain('is-autosize')
  })

  it('forwards attributes to the textarea, not the wrapper', () => {
    const wrapper = mount(WxTextarea, { attrs: { name: 'body' } })

    expect(wrapper.get('textarea').attributes('name')).toBe('body')
    expect(wrapper.attributes('name')).toBeUndefined()
  })

  it('does not emit input while disabled', () => {
    const wrapper = mount(WxTextarea, { props: { disabled: true } })

    expect(wrapper.get('textarea').attributes('disabled')).toBeDefined()
    expect(wrapper.classes()).toContain('is-disabled')
  })
})

describe('WxTextarea placeholders', () => {
  const tokens = [
    { name: 'phone', value: '+1 555 0100' },
    { name: 'email', value: 'hello@example.com' },
  ]

  afterEach(() => {
    document.body.innerHTML = ''
  })

  function options(): HTMLElement[] {
    return Array.from(document.querySelectorAll<HTMLElement>('.wx-token-menu__option'))
  }

  async function type(wrapper: VueWrapper, value: string) {
    const textarea = wrapper.get('textarea').element as HTMLTextAreaElement
    textarea.value = value
    textarea.setSelectionRange(value.length, value.length)
    await wrapper.get('textarea').trigger('input')
    await flushPromises()
  }

  it('suggests on a bracket and inserts on Tab', async () => {
    const wrapper = mount(WxTextarea, { props: { tokens }, attachTo: document.body })

    await type(wrapper, 'Line one\nCall [ph')
    expect(options()).toHaveLength(1)

    await wrapper.get('textarea').trigger('keydown', { key: 'Tab' })
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['Line one\nCall [phone]'])
    wrapper.unmount()
  })

  it('draws chips for known names only', () => {
    const wrapper = mount(WxTextarea, {
      props: { tokens, modelValue: 'Write to [email]\nor [unknown], not [[email]]' },
    })

    expect(wrapper.findAll('.wx-textarea__mirror .wx-token').map((chip) => chip.text())).toEqual([
      '[email]',
    ])
    expect(wrapper.classes()).toContain('is-tokenized')
  })

  it('keeps the mirror scrolled with the textarea', async () => {
    const wrapper = mount(WxTextarea, {
      props: { tokens, modelValue: 'a\nb\nc\nd\n[phone]', rows: 2 },
    })
    const textarea = wrapper.get('textarea').element
    const mirror = wrapper.get('.wx-textarea__mirror').element

    // jsdom has no layout: the scroll positions are stood in for.
    Object.defineProperty(textarea, 'scrollTop', { value: 40, configurable: true })
    Object.defineProperty(mirror, 'scrollTop', { value: 0, writable: true, configurable: true })

    await wrapper.get('textarea').trigger('scroll')
    expect(mirror.scrollTop).toBe(40)
  })

  it('inserts from the help button where the caret is', async () => {
    const wrapper = mount(WxTextarea, {
      props: { tokens, modelValue: 'ab' },
      attachTo: document.body,
    })
    const textarea = wrapper.get('textarea').element as HTMLTextAreaElement
    textarea.focus()
    textarea.setSelectionRange(1, 1)

    await wrapper.get('.wx-token-button').trigger('click')
    await flushPromises()
    options()[1]!.click()

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['a[email]b'])
    wrapper.unmount()
  })
})
