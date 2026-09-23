import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h, nextTick } from 'vue'
import WxScreenRenderer from './ScreenRenderer.vue'
import type { Patch, ScreenNode } from './types'

const root: ScreenNode[] = [
  {
    id: 'card',
    type: 'wx-card',
    label: 'trans::demo::card',
    children: [
      { id: 'name', type: 'wx-input', name: 'name', label: 'Name', help: 'Public' },
      { id: 'indexing', type: 'wx-switch', name: 'indexing', label: 'Index' },
      {
        id: 'sitemap',
        type: 'wx-input',
        name: 'sitemap',
        label: 'Sitemap',
        visible: { when: 'indexing', is: true },
      },
      { id: 'secret', type: 'wx-input', name: 'secret', label: 'Secret', can: 'settings.manage' },
      { id: 'note', type: 'wx-text', label: 'A note' },
      { id: 'map', type: 'map', name: 'map' },
    ],
  },
]

function mountScreen(props: Record<string, unknown> = {}) {
  return mount(WxScreenRenderer, {
    props: { root, modelValue: {}, ...props },
    attachTo: document.body,
  })
}

describe('WxScreenRenderer', () => {
  it('marks a column so the fields in it stack with the form step, keeping its own class', () => {
    const wrapper = mountScreen({
      root: [
        {
          id: 'row',
          type: 'wx-row',
          children: [
            {
              id: 'col',
              type: 'wx-col',
              props: { md: 12, class: 'mine' },
              children: [
                { id: 'a', type: 'wx-input', name: 'a', label: 'A' },
                { id: 'b', type: 'wx-input', name: 'b', label: 'B' },
              ],
            },
          ],
        },
      ],
    })

    const col = wrapper.get('.wx-col')
    expect(col.classes()).toEqual(expect.arrayContaining(['wx-screen__col', 'mine']))
    expect(col.findAll('.wx-form-item')).toHaveLength(2)
    wrapper.unmount()
  })

  it('draws layout, fields and display nodes from the registry', () => {
    const wrapper = mountScreen({ modelValue: { name: 'Acme' } })

    expect(wrapper.find('.wx-card').exists()).toBe(true)
    expect(wrapper.text()).toContain('Name')
    expect(wrapper.text()).toContain('Public')
    expect(wrapper.text()).toContain('A note')
    expect((wrapper.find('input[name="name"]').element as HTMLInputElement).value).toBe('Acme')
  })

  it('writes a field back into the model without mutating the old one', async () => {
    const before = { name: 'Acme' }
    const wrapper = mountScreen({ modelValue: before })
    await wrapper.find('input[name="name"]').setValue('Globex')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([{ name: 'Globex' }])
    expect(before).toEqual({ name: 'Acme' })
  })

  it('hides a node whose condition is false and shows it once it holds', async () => {
    const wrapper = mountScreen({ modelValue: { indexing: false } })
    expect(wrapper.find('input[name="sitemap"]').exists()).toBe(false)

    await wrapper.setProps({ modelValue: { indexing: true } })
    expect(wrapper.find('input[name="sitemap"]').exists()).toBe(true)
  })

  it('hides a node the permission check refuses', () => {
    expect(mountScreen().find('input[name="secret"]').exists()).toBe(true)
    expect(
      mountScreen({ can: (permission: string) => permission !== 'settings.manage' })
        .find('input[name="secret"]')
        .exists(),
    ).toBe(false)
  })

  it('draws a loud placeholder for a type the registry does not know', () => {
    const wrapper = mountScreen()

    expect(wrapper.find('.wx-screen__unknown').text()).toBe('Unknown type: map')
  })

  it('takes project types over the core ones', () => {
    const Map = defineComponent({
      props: { modelValue: { type: String, default: '' } },
      setup: (props) => () => h('div', { class: 'my-map' }, props.modelValue),
    })
    const wrapper = mountScreen({
      modelValue: { map: '50.4,30.5' },
      types: { map: { component: Map, kind: 'field' } },
    })

    expect(wrapper.find('.my-map').text()).toBe('50.4,30.5')
    expect(wrapper.find('.wx-screen__unknown').exists()).toBe(false)
  })

  it('translates marked strings and leaves the rest alone', () => {
    const translate = vi.fn((key: string) => (key === 'demo::card' ? 'The card' : key))
    const wrapper = mountScreen({ translate })

    expect(wrapper.text()).toContain('The card')
    expect(wrapper.text()).toContain('Name')
    expect(translate).toHaveBeenCalledWith('demo::card')
  })

  it('shows the key when there is no dictionary', () => {
    expect(mountScreen().text()).toContain('demo::card')
  })

  it('applies a patch on top of the tree and reports what failed', async () => {
    const patch: Patch = [
      { op: 'remove', target: 'note' },
      { op: 'set', target: 'name', label: 'Company' },
      { op: 'remove', target: 'ghost' },
    ]
    const error = vi.spyOn(console, 'error').mockImplementation(() => {})
    const wrapper = mountScreen({ patch })
    await nextTick()

    expect(wrapper.text()).not.toContain('A note')
    expect(wrapper.text()).toContain('Company')
    const reported = wrapper.emitted('patchError')?.at(-1)?.[0] as { message: string }[]
    expect(reported).toHaveLength(1)
    expect(reported[0]?.message).toBe('target "ghost" not found')
    expect(error).toHaveBeenCalledOnce()
    error.mockRestore()
  })

  it('shows server errors under the field they name', () => {
    const wrapper = mountScreen({ errors: { name: ['Taken'] } })

    expect(wrapper.text()).toContain('Taken')
  })

  it('renders tabs from the tree', () => {
    const wrapper = mountScreen({
      root: [
        {
          id: 'tabs',
          type: 'wx-tabs',
          children: [
            { id: 'one', type: 'wx-tab', label: 'One', children: [{ id: 'x', type: 'wx-text' }] },
            { id: 'two', type: 'wx-tab', label: 'Two', children: [{ id: 'y', type: 'wx-text' }] },
          ],
        },
      ],
    })

    expect(wrapper.findAll('[role="tab"]').map((tab) => tab.text())).toEqual(['One', 'Two'])
  })

  it('draws every form control of the core with the value it holds', () => {
    const options = [
      { label: 'Left', value: 'left' },
      { label: 'Right', value: 'right' },
    ]
    const fields: ScreenNode[] = [
      { id: 'a', type: 'wx-checkbox-group', name: 'a', label: 'Group', props: { options } },
      { id: 'b', type: 'wx-segmented', name: 'b', label: 'Segmented', props: { options } },
      { id: 'c', type: 'wx-slider', name: 'c', label: 'Slider' },
      { id: 'd', type: 'wx-rate', name: 'd', label: 'Rate' },
      { id: 'e', type: 'wx-time-picker', name: 'e', label: 'Time' },
      { id: 'f', type: 'wx-date-time-picker', name: 'f', label: 'Moment' },
      { id: 'g', type: 'wx-date-range-picker', name: 'g', label: 'Range' },
      { id: 'h', type: 'wx-tags-input', name: 'h', label: 'Tags' },
      { id: 'i', type: 'wx-autocomplete', name: 'i', label: 'City' },
      { id: 'j', type: 'wx-icon-picker', name: 'j', label: 'Icon' },
      { id: 'k', type: 'wx-code-editor', name: 'k', label: 'Code' },
      { id: 'l', type: 'wx-cascader', name: 'l', label: 'Section', props: { options } },
      {
        id: 'm',
        type: 'wx-tree-select',
        name: 'm',
        label: 'Part',
        props: { nodes: [{ id: 1, label: 'Engine' }] },
      },
      {
        id: 'n',
        type: 'wx-transfer',
        name: 'n',
        label: 'Team',
        props: { items: [{ value: 'ann', label: 'Ann' }] },
      },
    ]
    const wrapper = mountScreen({
      root: [{ id: 'head', type: 'wx-heading', label: 'Event' }, ...fields],
      modelValue: { a: ['left'], b: 'right', c: 40, d: 3, h: ['jazz'], i: 'Lviv', n: ['ann'] },
    })

    expect(wrapper.find('.wx-screen__unknown').exists()).toBe(false)
    expect(wrapper.get('.wx-heading').text()).toBe('Event')
    for (const field of fields) expect(wrapper.text()).toContain(field.label as string)
    expect(wrapper.findAllComponents({ name: 'WxFormItem' })).toHaveLength(fields.length)

    expect(wrapper.findComponent({ name: 'WxCheckboxGroup' }).props('modelValue')).toEqual(['left'])
    expect(wrapper.findComponent({ name: 'WxSlider' }).props('modelValue')).toBe(40)
    expect(wrapper.findComponent({ name: 'WxTagsInput' }).text()).toContain('jazz')
    // The server keeps a moment with its offset, so the picker must write one.
    expect(wrapper.findComponent({ name: 'WxDateTimePicker' }).props('valueFormat')).toBe(
      "yyyy-MM-dd'T'HH:mm:ssXXX",
    )
  })

  it('draws no container that has nothing to show, and keeps one described without children', () => {
    const wrapper = mountScreen({
      root: [
        { id: 'project-fields', type: 'wx-card', label: 'More', children: [] },
        {
          id: 'guarded',
          type: 'wx-card',
          label: 'Guarded',
          children: [{ id: 'x', type: 'wx-input', name: 'x', label: 'X', can: 'nobody' }],
        },
        { id: 'plain', type: 'wx-card', label: 'Plain' },
      ],
      can: () => false,
    })

    expect(wrapper.text()).not.toContain('More')
    expect(wrapper.text()).not.toContain('Guarded')
    expect(wrapper.text()).toContain('Plain')
  })

  it('opens the tab a refused field is on, unless the one on screen has its own', async () => {
    const tabs: ScreenNode[] = [
      {
        id: 'tabs',
        type: 'wx-tabs',
        children: [
          {
            id: 'one',
            type: 'wx-tab',
            label: 'One',
            children: [{ id: 'a', type: 'wx-input', name: 'a', label: 'A' }],
          },
          {
            id: 'two',
            type: 'wx-tab',
            label: 'Two',
            children: [{ id: 'b', type: 'wx-input', name: 'b', label: 'B' }],
          },
        ],
      },
    ]
    const wrapper = mountScreen({ root: tabs })
    const selected = () =>
      wrapper
        .findAll('.wx-tabs__tab')
        .find((tab) => tab.attributes('aria-selected') === 'true')
        ?.text()

    await wrapper.setProps({ errors: { 'b.en': ['Wrong.'] } })
    await nextTick()
    expect(selected()).toBe('Two')
    // Laravel names the language that failed; the message still goes under the field.
    await nextTick()
    expect(wrapper.text()).toContain('Wrong.')

    await wrapper.setProps({ errors: { a: ['Wrong.'], b: ['Wrong.'] } })
    await nextTick()
    expect(selected()).toBe('Two')
  })
})
