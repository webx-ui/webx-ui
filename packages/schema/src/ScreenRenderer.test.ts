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
            { id: 'one', type: 'wx-tab', label: 'One', children: [] },
            { id: 'two', type: 'wx-tab', label: 'Two', children: [] },
          ],
        },
      ],
    })

    expect(wrapper.findAll('[role="tab"]').map((tab) => tab.text())).toEqual(['One', 'Two'])
  })
})
