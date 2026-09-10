import { afterEach, describe, expect, it } from 'vitest'
import { enableAutoUnmount, mount } from '@vue/test-utils'
import { defineComponent, h, nextTick } from 'vue'
import WxSelect from './Select.vue'
import WxFormItem from '../FormItem/FormItem.vue'

// Teleported lists outlive their wrapper otherwise, and leak into the next test.
enableAutoUnmount(afterEach)

const options = [
  { label: 'Draft', value: 'draft' },
  { label: 'Published', value: 'published' },
  { label: 'Archived', value: 'archived', disabled: true },
]

/**
 * The list is teleported, so it is mounted into the document rather than into the
 * wrapper. Tests that look at options query the document.
 */
function mountSelect(props: Record<string, unknown> = {}) {
  return mount(WxSelect, { props: { options, ...props }, attachTo: document.body })
}

function optionTexts() {
  return [...document.querySelectorAll('.wx-select__option')].map((el) => el.textContent?.trim())
}

describe('WxSelect', () => {
  it('shows the placeholder while nothing is selected', () => {
    const wrapper = mountSelect({ modelValue: null, placeholder: 'Pick a status' })

    expect(wrapper.get('.wx-select__placeholder').text()).toBe('Pick a status')
  })

  it('shows the label of the selected value, not the value', () => {
    const wrapper = mountSelect({ modelValue: 'published' })

    expect(wrapper.get('.wx-select__single').text()).toBe('Published')
  })

  it('falls back to the raw value when no option matches it', () => {
    const wrapper = mountSelect({ modelValue: 'gone' })

    expect(wrapper.get('.wx-select__single').text()).toBe('gone')
  })

  it('renders one option per entry, marking the selected and the disabled one', async () => {
    const wrapper = mountSelect({ modelValue: 'draft' })

    await wrapper.get('.wx-select__toggle').trigger('click')
    await nextTick()

    expect(optionTexts()).toEqual(['Draft', 'Published', 'Archived'])
    const items = [...document.querySelectorAll('.wx-select__option')]
    expect(items[0].getAttribute('data-state')).toBe('checked')
    expect(items[2].hasAttribute('data-disabled')).toBe(true)
  })

  it('opens on a click, which the underlying combobox does not do by default', async () => {
    const wrapper = mountSelect({ modelValue: null })
    expect(optionTexts()).toHaveLength(0)

    await wrapper.get('.wx-select__toggle').trigger('click')
    await nextTick()

    expect(optionTexts()).toHaveLength(3)
    expect(wrapper.get('.wx-select').classes()).toContain('is-open')
  })

  it('opens when the field itself is clicked, not only the arrow', async () => {
    const wrapper = mountSelect({ modelValue: null })

    await wrapper.get('.wx-select__anchor').trigger('click')
    await nextTick()

    expect(optionTexts()).toHaveLength(3)
  })

  it('stays shut when a disabled field is clicked', async () => {
    const wrapper = mountSelect({ modelValue: null, disabled: true })

    await wrapper.get('.wx-select__anchor').trigger('click')
    await nextTick()

    expect(optionTexts()).toHaveLength(0)
  })

  it('shows the label of the selection in the search field, not its value', async () => {
    const wrapper = mountSelect({
      filterable: true,
      modelValue: 'published',
      options: [{ label: 'Maria Kovalenko', value: 'published' }],
    })
    await nextTick()

    expect((wrapper.get('.wx-select__input').element as HTMLInputElement).value).toBe(
      'Maria Kovalenko',
    )
  })

  it('renders a search field only when filterable', () => {
    expect(mountSelect().find('.wx-select__input').exists()).toBe(false)
    expect(mountSelect({ filterable: true }).find('.wx-select__input').exists()).toBe(true)
  })

  it('reports what was typed so options can come from a backend', async () => {
    const wrapper = mountSelect({ filterable: true })

    // The combobox only tracks the search term while its list is open.
    await wrapper.get('.wx-select__toggle').trigger('click')
    await nextTick()
    await wrapper.get('.wx-select__input').setValue('pub')
    await nextTick()
    await nextTick()

    expect(wrapper.emitted('search')?.at(-1)).toEqual(['pub'])
  })

  it('renders a tag per value when multiple', () => {
    const wrapper = mountSelect({ multiple: true, modelValue: ['draft', 'published'] })

    expect(
      wrapper.findAll('.wx-select__tag').map((tag) => tag.text().replace(/\s*✕$/, '')),
    ).toEqual(['Draft', 'Published'])
  })

  it('removes one value through its tag', async () => {
    const wrapper = mountSelect({ multiple: true, modelValue: ['draft', 'published'] })

    await wrapper.get('.wx-select__tag-remove').trigger('click')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([['published']])
    expect(wrapper.emitted('change')?.at(-1)).toEqual([['published']])
  })

  it('clears to null, and to an empty array when multiple', async () => {
    const single = mountSelect({ modelValue: 'draft', clearable: true })
    await single.get('.wx-select__clear').trigger('click')
    expect(single.emitted('update:modelValue')?.at(-1)).toEqual([null])
    expect(single.emitted('clear')).toHaveLength(1)

    const many = mountSelect({ multiple: true, modelValue: ['draft'], clearable: true })
    await many.get('.wx-select__clear').trigger('click')
    expect(many.emitted('update:modelValue')?.at(-1)).toEqual([[]])
  })

  it('offers the clear button only when there is something to clear', async () => {
    const wrapper = mountSelect({ modelValue: null, clearable: true })
    expect(wrapper.find('.wx-select__clear').exists()).toBe(false)

    await wrapper.setProps({ modelValue: 'draft' })
    expect(wrapper.find('.wx-select__clear').exists()).toBe(true)
  })

  it('hides the clear button while disabled', () => {
    const wrapper = mountSelect({ modelValue: 'draft', clearable: true, disabled: true })

    expect(wrapper.find('.wx-select__clear').exists()).toBe(false)
    expect(wrapper.get('.wx-select').classes()).toContain('is-disabled')
  })

  it('drops the tag remove buttons while disabled', () => {
    const wrapper = mountSelect({ multiple: true, modelValue: ['draft'], disabled: true })

    expect(wrapper.find('.wx-select__tag').exists()).toBe(true)
    expect(wrapper.find('.wx-select__tag-remove').exists()).toBe(false)
  })

  it('applies size and status modifiers', () => {
    const wrapper = mountSelect({ size: 'lg', status: 'error' })

    expect(wrapper.get('.wx-select').classes()).toContain('wx-select--lg')
    expect(wrapper.get('.wx-select').classes()).toContain('wx-select--error')
  })

  it('takes id, error state and description from a form item', () => {
    const wrapper = mount(
      defineComponent({
        render: () =>
          h(
            WxFormItem,
            { label: 'Status', error: 'The status field is required.' },
            { default: () => h(WxSelect, { options }) },
          ),
      }),
      { attachTo: document.body },
    )

    const select = wrapper.get('.wx-select')
    expect(select.classes()).toContain('wx-select--error')
    const labelFor = wrapper.get('label').attributes('for')
    expect(wrapper.find(`#${labelFor}`).exists()).toBe(true)
  })
})
