import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxTable from './Table.vue'
import type { Paginated, TableColumn } from './types'

interface User extends Record<string, unknown> {
  id: number
  name: string
  balance: number
  user?: { name: string }
}

const users: User[] = [
  { id: 7, name: 'Ada', balance: 1200 },
  { id: 9, name: 'Grace', balance: 340 },
]

const columns: TableColumn<User>[] = [
  { key: 'name', label: 'Name' },
  { key: 'balance', label: 'Balance' },
]

/**
 * A template infers the row type from `data`; a helper that takes a loose bag of props
 * has nothing to infer from and falls back to the default row, so the props are cast
 * once here instead of at every call.
 */
function mountTable(props: Record<string, unknown> = {}, options: Record<string, unknown> = {}) {
  return mount(WxTable, { props: { data: users, columns, ...props } as never, ...options })
}

function bodyText(wrapper: ReturnType<typeof mountTable>) {
  return wrapper.findAll('tbody tr').map((row) => row.findAll('td').map((cell) => cell.text()))
}

describe('WxTable', () => {
  it('renders a column per definition and a row per record', () => {
    const wrapper = mountTable()

    expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual(['Name', 'Balance'])
    expect(bodyText(wrapper)).toEqual([
      ['Ada', '1200'],
      ['Grace', '340'],
    ])
  })

  it('takes the rows out of a paginator as Laravel sends it', () => {
    const page: Paginated<User> = {
      data: users,
      current_page: 1,
      last_page: 4,
      per_page: 2,
      total: 8,
      from: 1,
      to: 2,
    }

    expect(bodyText(mountTable({ data: page }))).toHaveLength(2)
  })

  it('shows the empty text rather than an empty body', () => {
    const wrapper = mountTable({ data: [], emptyText: 'No users yet' })
    const cell = wrapper.get('.wx-table__empty')

    expect(cell.text()).toBe('No users yet')
    expect(cell.attributes('colspan')).toBe('2')
  })

  it('counts the checkbox column into the empty row', () => {
    const wrapper = mountTable({ data: [], selectable: true })

    expect(wrapper.get('.wx-table__empty').attributes('colspan')).toBe('3')
  })

  it('reads a dotted key, so an eager-loaded relation lands in its column', () => {
    const wrapper = mountTable({
      data: [{ id: 1, name: 'Ada', balance: 0, user: { name: 'Byron' } }],
      columns: [{ key: 'user.name', label: 'Owner' }],
    })

    expect(wrapper.get('tbody td').text()).toBe('Byron')
  })

  it('leaves a missing path blank instead of printing undefined', () => {
    const wrapper = mountTable({
      data: [{ id: 1, name: 'Ada', balance: 0 }],
      columns: [{ key: 'user.name', label: 'Owner' }],
    })

    expect(wrapper.get('tbody td').text()).toBe('')
  })

  it('runs the value through the column formatter', () => {
    const wrapper = mountTable({
      columns: [
        { key: 'balance', label: 'Balance', formatter: (value: unknown) => `${value} EUR` },
      ],
    })

    expect(bodyText(wrapper)).toEqual([['1200 EUR'], ['340 EUR']])
  })

  it('lets a cell slot replace the text', () => {
    const wrapper = mountTable(
      {},
      { slots: { 'cell-name': '<strong>{{ params.row.name }}</strong>' } },
    )

    expect(wrapper.get('tbody td strong').text()).toBe('Ada')
  })

  it('leaves a hidden column out', () => {
    const wrapper = mountTable({ columns: [columns[0], { ...columns[1], hidden: true }] })

    expect(wrapper.findAll('thead th')).toHaveLength(1)
  })

  it('cycles a sortable header through asc, desc and off', async () => {
    const wrapper = mountTable({ columns: [{ key: 'name', label: 'Name', sortable: true }] })
    const header = wrapper.get('.wx-table__sort')

    await header.trigger('click')
    expect(wrapper.emitted('sort-change')?.at(-1)).toEqual([{ key: 'name', order: 'asc' }])
    expect(wrapper.get('thead th').attributes('aria-sort')).toBe('ascending')

    await header.trigger('click')
    expect(wrapper.emitted('sort-change')?.at(-1)).toEqual([{ key: 'name', order: 'desc' }])
    expect(wrapper.get('thead th').attributes('aria-sort')).toBe('descending')

    await header.trigger('click')
    expect(wrapper.emitted('sort-change')?.at(-1)).toEqual([null])
    expect(wrapper.get('thead th').attributes('aria-sort')).toBe('none')
  })

  it('reports the sort without reordering the page', async () => {
    const wrapper = mountTable({ columns: [{ key: 'name', label: 'Name', sortable: true }] })

    await wrapper.get('.wx-table__sort').trigger('click')

    // Ada still first: the rows are one page of a query the server ordered.
    expect(bodyText(wrapper)).toEqual([['Ada'], ['Grace']])
  })

  it('gives a plain header no sort control', () => {
    const wrapper = mountTable()

    expect(wrapper.find('.wx-table__sort').exists()).toBe(false)
    expect(wrapper.get('thead th').attributes('aria-sort')).toBeUndefined()
  })

  it('selects a row by its key and hands back the row with it', async () => {
    const wrapper = mountTable({ selectable: true })

    await wrapper.findAll('tbody input[type="checkbox"]')[1].setValue(true)

    expect(wrapper.emitted('update:selected')?.at(-1)).toEqual([[9]])
    expect(wrapper.emitted('selection-change')?.at(-1)).toEqual([[9], [users[1]]])
  })

  it('falls back to the row position when the key field is missing', async () => {
    const wrapper = mountTable({ data: [{ name: 'Ada', balance: 1 }], selectable: true })

    await wrapper.get('tbody input[type="checkbox"]').setValue(true)

    expect(wrapper.emitted('update:selected')?.at(-1)).toEqual([[0]])
  })

  it('takes a function as the row key', async () => {
    const wrapper = mountTable({
      selectable: true,
      rowKey: (row: User) => `user-${row.id}`,
    })

    await wrapper.get('tbody input[type="checkbox"]').setValue(true)

    expect(wrapper.emitted('update:selected')?.at(-1)).toEqual([['user-7']])
  })

  it('selects the whole page from the header, keeping keys picked elsewhere', async () => {
    const wrapper = mountTable({ selectable: true, selected: [99] })

    await wrapper.get('thead input[type="checkbox"]').setValue(true)

    expect(wrapper.emitted('update:selected')?.at(-1)).toEqual([[99, 7, 9]])
  })

  it('clears only this page when the header is unticked', async () => {
    const wrapper = mountTable({ selectable: true, selected: [99, 7, 9] })

    await wrapper.get('thead input[type="checkbox"]').setValue(false)

    expect(wrapper.emitted('update:selected')?.at(-1)).toEqual([[99]])
  })

  it('marks the header partial when only some rows are picked', () => {
    const wrapper = mountTable({ selectable: true, selected: [7] })

    expect(wrapper.get('thead .wx-checkbox').classes()).toContain('is-indeterminate')
  })

  it('leaves a row out of reach when selectableIf says no', async () => {
    const wrapper = mountTable({ selectable: true, selectableIf: (row: User) => row.id !== 9 })

    const boxes = wrapper.findAll('tbody input[type="checkbox"]')
    expect(boxes[1].attributes('disabled')).toBeDefined()

    await wrapper.get('thead input[type="checkbox"]').setValue(true)
    expect(wrapper.emitted('update:selected')?.at(-1)).toEqual([[7]])
  })

  it('reports a click with the row and its position', async () => {
    const wrapper = mountTable()

    await wrapper.findAll('tbody tr')[1].trigger('click')

    const event = wrapper.emitted('row-click')?.at(-1)
    expect(event?.[0]).toEqual(users[1])
    expect(event?.[1]).toBe(1)
  })

  it('does not select the row a checkbox click landed in', async () => {
    const wrapper = mountTable({ selectable: true })

    await wrapper.get('tbody .wx-table__cell--select').trigger('click')

    expect(wrapper.emitted('row-click')).toBeUndefined()
  })

  it('keeps the rows on screen while loading and says it is busy', () => {
    const wrapper = mountTable({ loading: true })

    expect(wrapper.get('table').attributes('aria-busy')).toBe('true')
    expect(wrapper.findAll('tbody tr')).toHaveLength(2)
    expect(wrapper.find('.wx-table__loading').exists()).toBe(true)
  })

  it('waits for the load to finish before saying there is nothing', () => {
    const wrapper = mountTable({ data: [], loading: true })

    expect(wrapper.find('.wx-table__empty').exists()).toBe(false)
  })
})
