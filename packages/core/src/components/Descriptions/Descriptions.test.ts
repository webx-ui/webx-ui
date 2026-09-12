import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxDescriptions from './Descriptions.vue'
import WxDescriptionsItem from '../DescriptionsItem/DescriptionsItem.vue'

const global = { components: { WxDescriptionsItem } }

const pairs = `
  <wx-descriptions-item label="Customer">Nova Build</wx-descriptions-item>
  <wx-descriptions-item label="Email">office@example.com</wx-descriptions-item>
  <wx-descriptions-item label="Note" :span="2">Lorem ipsum dolor sit amet</wx-descriptions-item>
`

function mountList(props: Record<string, unknown> = {}) {
  return mount(WxDescriptions, { props, slots: { default: pairs }, global })
}

describe('WxDescriptions', () => {
  it('renders every pair as a label and a value', () => {
    const wrapper = mountList()

    expect(wrapper.findAll('.wx-descriptions__label').map((el) => el.text())).toEqual([
      'Customer',
      'Email',
      'Note',
    ])
    expect(wrapper.findAll('.wx-descriptions__value')[0].text()).toBe('Nova Build')
  })

  it('puts the pairs in the grid itself, so labels share a column', () => {
    const wrapper = mountList()
    const list = wrapper.get('.wx-descriptions__list')

    // Three pairs, six grid items — not three boxes of two.
    expect(list.element.children).toHaveLength(6)
    expect(list.element.children[0].tagName).toBe('DT')
  })

  it('spans a pair across the columns it was given', () => {
    const wrapper = mountList()
    const values = wrapper.findAll('.wx-descriptions__value')

    // Beside its label, a pair is two tracks: spanning two columns is three of them.
    expect(values[0].attributes('style')).toContain('--wx-descriptions-span: 1')
    expect(values[2].attributes('style')).toContain('--wx-descriptions-span: 3')
  })

  it('never spans a pair past the columns the list has', () => {
    const wrapper = mountList({ columns: 1 })
    const values = wrapper.findAll('.wx-descriptions__value')

    // `:span="2"` in a one-column list is one column, or the grid it asks for does
    // not exist and every pair after it is placed off the end of the one that does.
    expect(values[2].attributes('style')).toContain('--wx-descriptions-span: 1')
  })

  it('stacks the label over the value when asked, as one grid item', () => {
    const wrapper = mountList({ layout: 'vertical' })
    const list = wrapper.get('.wx-descriptions__list')

    expect(list.element.children).toHaveLength(3)
    expect(wrapper.findAll('.wx-descriptions__pair')).toHaveLength(3)
    expect(wrapper.findAll('.wx-descriptions__pair')[2].attributes('style')).toContain(
      '--wx-descriptions-span: 2',
    )
  })

  it('carries the column count as a custom property', () => {
    expect(mountList({ columns: 3 }).attributes('style')).toContain('--wx-descriptions-columns: 3')
  })

  it('refuses to be narrower than one column', () => {
    expect(mountList({ columns: 0 }).attributes('style')).toContain('--wx-descriptions-columns: 1')
  })

  it('shows a heading and whatever sits beside it', () => {
    const wrapper = mount(WxDescriptions, {
      props: { title: 'Order WX-4100' },
      slots: { default: pairs, extra: '<button>Edit</button>' },
      global,
    })

    expect(wrapper.get('.wx-descriptions__title').text()).toBe('Order WX-4100')
    expect(wrapper.get('.wx-descriptions__extra button').text()).toBe('Edit')
  })

  it('has no header at all without one', () => {
    expect(mountList().find('.wx-descriptions__header').exists()).toBe(false)
  })

  it('takes a border and a label width', () => {
    const wrapper = mountList({ bordered: true, labelWidth: '140px' })

    expect(wrapper.classes()).toContain('wx-descriptions--bordered')
    expect(wrapper.attributes('style')).toContain('--wx-descriptions-label: 140px')
  })
})
