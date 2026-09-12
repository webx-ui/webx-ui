import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, mount, type VueWrapper } from '@vue/test-utils'
import { nextTick } from 'vue'
import WxTable from './Table.vue'
import type { TableColumn, TableNodeDropEvent } from './types'

interface Page extends Record<string, unknown> {
  id: number
  title: string
  status?: string
  has_children?: boolean
  children?: Page[]
}

const columns: TableColumn<Page>[] = [
  { key: 'title', label: 'Title' },
  { key: 'status', label: 'Status' },
]

function pages(): Page[] {
  return [
    {
      id: 1,
      title: 'About',
      status: 'live',
      children: [
        { id: 11, title: 'Team', status: 'live', has_children: false },
        { id: 12, title: 'Careers', status: 'draft', has_children: false },
      ],
    },
    { id: 2, title: 'Services', status: 'live', has_children: false },
    { id: 3, title: 'Contacts', status: 'live', has_children: false },
  ]
}

enableAutoUnmount(afterEach)

beforeEach(() => {
  vi.stubGlobal(
    'ResizeObserver',
    class {
      observe() {}
      unobserve() {}
      disconnect() {}
    },
  )
})

afterEach(() => vi.unstubAllGlobals())

function table(props: Record<string, unknown> = {}) {
  const held: { wrapper?: VueWrapper } = {}

  const merged: Record<string, unknown> = {
    columns,
    data: pages(),
    tree: {},
    expanded: [],
    'onUpdate:expanded': (value: unknown) => held.wrapper?.setProps({ expanded: value }),
    ...props,
  }

  /* `expanded: undefined` means nobody is holding it — the table keeps it itself. */
  if (merged.expanded === undefined) {
    delete merged.expanded
    delete merged['onUpdate:expanded']
  }

  const wrapper = mount(WxTable, { attachTo: document.body, props: merged as never })

  held.wrapper = wrapper
  return wrapper
}

const bodyRows = (wrapper: VueWrapper) => wrapper.findAll('.wx-table__body .wx-table__row')

const titles = (wrapper: VueWrapper) =>
  bodyRows(wrapper).map((row) => row.findAll('.wx-table__cell')[0]!.text())

const rowFor = (wrapper: VueWrapper, title: string) =>
  bodyRows(wrapper).find((row) => row.findAll('.wx-table__cell')[0]!.text() === title)!

/** A drag event jsdom can carry: `DataTransfer` does not exist there. */
const dataTransfer = () => ({ setData: vi.fn(), dropEffect: '', effectAllowed: '' })

async function drag(wrapper: VueWrapper, from: string, to: string) {
  await rowFor(wrapper, from).trigger('dragstart', { dataTransfer: dataTransfer() })
  await rowFor(wrapper, to).trigger('dragover', { dataTransfer: dataTransfer() })
  await rowFor(wrapper, to).trigger('drop')
  await nextTick()
}

describe('WxTable in tree mode', () => {
  it('shows the roots and keeps the branches closed', () => {
    const wrapper = table()

    expect(titles(wrapper)).toEqual(['About', 'Services', 'Contacts'])
  })

  it('opens a branch from the first column', async () => {
    const wrapper = table()

    await rowFor(wrapper, 'About').get('.wx-table__tree-toggle').trigger('click')

    expect(titles(wrapper)).toEqual(['About', 'Team', 'Careers', 'Services', 'Contacts'])
    expect(wrapper.emitted('expand-change')?.at(-1)?.[0]).toEqual([1])
  })

  it('opens everything at once when asked', () => {
    const wrapper = table({ tree: { defaultExpandAll: true }, expanded: undefined })

    expect(titles(wrapper)).toContain('Team')
  })

  it('indents a child by its depth', async () => {
    const wrapper = table({ tree: { indent: 24 }, expanded: [1] })

    const child = rowFor(wrapper, 'Team').get('.wx-table__tree')

    expect(child.attributes('style')).toContain('24px')
  })

  it('has nothing to open on a row that says it has no children', () => {
    const wrapper = table()

    expect(rowFor(wrapper, 'Services').get('.wx-table__tree-toggle').classes()).toContain('is-leaf')
  })

  it('offers a chevron where a lazy row said nothing about children', () => {
    const wrapper = table({
      data: [{ id: 9, title: 'Unknown' }],
      tree: { lazy: true, load: vi.fn() },
    })

    expect(rowFor(wrapper, 'Unknown').get('.wx-table__tree-toggle').classes()).not.toContain(
      'is-leaf',
    )
  })

  /* ---------------------------------------------------------------- lazy */

  it('fetches a branch the first time it opens', async () => {
    const load = vi.fn().mockResolvedValue([{ id: 21, title: 'Delivery', has_children: false }])
    const wrapper = table({
      data: [{ id: 2, title: 'Services', has_children: true }],
      tree: { lazy: true, load },
    })

    await rowFor(wrapper, 'Services').get('.wx-table__tree-toggle').trigger('click')
    await new Promise((resolve) => setTimeout(resolve))

    expect(load).toHaveBeenCalledTimes(1)
    expect(titles(wrapper)).toEqual(['Services', 'Delivery'])
  })

  /* ------------------------------------------------------ what a tree is not */

  it('draws no sort control, whatever the columns say', () => {
    const flat = mount(WxTable, {
      props: { columns: [{ key: 'title', sortable: true }], data: [] } as never,
    })
    const treed = table({ columns: [{ key: 'title', sortable: true }] })

    expect(flat.find('.wx-table__sort').exists()).toBe(true)
    expect(treed.find('.wx-table__sort').exists()).toBe(false)
  })

  it('leaves the pagination out, even for a paginator', () => {
    const paginated = {
      data: pages(),
      current_page: 1,
      last_page: 3,
      per_page: 15,
      total: 40,
      from: 1,
      to: 15,
    }

    const flat = mount(WxTable, { props: { columns, data: paginated } as never })
    const treed = table({ data: paginated })

    expect(flat.findComponent({ name: 'WxPagination' }).exists()).toBe(true)
    expect(treed.findComponent({ name: 'WxPagination' }).exists()).toBe(false)
  })

  /* ------------------------------------------------------------- dragging */

  it('moves a row and reports where it landed', async () => {
    const wrapper = table({ tree: { draggable: true } })

    await drag(wrapper, 'Contacts', 'Services')

    const event = wrapper.emitted('node-drop')?.[0]?.[0] as TableNodeDropEvent<Page>
    expect(event).toMatchObject({ zone: 'inside', index: 0, via: 'pointer' })
    expect(event.row.id).toBe(3)
    expect(event.parent?.id).toBe(2)
    expect(titles(wrapper)).toEqual(['About', 'Services', 'Contacts'])
  })

  it('refuses to drop a branch inside its own subtree', async () => {
    const wrapper = table({ tree: { draggable: true }, expanded: [1] })

    await drag(wrapper, 'About', 'Team')

    expect(wrapper.emitted('node-drop')).toBeUndefined()
  })

  it('asks allow-drop before it moves anything', async () => {
    const allowDrop = vi.fn().mockReturnValue(false)
    const wrapper = table({ tree: { draggable: true, allowDrop } })

    await drag(wrapper, 'Contacts', 'Services')

    expect(allowDrop).toHaveBeenCalled()
    expect(wrapper.emitted('node-drop')).toBeUndefined()
  })

  it('does not pick up a row allow-drag turned down', () => {
    const wrapper = table({
      tree: { draggable: true, allowDrag: (row: Page) => row.id !== 2 },
    })

    expect(rowFor(wrapper, 'Services').attributes('draggable')).toBe('false')
    expect(rowFor(wrapper, 'Contacts').attributes('draggable')).toBe('true')
  })

  it('leaves the rows alone when nothing is draggable', () => {
    const wrapper = table()

    expect(rowFor(wrapper, 'Services').attributes('draggable')).toBe('false')
  })

  /* --------------------------------------------------------------- spring */

  it('opens a closed branch a row is held over', async () => {
    vi.useFakeTimers()
    const wrapper = table({ tree: { draggable: true, springDelay: 400 } })

    await rowFor(wrapper, 'Contacts').trigger('dragstart', { dataTransfer: dataTransfer() })
    await rowFor(wrapper, 'About').trigger('dragover', { dataTransfer: dataTransfer() })

    expect(titles(wrapper)).not.toContain('Team')

    await vi.advanceTimersByTimeAsync(400)
    await nextTick()

    expect(titles(wrapper)).toContain('Team')
    vi.useRealTimers()
  })

  it('holds the branch closed when the spring is switched off', async () => {
    vi.useFakeTimers()
    const wrapper = table({ tree: { draggable: true, springDelay: 0 } })

    await rowFor(wrapper, 'Contacts').trigger('dragstart', { dataTransfer: dataTransfer() })
    await rowFor(wrapper, 'About').trigger('dragover', { dataTransfer: dataTransfer() })
    await vi.advanceTimersByTimeAsync(2000)
    await nextTick()

    expect(titles(wrapper)).not.toContain('Team')
    vi.useRealTimers()
  })
})
