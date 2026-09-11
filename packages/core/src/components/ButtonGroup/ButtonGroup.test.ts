import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxButton from '../Button/Button.vue'
import WxButtonGroup from './ButtonGroup.vue'

function group(props: Record<string, unknown> = {}, buttons = '<wx-button>A</wx-button>') {
  return mount(WxButtonGroup, {
    props,
    slots: { default: buttons },
    global: { components: { WxButton } },
  })
}

describe('WxButtonGroup', () => {
  it('is a labelled group of buttons', () => {
    const wrapper = group({ ariaLabel: 'Row actions' })

    expect(wrapper.attributes('role')).toBe('group')
    expect(wrapper.attributes('aria-label')).toBe('Row actions')
    expect(wrapper.classes()).toContain('wx-button-group--attached')
  })

  it('hands its look down to the buttons', () => {
    const wrapper = group({ type: 'primary', variant: 'outline', size: 'sm' })
    const button = wrapper.get('.wx-button')

    expect(button.classes()).toEqual(
      expect.arrayContaining(['wx-button--primary', 'wx-button--outline', 'wx-button--sm']),
    )
  })

  it("lets a button's own props win over the group's", () => {
    const wrapper = group(
      { type: 'primary', size: 'sm' },
      '<wx-button type="danger">Delete</wx-button>',
    )
    const button = wrapper.get('.wx-button')

    expect(button.classes()).toContain('wx-button--danger')
    expect(button.classes()).not.toContain('wx-button--primary')
    expect(button.classes()).toContain('wx-button--sm')
  })

  it('disables every button in the group', () => {
    const wrapper = group({ disabled: true }, '<wx-button>A</wx-button><wx-button>B</wx-button>')

    for (const button of wrapper.findAll('button.wx-button')) {
      expect(button.classes()).toContain('is-disabled')
      expect(button.attributes('disabled')).toBeDefined()
    }
  })

  it('stacks and separates on request', () => {
    const wrapper = group({ vertical: true, attached: false })

    expect(wrapper.classes()).toContain('wx-button-group--vertical')
    expect(wrapper.classes()).not.toContain('wx-button-group--attached')
  })

  it('leaves a lone button untouched', () => {
    const wrapper = mount(WxButton, { slots: { default: 'Save' } })

    expect(wrapper.classes()).toEqual(
      expect.arrayContaining(['wx-button--default', 'wx-button--solid', 'wx-button--md']),
    )
  })
})
