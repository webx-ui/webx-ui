import { afterEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { markRaw, nextTick } from 'vue'
import WxTreeSelect from './TreeSelect.vue'
import type { TreeNode } from '../Tree/types'

// The panel is teleported, so it would outlive its wrapper and leak into the next test.
enableAutoUnmount(afterEach)

const nodes: TreeNode[] = [
  {
    id: 1,
    label: 'Engine',
    children: [
      { id: 11, label: 'Pistons' },
      { id: 12, label: 'Bearings' },
    ],
  },
  { id: 2, label: 'Brakes', children: [{ id: 21, label: 'Pads' }] },
  { id: 3, label: 'Locked', disabled: true },
]

function factory(props: Record<string, unknown> = {}) {
  return mount(WxTreeSelect, { props: { nodes, ...props }, attachTo: document.body })
}

const rows = () => [...document.querySelectorAll('.wx-tree__row')]

const rowFor = (label: string) => rows().find((row) => row.textContent?.trim().startsWith(label))!

async function openPanel(wrapper: ReturnType<typeof factory>) {
  await wrapper.get('.wx-tree-select__trigger').trigger('click')
  await nextTick()
}

async function click(element: Element) {
  element.dispatchEvent(new MouseEvent('click', { bubbles: true }))
  await nextTick()
}

describe('WxTreeSelect', () => {
  it('shows the placeholder until something is chosen', () => {
    const wrapper = factory({ placeholder: 'Pick a category' })

    expect(wrapper.get('.wx-tree-select__placeholder').text()).toBe('Pick a category')
  })

  it('opens a tree of the nodes it was given', async () => {
    const wrapper = factory()

    await openPanel(wrapper)

    expect(rows().length).toBeGreaterThan(0)
    expect(rowFor('Engine')).toBeTruthy()
  })

  /* -------------------------------------------------------------- single */

  it('takes the node that was clicked and closes', async () => {
    const wrapper = factory({ defaultExpandAll: true })

    await openPanel(wrapper)
    await click(rowFor('Pistons'))

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([11])

    const change = wrapper.emitted('change')?.at(-1)
    expect(change?.[0]).toBe(11)
    expect((change?.[1] as TreeNode[])[0]?.label).toBe('Pistons')

    await flushPromises()
    expect(document.querySelector('.wx-tree-select__panel')).toBeNull()
  })

  it('shows the label of what is chosen', async () => {
    const wrapper = factory({ modelValue: 11 })

    expect(wrapper.get('.wx-tree-select__single').text()).toBe('Pistons')
  })

  it('shows the whole path with show-path', () => {
    const wrapper = factory({ modelValue: 11, showPath: true })

    expect(wrapper.get('.wx-tree-select__single').text()).toBe('Engine / Pistons')
  })

  it('opens the branches leading to what is already chosen', async () => {
    const wrapper = factory({ modelValue: 11 })

    await openPanel(wrapper)

    expect(rowFor('Pistons')).toBeTruthy()
  })

  /* ------------------------------------------------------------ multiple */

  it('collects keys and draws a tag per node', async () => {
    const wrapper = factory({ multiple: true, modelValue: [], defaultExpandAll: true })

    await openPanel(wrapper)
    await rowFor('Pads').querySelector<HTMLInputElement>('input[type="checkbox"]')!.click()
    await nextTick()

    const chosen = wrapper.emitted('update:modelValue')?.at(-1)?.[0] as number[]
    expect(chosen).toContain(21)

    await wrapper.setProps({ modelValue: chosen })
    expect(wrapper.findAll('.wx-tree-select__tag')).toHaveLength(chosen.length)
    expect(document.querySelector('.wx-tree-select__panel')).not.toBeNull()
  })

  it('drops one node when its tag is dismissed', async () => {
    const wrapper = factory({ multiple: true, modelValue: [11, 12] })

    expect(wrapper.findAll('.wx-tree-select__tag')).toHaveLength(2)

    await wrapper.findAll('.wx-tree-select__tag-remove')[0]!.trigger('click')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([[12]])
  })

  it('sends a tick down the branch unless check-strictly says otherwise', async () => {
    const strict = factory({
      multiple: true,
      checkStrictly: true,
      modelValue: [],
      defaultExpandAll: true,
    })

    await openPanel(strict)
    await rowFor('Engine').querySelector<HTMLInputElement>('input[type="checkbox"]')!.click()
    await nextTick()

    expect(strict.emitted('update:modelValue')?.at(-1)?.[0]).toEqual([1])
  })

  /* --------------------------------------------------------------- field */

  it('empties the selection with the clear button', async () => {
    const wrapper = factory({ modelValue: 11, clearable: true })

    await wrapper.get('.wx-tree-select__clear').trigger('click')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([null])
    expect(wrapper.emitted('clear')).toHaveLength(1)
  })

  it('clears a multiple field to an empty array, not to nothing', async () => {
    const wrapper = factory({ multiple: true, modelValue: [11], clearable: true })

    await wrapper.get('.wx-tree-select__clear').trigger('click')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([[]])
  })

  it('filters the tree from the search field', async () => {
    const wrapper = factory({ filterable: true })

    await openPanel(wrapper)
    const search = document.querySelector<HTMLInputElement>('.wx-tree-select__search-input')!
    search.value = 'pist'
    search.dispatchEvent(new Event('input'))
    await nextTick()

    expect(rows().map((row) => row.textContent?.trim())).toEqual(['Engine', 'Pistons'])
  })

  it('hands the first arrow to the tree, which is what listens for the rest', async () => {
    const wrapper = factory({ modelValue: 11 })

    await openPanel(wrapper)
    document
      .querySelector('.wx-tree-select__panel')!
      .dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }))
    await nextTick()

    expect(document.activeElement?.textContent).toContain('Pistons')
  })

  it('carries the keys for a plain form post', () => {
    const wrapper = factory({ multiple: true, modelValue: [11, 12], name: 'categories' })

    expect(wrapper.get('input[type="hidden"]').attributes('value')).toBe('11,12')
  })

  it('cannot be opened when disabled', async () => {
    const wrapper = factory({ disabled: true })

    expect(wrapper.get('.wx-tree-select__trigger').attributes('disabled')).toBeDefined()
  })

  it('names a value the tree has not fetched, from the path it was given', () => {
    const wrapper = factory({
      nodes: [{ id: 'ua', label: 'Ukraine' }],
      modelValue: 'ua-kyiv',
      selectedPath: [
        { id: 'ua', label: 'Ukraine' },
        { id: 'ua-kyiv', label: 'Kyiv' },
      ],
      showPath: true,
      lazy: true,
      load: vi.fn(),
    })

    expect(wrapper.get('.wx-tree-select__single').text()).toBe('Ukraine / Kyiv')
  })

  it('shows the key itself rather than pretending a value is not there', () => {
    const wrapper = factory({ nodes: [], modelValue: 42, placeholder: 'Pick' })

    expect(wrapper.find('.wx-tree-select__placeholder').exists()).toBe(false)
    expect(wrapper.get('.wx-tree-select__single').text()).toBe('42')
  })

  it('fetches and opens the branch its value sits in', async () => {
    const load = vi.fn().mockResolvedValue([{ id: 'ua-kyiv', label: 'Kyiv', leaf: true }])
    const wrapper = factory({
      nodes: [{ id: 'ua', label: 'Ukraine' }],
      modelValue: 'ua-kyiv',
      selectedPath: [
        { id: 'ua', label: 'Ukraine' },
        { id: 'ua-kyiv', label: 'Kyiv' },
      ],
      lazy: true,
      load,
    })

    await openPanel(wrapper)
    await flushPromises()

    expect(load).toHaveBeenCalledTimes(1)
    expect(rowFor('Kyiv')).toBeTruthy()
    expect(rowFor('Kyiv').classList.contains('is-selected')).toBe(true)
  })

  it('names a node picked out of a branch it has just fetched', async () => {
    const load = vi.fn().mockResolvedValue([
      { id: 'ua-kyiv', label: 'Kyiv', leaf: true },
      { id: 'ua-odesa', label: 'Odesa', leaf: true },
    ])
    /*
     * The field keeps one index to name its value and the tree in the panel keeps
     * another, and it is the tree that fetches. This covers the chain that finds the
     * label — and not the reason it once broke: in a browser the field's index stayed
     * stale, and here it does not, whatever `markRaw` is asked to withhold. jsdom
     * refreshes too much to be able to fail at it, so that half is a browser's to check.
     */
    const wrapper = factory({
      nodes: [markRaw({ id: 'ua', label: 'Ukraine' })],
      lazy: true,
      load,
    })

    await openPanel(wrapper)
    await click(rowFor('Ukraine').querySelector('.wx-tree__toggle')!)
    await flushPromises()
    await click(rowFor('Odesa'))

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['ua-odesa'])

    /*
     * No `setProps` here on purpose: handing the props object back rebuilds every index
     * over it, and that is exactly the help the field does not get in an application.
     */
    await nextTick()
    expect(wrapper.get('.wx-tree-select__single').text()).toBe('Odesa')
    expect((wrapper.emitted('change')?.at(-1)?.[1] as TreeNode[])[0]?.label).toBe('Odesa')
  })

  it('takes a path per value when several are chosen', () => {
    const wrapper = factory({
      nodes: [],
      multiple: true,
      modelValue: ['ua-kyiv', 'pl-warsaw'],
      selectedPath: [
        [
          { id: 'ua', label: 'Ukraine' },
          { id: 'ua-kyiv', label: 'Kyiv' },
        ],
        [
          { id: 'pl', label: 'Poland' },
          { id: 'pl-warsaw', label: 'Warsaw' },
        ],
      ],
      lazy: true,
      load: vi.fn(),
    })

    expect(wrapper.findAll('.wx-tree-select__tag').map((tag) => tag.text())).toEqual([
      'Kyiv',
      'Warsaw',
    ])
  })

  it('fetches a branch through the tree it holds', async () => {
    const load = vi.fn().mockResolvedValue([{ id: 31, label: 'Rotors' }])
    const wrapper = factory({ nodes: [{ id: 3, label: 'Discs' }], lazy: true, load })

    await openPanel(wrapper)
    await click(rowFor('Discs').querySelector('.wx-tree__toggle')!)
    await new Promise((resolve) => setTimeout(resolve))

    expect(load).toHaveBeenCalledTimes(1)
    expect(rowFor('Rotors')).toBeTruthy()
  })
})
