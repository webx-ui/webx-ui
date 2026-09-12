import { describe, expect, it, vi } from 'vitest'
import { mount, type VueWrapper } from '@vue/test-utils'
import { markRaw } from 'vue'
import WxTree from './Tree.vue'
import type { TreeNode } from './types'

function catalog(): TreeNode[] {
  return [
    {
      id: 1,
      label: 'Catalog',
      children: [
        { id: 11, label: 'Engine', children: [{ id: 111, label: 'Pistons' }] },
        { id: 12, label: 'Brakes' },
      ],
    },
    { id: 2, label: 'Pages' },
  ]
}

function tree(props: Record<string, unknown> = {}, slots: Record<string, string> = {}) {
  const held: { wrapper?: VueWrapper } = {}

  const merged: Record<string, unknown> = {
    /* Held the way a parent holds it, so a second move builds on the first. */
    modelValue: catalog(),
    'onUpdate:modelValue': (value: unknown) => held.wrapper?.setProps({ modelValue: value }),
    expanded: [1],
    'onUpdate:expanded': (value: unknown) => held.wrapper?.setProps({ expanded: value }),
    ...props,
  }

  /* `expanded: undefined` means "nobody is holding this one" — the tree keeps it itself. */
  if (merged.expanded === undefined) {
    delete merged.expanded
    delete merged['onUpdate:expanded']
  }

  const wrapper = mount(WxTree, { attachTo: document.body, props: merged, slots })

  held.wrapper = wrapper
  return wrapper
}

const labels = (wrapper: VueWrapper) => wrapper.findAll('.wx-tree__label').map((row) => row.text())

const row = (wrapper: VueWrapper, label: string) =>
  wrapper.findAll('.wx-tree__row').find((item) => item.text().startsWith(label))!

/** A drag event jsdom can carry: `DataTransfer` does not exist there. */
const dataTransfer = () => ({ setData: vi.fn(), dropEffect: '', effectAllowed: '' })

async function drag(wrapper: VueWrapper, from: string, to: string) {
  await row(wrapper, from).trigger('dragstart', { dataTransfer: dataTransfer() })
  await row(wrapper, to).trigger('dragover', { dataTransfer: dataTransfer() })
  await row(wrapper, to).trigger('drop')
}

describe('WxTree', () => {
  it('draws the open branches and leaves the closed ones out', () => {
    const wrapper = tree()

    expect(labels(wrapper)).toEqual(['Catalog', 'Engine', 'Brakes', 'Pages'])
  })

  it('opens every branch with default-expand-all', () => {
    const wrapper = tree({ defaultExpandAll: true, expanded: undefined })

    expect(labels(wrapper)).toContain('Pistons')
  })

  it('opens and closes a branch, and says which way it went', async () => {
    const wrapper = tree()

    await row(wrapper, 'Engine').get('.wx-tree__toggle').trigger('click')
    expect(labels(wrapper)).toContain('Pistons')
    expect(wrapper.emitted('expand')?.[0]).toBeTruthy()

    await row(wrapper, 'Engine').get('.wx-tree__toggle').trigger('click')
    expect(labels(wrapper)).not.toContain('Pistons')
    expect(wrapper.emitted('collapse')?.[0]).toBeTruthy()
  })

  it('selects a node on click', async () => {
    const wrapper = tree()

    await row(wrapper, 'Brakes').trigger('click')

    expect(wrapper.emitted('select')?.[0]?.[1]).toBe(12)
    expect(wrapper.emitted('update:selected')?.[0]).toEqual([12])
    expect(row(wrapper, 'Brakes').classes()).toContain('is-selected')
  })

  it('leaves a disabled node alone', async () => {
    const wrapper = tree({ modelValue: [{ id: 1, label: 'Catalog', disabled: true }] })

    await row(wrapper, 'Catalog').trigger('click')

    expect(wrapper.emitted('select')).toBeUndefined()
    expect(wrapper.emitted('node-click')).toHaveLength(1)
  })

  it('reads the fields the caller names', () => {
    const wrapper = tree({
      nodeKey: 'slug',
      labelKey: 'title',
      childrenKey: 'items',
      modelValue: [
        { slug: 'catalog', title: 'Каталог', items: [{ slug: 'engine', title: 'Двигун' }] },
      ],
      expanded: ['catalog'],
    })

    expect(labels(wrapper)).toEqual(['Каталог', 'Двигун'])
  })

  /* ------------------------------------------------------------- checking */

  it('checks a branch and everything under it, and the parent that is now whole', async () => {
    const wrapper = tree({ checkable: true, defaultExpandAll: true, expanded: undefined })

    await row(wrapper, 'Engine').get('input[type="checkbox"]').setValue(true)

    let checked = wrapper.emitted('update:checked')?.at(-1)?.[0] as number[]
    expect(checked).toEqual(expect.arrayContaining([11, 111]))
    expect(checked).not.toContain(1)

    await wrapper.setProps({ checked })
    await row(wrapper, 'Brakes').get('input[type="checkbox"]').setValue(true)

    checked = wrapper.emitted('update:checked')?.at(-1)?.[0] as number[]
    // Both children are checked, so Catalog is no longer half of anything.
    expect(checked).toContain(1)
  })

  it('shows a branch with some of its children checked as half-checked', async () => {
    const wrapper = tree({
      checkable: true,
      checked: [111],
      defaultExpandAll: true,
      expanded: undefined,
    })

    expect(row(wrapper, 'Engine').get('.wx-checkbox').classes()).toContain('is-indeterminate')
  })

  it('leaves the children alone with check-strictly', async () => {
    const wrapper = tree({
      checkable: true,
      checkStrictly: true,
      defaultExpandAll: true,
      expanded: undefined,
    })

    await row(wrapper, 'Engine').get('input[type="checkbox"]').setValue(true)

    expect(wrapper.emitted('update:checked')?.at(-1)?.[0]).toEqual([11])
  })

  /* -------------------------------------------------------------- filter */

  it('keeps the matches and the branches that lead to them', () => {
    const wrapper = tree({ filter: 'pist', expanded: [] })

    expect(labels(wrapper)).toEqual(['Catalog', 'Engine', 'Pistons'])
    expect(row(wrapper, 'Pistons').get('.wx-tree__hit').text()).toBe('Pist')
  })

  it('draws nothing but the empty slot when the filter matches nothing', () => {
    const wrapper = tree({ filter: 'zzz' })

    expect(wrapper.find('.wx-tree__empty').exists()).toBe(true)
  })

  /* ---------------------------------------------------------------- lazy */

  it('fetches the children of a branch the first time it opens', async () => {
    const load = vi.fn().mockResolvedValue([{ id: 21, label: 'Contacts' }])
    const wrapper = tree({
      lazy: true,
      load,
      modelValue: [{ id: 2, label: 'Pages' }],
      expanded: [],
    })

    await row(wrapper, 'Pages').get('.wx-tree__toggle').trigger('click')
    await new Promise((resolve) => setTimeout(resolve))

    expect(load).toHaveBeenCalledTimes(1)
    expect(labels(wrapper)).toContain('Contacts')

    await row(wrapper, 'Pages').get('.wx-tree__toggle').trigger('click')
    await row(wrapper, 'Pages').get('.wx-tree__toggle').trigger('click')
    expect(load).toHaveBeenCalledTimes(1)
  })

  it('shows a fetched branch even when the tree it was handed is not reactive', async () => {
    const load = vi.fn().mockResolvedValue([{ id: 21, label: 'Contacts' }])
    /*
     * `markRaw` is what a plain array amounts to once it arrives through a prop:
     * nothing in it is a proxy, so writing the children into a node notifies nobody.
     * The browser showed this as a branch that opened empty and filled on the second
     * try; jsdom had hidden it, because the test harness makes its props reactive.
     */
    const wrapper = tree({
      lazy: true,
      load,
      modelValue: [markRaw({ id: 2, label: 'Pages' })],
      expanded: [],
    })

    await row(wrapper, 'Pages').get('.wx-tree__toggle').trigger('click')
    await new Promise((resolve) => setTimeout(resolve))

    expect(labels(wrapper)).toContain('Contacts')
  })

  it('has nothing to open on a node marked as a leaf', () => {
    const wrapper = tree({
      lazy: true,
      load: vi.fn(),
      modelValue: [{ id: 2, label: 'Pages', leaf: true }],
    })

    expect(row(wrapper, 'Pages').get('.wx-tree__toggle').classes()).toContain('is-leaf')
  })

  /* ------------------------------------------------------------- dragging */

  it('drops a node inside another and reports where it landed', async () => {
    const wrapper = tree({ draggable: true })

    await drag(wrapper, 'Brakes', 'Pages')

    const event = wrapper.emitted('drop')?.[0]?.[0] as Record<string, unknown>
    expect(event).toMatchObject({ zone: 'inside', index: 0, via: 'pointer' })
    expect((event.node as TreeNode).id).toBe(12)
    expect((event.parent as TreeNode).id).toBe(2)
    expect(labels(wrapper)).toEqual(['Catalog', 'Engine', 'Pages', 'Brakes'])
  })

  it('refuses to drop a branch inside its own subtree', async () => {
    const wrapper = tree({ draggable: true })

    await drag(wrapper, 'Catalog', 'Engine')

    expect(wrapper.emitted('drop')).toBeUndefined()
    expect(labels(wrapper)).toEqual(['Catalog', 'Engine', 'Brakes', 'Pages'])
  })

  it('asks allow-drop before it moves anything', async () => {
    const allowDrop = vi.fn().mockReturnValue(false)
    const wrapper = tree({ draggable: true, allowDrop })

    await drag(wrapper, 'Brakes', 'Pages')

    expect(allowDrop).toHaveBeenCalled()
    expect(wrapper.emitted('drop')).toBeUndefined()
  })

  it('does not pick up a node allow-drag turned down', async () => {
    const wrapper = tree({ draggable: true, allowDrag: (node: TreeNode) => node.id !== 12 })

    expect(row(wrapper, 'Brakes').attributes('draggable')).toBe('false')
    expect(row(wrapper, 'Pages').attributes('draggable')).toBe('true')
  })

  /* --------------------------------------------------------------- spring */

  it('opens a closed branch a node is held over', async () => {
    vi.useFakeTimers()
    const wrapper = tree({ draggable: true, springDelay: 400 })

    await row(wrapper, 'Pages').trigger('dragstart', { dataTransfer: dataTransfer() })
    await row(wrapper, 'Engine').trigger('dragover', { dataTransfer: dataTransfer() })

    expect(labels(wrapper)).not.toContain('Pistons')

    await vi.advanceTimersByTimeAsync(400)

    expect(labels(wrapper)).toContain('Pistons')
    expect(wrapper.emitted('expand')).toHaveLength(1)
    vi.useRealTimers()
  })

  it('holds the branch closed when the spring is switched off', async () => {
    vi.useFakeTimers()
    const wrapper = tree({ draggable: true, springDelay: 0 })

    await row(wrapper, 'Pages').trigger('dragstart', { dataTransfer: dataTransfer() })
    await row(wrapper, 'Engine').trigger('dragover', { dataTransfer: dataTransfer() })
    await vi.advanceTimersByTimeAsync(2000)

    expect(labels(wrapper)).not.toContain('Pistons')
    vi.useRealTimers()
  })

  it('leaves the branch closed when the drag ends before the spring fires', async () => {
    vi.useFakeTimers()
    const wrapper = tree({ draggable: true, springDelay: 400 })

    await row(wrapper, 'Pages').trigger('dragstart', { dataTransfer: dataTransfer() })
    await row(wrapper, 'Engine').trigger('dragover', { dataTransfer: dataTransfer() })
    await row(wrapper, 'Engine').trigger('dragend')
    await vi.advanceTimersByTimeAsync(2000)

    expect(labels(wrapper)).not.toContain('Pistons')
    vi.useRealTimers()
  })

  /* ------------------------------------------------------------- keyboard */

  it('walks the rows with the arrow keys', async () => {
    const wrapper = tree()

    await row(wrapper, 'Catalog').trigger('keydown', { key: 'ArrowDown' })
    await wrapper.vm.$nextTick()

    expect(document.activeElement?.textContent).toContain('Engine')
  })

  it('opens a branch with ArrowRight and closes it with ArrowLeft', async () => {
    const wrapper = tree()

    await row(wrapper, 'Engine').trigger('keydown', { key: 'ArrowRight' })
    expect(labels(wrapper)).toContain('Pistons')

    await row(wrapper, 'Engine').trigger('keydown', { key: 'ArrowLeft' })
    expect(labels(wrapper)).not.toContain('Pistons')
  })

  it('moves a node among its siblings with Alt and the arrow keys', async () => {
    const wrapper = tree({ draggable: true })

    await row(wrapper, 'Brakes').trigger('keydown', { key: 'ArrowUp', altKey: true })

    expect(labels(wrapper)).toEqual(['Catalog', 'Brakes', 'Engine', 'Pages'])
    expect(wrapper.emitted('drop')?.[0]?.[0]).toMatchObject({ zone: 'before', via: 'keyboard' })
    expect(wrapper.get('.wx-tree__live').text()).toContain('Brakes is now 1 of 2')
  })

  it('nests a node under the one above it with Alt+ArrowRight', async () => {
    const wrapper = tree({ draggable: true })

    await row(wrapper, 'Brakes').trigger('keydown', { key: 'ArrowRight', altKey: true })

    expect(wrapper.emitted('drop')?.[0]?.[0]).toMatchObject({ zone: 'inside', via: 'keyboard' })
    expect(labels(wrapper)).toEqual(['Catalog', 'Engine', 'Pistons', 'Brakes', 'Pages'])
  })

  it('lifts a node out of its branch with Alt+ArrowLeft', async () => {
    const wrapper = tree({ draggable: true, expanded: [1, 11] })

    await row(wrapper, 'Pistons').trigger('keydown', { key: 'ArrowLeft', altKey: true })

    expect(labels(wrapper)).toEqual(['Catalog', 'Engine', 'Pistons', 'Brakes', 'Pages'])
    expect(wrapper.emitted('drop')?.[0]?.[0]).toMatchObject({ zone: 'after' })
  })

  it('says when a node has nowhere to go', async () => {
    const wrapper = tree({ draggable: true })

    await row(wrapper, 'Catalog').trigger('keydown', { key: 'ArrowUp', altKey: true })

    expect(wrapper.emitted('drop')).toBeUndefined()
    expect(wrapper.get('.wx-tree__live').text()).toContain('cannot move there')
  })

  /* ---------------------------------------------------------------- a11y */

  it('is a tree of treeitems that say where they sit', () => {
    const wrapper = tree({ ariaLabel: 'Catalog' })

    expect(wrapper.get('[role="tree"]').attributes('aria-label')).toBe('Catalog')

    const engine = row(wrapper, 'Engine')
    expect(engine.attributes('role')).toBe('treeitem')
    expect(engine.attributes('aria-level')).toBe('2')
    expect(engine.attributes('aria-posinset')).toBe('1')
    expect(engine.attributes('aria-setsize')).toBe('2')
    expect(engine.attributes('aria-expanded')).toBe('false')
  })

  it('keeps one tab stop for the whole tree', () => {
    const wrapper = tree()
    const stops = wrapper
      .findAll('.wx-tree__row')
      .filter((item) => item.attributes('tabindex') === '0')

    expect(stops).toHaveLength(1)
  })

  it('renders the label slot instead of the field', () => {
    const wrapper = tree({}, { default: '<template #default="{ node }">#{{ node.id }}</template>' })

    expect(labels(wrapper)[0]).toBe('#1')
  })
})
