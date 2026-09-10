import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
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

    await wrapper.get('tbody .wx-table__cell--utility').trigger('click')

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

describe('WxTable pagination and state', () => {
  const page: Paginated<User> = {
    data: users,
    current_page: 1,
    last_page: 4,
    per_page: 2,
    total: 8,
    from: 1,
    to: 2,
  }

  afterEach(() => {
    vi.useRealTimers()
    localStorage.clear()
  })

  it('paginates itself once the data is a paginator', () => {
    const wrapper = mountTable({ data: page })

    expect(wrapper.find('.wx-pagination').exists()).toBe(true)
    expect(wrapper.get('.wx-pagination__total').text()).toContain('of 8')
  })

  it('leaves a plain array alone', () => {
    expect(mountTable().find('.wx-pagination').exists()).toBe(false)
  })

  it('can be told either way', () => {
    expect(mountTable({ pagination: true }).find('.wx-pagination').exists()).toBe(true)
    expect(mountTable({ data: page, pagination: false }).find('.wx-pagination').exists()).toBe(
      false,
    )
  })

  it('steps aside for a footer slot', () => {
    const wrapper = mountTable(
      { data: page },
      { slots: { footer: '<span class="mine">mine</span>' } },
    )

    expect(wrapper.find('.mine').exists()).toBe(true)
    expect(wrapper.find('.wx-pagination').exists()).toBe(false)
  })

  it('reports the whole state once on mount', () => {
    const wrapper = mountTable({ data: page })

    expect(wrapper.emitted('state-change')).toHaveLength(1)
    expect(wrapper.emitted('state-change')?.[0]).toEqual([
      { page: 1, perPage: 15, sort: null, search: '' },
    ])
  })

  it('reports a sort, and goes back to the first page for it', async () => {
    const wrapper = mountTable({
      data: page,
      page: 3,
      columns: [{ key: 'name', label: 'Name', sortable: true }],
    })

    await wrapper.get('.wx-table__sort').trigger('click')

    expect(wrapper.emitted('state-change')?.at(-1)).toEqual([
      { page: 1, perPage: 15, sort: { key: 'name', order: 'asc' }, search: '' },
    ])
  })

  it('reports a burst of typing once, on the first page', async () => {
    vi.useFakeTimers()
    const wrapper = mountTable({ data: page, page: 2, searchable: true })
    const input = wrapper.get('.wx-table__search input')

    await input.setValue('a')
    await input.setValue('ad')
    await input.setValue('ada')
    expect(wrapper.emitted('state-change')).toHaveLength(1)

    vi.advanceTimersByTime(300)
    await nextTick()

    expect(wrapper.emitted('state-change')).toHaveLength(2)
    expect(wrapper.emitted('state-change')?.at(-1)).toEqual([
      { page: 1, perPage: 15, sort: null, search: 'ada' },
    ])
  })

  it('writes the state down when asked to remember it', async () => {
    const wrapper = mountTable({
      data: page,
      persist: 'orders',
      columns: [{ key: 'name', label: 'Name', sortable: true }],
    })

    await wrapper.get('.wx-table__sort').trigger('click')

    expect(JSON.parse(localStorage.getItem('wx-table:orders') ?? '{}')).toEqual({
      page: 1,
      perPage: 15,
      sort: { key: 'name', order: 'asc' },
      search: '',
    })
  })

  it('opens where it was left', async () => {
    localStorage.setItem(
      'wx-table:orders',
      JSON.stringify({ page: 3, perPage: 25, sort: { key: 'name', order: 'desc' }, search: 'ada' }),
    )

    const wrapper = mountTable({ data: page, persist: 'orders', searchable: true })

    expect(wrapper.emitted('state-change')?.[0]).toEqual([
      { page: 3, perPage: 25, sort: { key: 'name', order: 'desc' }, search: 'ada' },
    ])

    // The restore happens on mount, so the field catches up on the next tick.
    await nextTick()
    expect((wrapper.get('.wx-table__search input').element as HTMLInputElement).value).toBe('ada')
  })

  it('remembers nothing without a key', () => {
    mountTable({ data: page })

    expect(localStorage.length).toBe(0)
  })

  it('falls back to the defaults when what was stored makes no sense', () => {
    localStorage.setItem('wx-table:orders', '{ not json')

    const wrapper = mountTable({ data: page, persist: 'orders' })

    expect(wrapper.emitted('state-change')?.[0]).toEqual([
      { page: 1, perPage: 15, sort: null, search: '' },
    ])
  })

  it('ignores a stored page that could not be one', () => {
    localStorage.setItem('wx-table:orders', JSON.stringify({ page: -2, perPage: 'lots' }))

    const wrapper = mountTable({ data: page, persist: 'orders' })

    expect(wrapper.emitted('state-change')?.[0]).toEqual([
      { page: 1, perPage: 15, sort: null, search: '' },
    ])
  })
})

describe('WxTable summary', () => {
  const money: TableColumn<User>[] = [
    { key: 'name', label: 'Item' },
    { key: 'balance', label: 'Total', align: 'right' },
  ]

  it('spans the caption across the columns ahead of the figure', () => {
    const wrapper = mountTable({
      columns: money,
      summary: [{ label: 'Subtotal', cells: { balance: '1540' } }],
    })

    const cells = wrapper.findAll('tfoot td')
    expect(cells[0].text()).toBe('Subtotal')
    expect(cells[0].attributes('colspan')).toBe('1')
    expect(cells[1].text()).toBe('1540')
  })

  it('counts the checkbox column into the caption', () => {
    const wrapper = mountTable({
      columns: money,
      selectable: true,
      summary: [{ label: 'Subtotal', cells: { balance: '1540' } }],
    })

    expect(wrapper.get('tfoot td').attributes('colspan')).toBe('2')
  })

  it('keeps each figure under its own column, aligned as that column is', () => {
    const wrapper = mountTable({
      columns: money,
      summary: [{ label: 'Subtotal', cells: { balance: '1540' } }],
    })

    expect(wrapper.findAll('tfoot td')[1].classes()).toContain('wx-table__cell--right')
  })

  it('stacks several lines and marks the one that matters', () => {
    const wrapper = mountTable({
      columns: money,
      summary: [
        { label: 'Sum', cells: { balance: '1540' } },
        { label: 'Discount', cells: { balance: '-40' } },
        { label: 'Total', cells: { balance: '1500' }, strong: true },
      ],
    })

    const lines = wrapper.findAll('.wx-table__summary')
    expect(lines).toHaveLength(3)
    expect(lines[2].classes()).toContain('is-strong')
    expect(lines[1].text()).toContain('-40')
  })

  it('lets a slot render the figure', () => {
    const wrapper = mountTable(
      { columns: money, summary: [{ label: 'Total', cells: { balance: 1500 } }] },
      { slots: { 'summary-balance': '<b>{{ params.value }} EUR</b>' } },
    )

    expect(wrapper.get('tfoot b').text()).toBe('1500 EUR')
  })

  it('keeps the footer slot alongside the summary', () => {
    const wrapper = mountTable(
      { columns: money, summary: [{ label: 'Total', cells: { balance: 1500 } }] },
      { slots: { footer: '<span class="pager">pages</span>' } },
    )

    expect(wrapper.findAll('tfoot tr')).toHaveLength(2)
    expect(wrapper.get('.wx-table__footer-row td').attributes('colspan')).toBe('2')
  })
})

describe('WxTable expandable rows', () => {
  it('opens a row under itself, spanning every column', async () => {
    const wrapper = mountTable(
      { expandable: true },
      { slots: { expanded: '<div class="detail">{{ params.row.name }} detail</div>' } },
    )

    expect(wrapper.find('.detail').exists()).toBe(false)

    await wrapper.get('.wx-table__expander').trigger('click')

    expect(wrapper.get('.detail').text()).toBe('Ada detail')
    expect(wrapper.get('.wx-table__expansion').attributes('colspan')).toBe('3')
  })

  it('reports the open rows by key', async () => {
    const wrapper = mountTable({ expandable: true })

    await wrapper.findAll('.wx-table__expander')[1].trigger('click')

    expect(wrapper.emitted('update:expanded')?.at(-1)).toEqual([[9]])
    expect(wrapper.emitted('expand-change')?.at(-1)).toEqual([[9], [users[1]]])
  })

  it('closes a row that was open', async () => {
    const wrapper = mountTable({ expandable: true, expanded: [7] })

    await wrapper.get('.wx-table__expander').trigger('click')

    expect(wrapper.emitted('update:expanded')?.at(-1)).toEqual([[]])
  })

  it('gives a row with nothing to show no control at all', () => {
    const wrapper = mountTable({ expandable: true, expandableIf: (row: User) => row.id !== 9 })

    expect(wrapper.findAll('.wx-table__expander')).toHaveLength(1)
  })

  it('does not select the row the chevron sits in', async () => {
    const wrapper = mountTable({ expandable: true })

    await wrapper.get('.wx-table__expander').trigger('click')

    expect(wrapper.emitted('row-click')).toBeUndefined()
  })
})

describe('WxTable fixed columns', () => {
  const wide: TableColumn<User>[] = [
    { key: 'name', label: 'Name', width: 160, fixed: 'left' },
    { key: 'balance', label: 'Balance', width: 300 },
    { key: 'id', label: '', width: 90, fixed: 'right' },
  ]

  it('pins a column at the width of everything pinned before it', () => {
    const wrapper = mountTable({ columns: wide, selectable: true })
    const headers = wrapper.findAll('thead th')

    // The checkbox column is 44 wide and pinned too, so the name column starts after it.
    expect(headers[0].attributes('style')).toContain('--wx-pin-left: 0px')
    expect(headers[1].attributes('style')).toContain('--wx-pin-left: 44px')
    expect(headers[3].attributes('style')).toContain('--wx-pin-right: 0px')
  })

  it('stacks two pinned columns on the same edge', () => {
    const wrapper = mountTable({
      columns: [
        { key: 'id', label: '#', width: 60, fixed: 'left' },
        { key: 'name', label: 'Name', width: 160, fixed: 'left' },
        { key: 'balance', label: 'Balance', width: 300 },
      ],
    })
    const headers = wrapper.findAll('thead th')

    expect(headers[0].attributes('style')).toContain('--wx-pin-left: 0px')
    expect(headers[1].attributes('style')).toContain('--wx-pin-left: 60px')
    expect(headers[1].classes()).toContain('is-fixed-edge')
  })

  it('leaves the columns alone when nothing is pinned', () => {
    const wrapper = mountTable({ selectable: true })

    expect(wrapper.get('thead th').attributes('style')).toBeUndefined()
    expect(wrapper.get('thead th').classes()).not.toContain('is-fixed-left')
  })
})

describe('WxTable header', () => {
  afterEach(() => {
    vi.useRealTimers()
  })

  it('stays out of the way until there is something to put in it', () => {
    expect(mountTable().find('.wx-table__header').exists()).toBe(false)
  })

  it('puts the title on the left and the search on the right', () => {
    const wrapper = mountTable({ title: 'Orders', searchable: true })

    expect(wrapper.get('.wx-table__title').text()).toBe('Orders')
    expect(wrapper.find('.wx-table__search input').exists()).toBe(true)
  })

  it('answers in the field at once and tells the backend when typing settles', async () => {
    vi.useFakeTimers()
    const wrapper = mountTable({ searchable: true })

    await wrapper.get('.wx-table__search input').setValue('ada')

    expect(wrapper.emitted('update:search')?.at(-1)).toEqual(['ada'])
    expect(wrapper.emitted('search')).toBeUndefined()

    vi.advanceTimersByTime(300)
    expect(wrapper.emitted('search')?.at(-1)).toEqual(['ada'])
  })

  it('reports only the last of a burst of keystrokes', async () => {
    vi.useFakeTimers()
    const wrapper = mountTable({ searchable: true })
    const input = wrapper.get('.wx-table__search input')

    await input.setValue('a')
    vi.advanceTimersByTime(100)
    await input.setValue('ad')
    vi.advanceTimersByTime(100)
    await input.setValue('ada')
    vi.advanceTimersByTime(300)

    expect(wrapper.emitted('search')).toHaveLength(1)
    expect(wrapper.emitted('search')?.at(-1)).toEqual(['ada'])
  })

  it('reports every keystroke when the wait is turned off', async () => {
    const wrapper = mountTable({ searchable: true, searchDebounce: 0 })

    await wrapper.get('.wx-table__search input').setValue('ad')

    expect(wrapper.emitted('search')?.at(-1)).toEqual(['ad'])
  })
})
