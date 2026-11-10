import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import CodeField from './CodeField.vue'

/**
 * `wx-code` as an editor meets it: what is typed is a code under the fingers, and a sign that
 * leaves the code as it was does not stay in the input either.
 */
function field(props: Record<string, unknown> = {}) {
  return mount(CodeField, { props: { modelValue: '', ...props }, attachTo: document.body })
}

describe('WxCodeField', () => {
  it('turns what is typed into a code at once', async () => {
    const wrapper = field({ maxlength: 32 })
    const input = wrapper.find('input')

    await input.setValue('Тормозные колодки')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['tormoznye-kolodki'])
    expect((input.element as HTMLInputElement).value).toBe('tormoznye-kolodki')
    // The cut is the field's own: the browser's would cut a pasted name before it is a code.
    expect(input.attributes('maxlength')).toBeUndefined()
    wrapper.unmount()
  })

  it('keeps the separator while typing and drops it when the field is left', async () => {
    const wrapper = field({ separator: '_' })
    const input = wrapper.find('input')

    await input.setValue('brake ')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['brake_'])

    await wrapper.setProps({ modelValue: 'brake_' })
    await input.trigger('blur')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['brake'])
    expect((input.element as HTMLInputElement).value).toBe('brake')
    wrapper.unmount()
  })

  it('takes a sign that changes nothing back out of the input', async () => {
    const wrapper = field({ modelValue: 'brake' })
    const input = wrapper.find('input')

    await input.setValue('brake!')

    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    expect((input.element as HTMLInputElement).value).toBe('brake')
    wrapper.unmount()
  })
})
