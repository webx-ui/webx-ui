import { afterEach, describe, expect, it } from 'vitest'
import { enableAutoUnmount, mount } from '@vue/test-utils'
import { defineComponent, h, nextTick, ref } from 'vue'
import { flushPromises } from '@vue/test-utils'
import WxDialog from '../Dialog/Dialog.vue'
import { openModal, useModal } from '../../composables/useModal'
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

  it('leaves the search field empty when multiple, where the tags carry the selection', async () => {
    const wrapper = mountSelect({ filterable: true, multiple: true, modelValue: ['draft'] })
    await nextTick()

    // Without control the input falls back to rendering the model: "draft,published".
    expect((wrapper.get('.wx-select__input').element as HTMLInputElement).value).toBe('')

    await wrapper.setProps({ modelValue: ['draft', 'published'] })
    await nextTick()

    expect((wrapper.get('.wx-select__input').element as HTMLInputElement).value).toBe('')
    expect(wrapper.findAll('.wx-select__tag')).toHaveLength(2)
  })

  it('shows the label of a value whose options arrive after it', async () => {
    const wrapper = mountSelect({ filterable: true, modelValue: 'published', options: [] })
    await nextTick()

    expect((wrapper.get('.wx-select__input').element as HTMLInputElement).value).toBe('published')

    await wrapper.setProps({ options })

    expect((wrapper.get('.wx-select__input').element as HTMLInputElement).value).toBe('Published')
  })

  it('puts the label back after a search that picked nothing', async () => {
    const wrapper = mountSelect({ filterable: true, modelValue: 'published' })
    await nextTick()

    await wrapper.get('.wx-select__toggle').trigger('click')
    await wrapper.get('.wx-select__input').setValue('zzz')
    await wrapper.get('.wx-select__input').trigger('keydown', { key: 'Escape' })
    await nextTick()
    await nextTick()

    expect((wrapper.get('.wx-select__input').element as HTMLInputElement).value).toBe('Published')
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

describe('WxSelect with an option worth an empty string', () => {
  const withAll = [{ label: 'Any event', value: '' }, ...options]

  it('shows that option as the value rather than an empty field', () => {
    const wrapper = mountSelect({ options: withAll, modelValue: '' })

    expect(wrapper.get('.wx-select__single').text()).toBe('Any event')
  })

  it('opens the list with every option, the empty one checked', async () => {
    const wrapper = mountSelect({ options: withAll, modelValue: '' })

    await wrapper.get('.wx-select__anchor').trigger('click')
    await nextTick()

    expect(optionTexts()).toEqual(['Any event', 'Draft', 'Published', 'Archived'])
    expect(document.querySelector('.wx-select__option')?.getAttribute('data-state')).toBe('checked')
  })

  it("hands back '' when it is picked, and the value of any other", async () => {
    const wrapper = mountSelect({ options: withAll, modelValue: 'draft' })

    await wrapper.get('.wx-select__anchor').trigger('click')
    await nextTick()
    document.querySelector<HTMLElement>('.wx-select__option')?.click()
    await nextTick()

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([''])
    expect(wrapper.emitted('change')?.at(-1)).toEqual([''])
  })
})

describe('WxSelect in a modal opened from code', () => {
  const Picker = defineComponent({
    setup() {
      const { open, dismiss } = useModal<void>()
      const event = ref('')

      return () =>
        h(
          WxDialog,
          { open: open.value, 'onUpdate:open': () => dismiss(), title: 'New channel' },
          {
            default: () => [
              h(WxSelect, {
                modelValue: event.value,
                'onUpdate:modelValue': (value: unknown) => (event.value = String(value)),
                options: [
                  { label: 'Any event', value: '' },
                  { label: 'A submission of a form', value: 'inbox.submission' },
                ],
              }),
              h('span', { class: 'picked' }, event.value),
            ],
            footer: () => h('button', { class: 'cancel', onClick: () => dismiss() }, 'Cancel'),
          },
        )
    },
  })

  it('shows the options, takes a pick, and leaves the dialog working', async () => {
    const answer = openModal(Picker, { duration: 0 })
    await flushPromises()

    document.querySelector<HTMLElement>('.wx-dialog .wx-select__anchor')?.click()
    await flushPromises()

    expect(optionTexts()).toEqual(['Any event', 'A submission of a form'])

    document.querySelectorAll<HTMLElement>('.wx-select__option')[1]?.click()
    await flushPromises()

    expect(document.querySelector('.picked')?.textContent).toBe('inbox.submission')
    expect(document.querySelector('.wx-dialog .wx-select__single')?.textContent).toBe(
      'A submission of a form',
    )

    document.querySelector<HTMLElement>('.wx-dialog .cancel')?.click()
    await expect(answer).resolves.toBeUndefined()
  })

  it('closes the list on Escape and keeps the dialog open', async () => {
    const answer = openModal(Picker, { duration: 0 })
    await flushPromises()

    document.querySelector<HTMLElement>('.wx-dialog .wx-select__anchor')?.click()
    await flushPromises()
    expect(optionTexts()).toHaveLength(2)

    document.activeElement?.dispatchEvent(
      new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }),
    )
    await flushPromises()

    expect(optionTexts()).toHaveLength(0)
    expect(document.querySelector('.wx-dialog')).not.toBeNull()

    answer.close()
    await answer
  })
})
