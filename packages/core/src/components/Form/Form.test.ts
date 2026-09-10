import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h, nextTick } from 'vue'
import WxForm from './Form.vue'
import WxFormItem from '../FormItem/FormItem.vue'
import WxInput from '../Input/Input.vue'
import WxCheckboxGroup from '../CheckboxGroup/CheckboxGroup.vue'

/** A field wrapped in an item, optionally wrapped in a form. */
function mountField(formProps: Record<string, unknown> | null, itemProps: Record<string, unknown>) {
  const field = defineComponent({
    render: () => h(WxFormItem, itemProps, { default: () => h(WxInput) }),
  })
  if (!formProps) return mount(field)
  return mount(defineComponent({ render: () => h(WxForm, formProps, { default: () => h(field) }) }))
}

describe('WxForm', () => {
  it('prevents the native submit and emits its own', async () => {
    const wrapper = mount(WxForm)

    await wrapper.trigger('submit')

    expect(wrapper.emitted('submit')).toHaveLength(1)
    expect(wrapper.attributes('novalidate')).toBeDefined()
  })

  it('cascades disabled to the controls inside', () => {
    const wrapper = mountField({ disabled: true }, {})

    expect(wrapper.get('input').attributes('disabled')).toBeDefined()
  })

  it('cascades size to the controls inside', () => {
    const wrapper = mountField({ size: 'lg' }, {})

    expect(wrapper.get('.wx-input').classes()).toContain('wx-input--lg')
  })

  it('lets a form item override the form size', () => {
    const wrapper = mountField({ size: 'lg' }, { size: 'sm' })

    expect(wrapper.get('.wx-input').classes()).toContain('wx-input--sm')
  })
})

describe('WxFormItem', () => {
  it('links the label to the control it wraps', () => {
    const wrapper = mountField(null, { label: 'Title' })
    const id = wrapper.get('input').attributes('id')

    expect(id).toBeTruthy()
    expect(wrapper.get('label').attributes('for')).toBe(id)
    expect(wrapper.get('label').text()).toContain('Title')
  })

  it('marks a required field', () => {
    const wrapper = mountField(null, { label: 'Title', required: true })

    expect(wrapper.get('.wx-form-item__required').text()).toBe('*')
    expect(wrapper.classes()).toContain('is-required')
  })

  it('shows its own error and puts the control in the error state', () => {
    const wrapper = mountField(null, { label: 'Title', error: 'Required field' })

    expect(wrapper.get('.wx-form-item__error').text()).toBe('Required field')
    expect(wrapper.get('.wx-input').classes()).toContain('wx-input--error')
    expect(wrapper.get('input').attributes('aria-invalid')).toBe('true')
  })

  it('picks up a message from the form errors by name', () => {
    const wrapper = mountField(
      { errors: { email: ['The email field must be a valid email address.'] } },
      { label: 'Email', name: 'email' },
    )

    expect(wrapper.get('.wx-form-item__error').text()).toBe(
      'The email field must be a valid email address.',
    )
    expect(wrapper.get('.wx-input').classes()).toContain('wx-input--error')
  })

  it('ignores errors meant for another field', () => {
    const wrapper = mountField({ errors: { password: ['Required'] } }, { name: 'email' })

    expect(wrapper.find('.wx-form-item__error').exists()).toBe(false)
    expect(wrapper.get('.wx-input').classes()).not.toContain('wx-input--error')
  })

  it('shows the first message when the server sends several', () => {
    const wrapper = mountField(
      { errors: { email: ['Too short', 'Not an email'] } },
      { name: 'email' },
    )

    expect(wrapper.get('.wx-form-item__error').text()).toBe('Too short')
  })

  it('points aria-describedby at the error node', () => {
    const wrapper = mountField(null, { error: 'Required field' })
    const describedBy = wrapper.get('input').attributes('aria-describedby')

    expect(describedBy).toBeTruthy()
    expect(wrapper.get('.wx-form-item__error').attributes('id')).toBe(describedBy)
    expect(wrapper.get('.wx-form-item__error').attributes('role')).toBe('alert')
  })

  it('describes the control by the help text when there is no error', () => {
    const wrapper = mountField(null, { help: 'Shown in the page title' })
    const describedBy = wrapper.get('input').attributes('aria-describedby')

    expect(wrapper.get('.wx-form-item__help').attributes('id')).toBe(describedBy)
  })

  it('never points aria-describedby at a node that is not rendered', () => {
    const wrapper = mountField(null, { help: 'Shown in the page title', error: 'Required' })
    const ids = wrapper.get('input').attributes('aria-describedby')?.split(' ') ?? []

    expect(ids).toHaveLength(1)
    for (const id of ids) {
      expect(wrapper.find(`#${id}`).exists()).toBe(true)
    }
  })

  it('shows help text until an error replaces it', async () => {
    const wrapper = mountField(null, { help: 'Shown in the page title' })
    expect(wrapper.get('.wx-form-item__help').text()).toBe('Shown in the page title')

    const withError = mountField(null, { help: 'Shown in the page title', error: 'Required' })
    expect(withError.find('.wx-form-item__help').exists()).toBe(false)
    expect(withError.get('.wx-form-item__error').text()).toBe('Required')
    await Promise.resolve()
  })

  it('disables the control it wraps', () => {
    const wrapper = mountField(null, { disabled: true })

    expect(wrapper.get('input').attributes('disabled')).toBeDefined()
  })

  it('renders a span instead of a label for a group, and links it by aria-labelledby', async () => {
    const wrapper = mount(
      defineComponent({
        render: () =>
          h(
            WxFormItem,
            { label: 'Permissions' },
            {
              default: () =>
                h(WxCheckboxGroup, {
                  modelValue: [],
                  options: [{ label: 'Read', value: 'read' }],
                }),
            },
          ),
      }),
    )

    // The group registers itself during setup; the item re-renders on the next tick.
    await nextTick()

    expect(wrapper.find('label.wx-form-item__label').exists()).toBe(false)
    const labelId = wrapper.get('.wx-form-item__label').attributes('id')
    expect(wrapper.get('[role="group"]').attributes('aria-labelledby')).toBe(labelId)
  })
})
