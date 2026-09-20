import { afterEach, describe, expect, it } from 'vitest'
import { enableAutoUnmount, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import WxIconPicker from './IconPicker.vue'

// Teleported panels outlive their wrapper otherwise, and leak into the next test.
enableAutoUnmount(afterEach)

function mountPicker(props: Record<string, unknown> = {}) {
  return mount(WxIconPicker, { props, attachTo: document.body })
}

const options = () => document.querySelectorAll<HTMLElement>('.wx-icon-picker__option')

describe('WxIconPicker', () => {
  it('shows the chosen icon and its name', () => {
    const wrapper = mountPicker({ modelValue: 'search' })

    expect(wrapper.get('.wx-icon-picker__preview').classes()).not.toContain('is-empty')
    expect((wrapper.get('input').element as HTMLInputElement).value).toBe('search')
  })

  it('marks an empty field rather than drawing nothing in silence', () => {
    const wrapper = mountPicker({ modelValue: null })

    expect(wrapper.get('.wx-icon-picker__preview').classes()).toContain('is-empty')
  })

  it('opens on focus with the whole set in it', async () => {
    const wrapper = mountPicker({ modelValue: null })

    await wrapper.get('input').trigger('focus')
    // The panel opens a turn later, so that the click that focused the field cannot dismiss it.
    await new Promise((resolve) => setTimeout(resolve))
    await nextTick()

    expect(options().length).toBeGreaterThan(100)
  })

  it('filters by what is typed', async () => {
    const wrapper = mountPicker({ modelValue: null })

    await wrapper.get('input').setValue('trash')

    const names = [...options()].map((option) => option.getAttribute('title'))

    expect(names).toContain('trash')
    expect(names).not.toContain('search')
  })

  /* The point of the whole component: a name the set does not have never becomes the value. */
  it('takes a name only by picking one', async () => {
    const wrapper = mountPicker({ modelValue: null })

    await wrapper.get('input').setValue('not-an-icon')

    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    expect(document.querySelector('.wx-icon-picker__empty')).not.toBeNull()
  })

  it('chooses the one match on Enter', async () => {
    const wrapper = mountPicker({ modelValue: null })

    await wrapper.get('input').setValue('trash')
    await wrapper.get('input').trigger('keydown.enter')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['trash'])
    expect(wrapper.emitted('change')?.at(-1)).toEqual(['trash'])
  })

  it('empties the field when asked', async () => {
    const wrapper = mountPicker({ modelValue: 'search', clearable: true })

    await wrapper.get('.wx-icon-picker__clear').trigger('click')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([null])
  })

  it('has no clear button with nothing to clear', () => {
    const wrapper = mountPicker({ modelValue: null, clearable: true })

    expect(wrapper.find('.wx-icon-picker__clear').exists()).toBe(false)
  })
})
