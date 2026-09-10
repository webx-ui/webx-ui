import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
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
