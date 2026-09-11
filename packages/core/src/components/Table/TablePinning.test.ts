import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import WxTable from './Table.vue'
import type { TableColumn } from './types'

interface Row extends Record<string, unknown> {
  id: number
  code: string
  name: string
  total: number
}

const rows: Row[] = [
  { id: 1, code: 'WX-1', name: 'Ada', total: 10 },
  { id: 2, code: 'WX-2', name: 'Grace', total: 20 },
]

const columns: TableColumn<Row>[] = [
  { key: 'code', label: 'Code', width: 110, fixed: 'left' },
  { key: 'name', label: 'Name', minWidth: 220 },
  { key: 'total', label: 'Total', width: 140, fixed: 'right' },
]

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

/**
 * jsdom lays nothing out, so the heading row is told what the browser "gave" it: the
 * checkbox column comes out 36.5px wide rather than the 44 it asked for, which is
 * exactly the mismatch that used to leave a gap beside it.
 */
function layOut(wrapper: ReturnType<typeof mountTable>, widths: number[]) {
  const cells = wrapper.element.querySelectorAll('thead tr > *')
  cells.forEach((cell, index) => {
    ;(cell as HTMLElement).getBoundingClientRect = () =>
      ({ width: widths[index] ?? 0 }) as unknown as DOMRect
  })
}

function mountTable(props: Record<string, unknown> = {}) {
  return mount(WxTable, {
    props: { data: rows, columns, rowKey: 'id', selectable: true, ...props } as never,
  })
}

function pinStyle(wrapper: ReturnType<typeof mountTable>, key: string) {
  const index = columns.findIndex((column) => column.key === key)
  const cell = wrapper.element.querySelectorAll('tbody tr:first-child > *')[index + 1]
  return (cell as HTMLElement).getAttribute('style') ?? ''
}

describe('WxTable pinning', () => {
  it('parks a pinned column behind the width the browser actually gave', async () => {
    const wrapper = mountTable()

    // Declared: checkbox 44. Given: 36.5 — which is where the next column starts.
    layOut(wrapper, [36.5, 94, 220, 120])

    /* A change to the rows is one of the things that asks for a fresh measurement. */
    await wrapper.setProps({ data: [...rows, { id: 3, code: 'WX-3', name: 'Alan', total: 30 }] })
    await nextTick()
    await nextTick()

    expect(pinStyle(wrapper, 'code')).toContain('--wx-pin-left: 36.5px')
  })

  it('falls back to the declared width before anything has been measured', () => {
    const wrapper = mountTable()

    expect(pinStyle(wrapper, 'code')).toContain('--wx-pin-left: 44px')
  })

  it('squares the corners under a heading', () => {
    expect(mountTable({ searchable: true }).classes()).toContain('wx-table--has-header')
    expect(mountTable().classes()).not.toContain('wx-table--has-header')
  })

  it('casts no edge shadow until something is hidden', async () => {
    const wrapper = mountTable()
    await nextTick()

    // Nothing measured, nothing scrolled: neither side has more to show.
    expect(wrapper.classes()).not.toContain('has-more-left')
    expect(wrapper.classes()).not.toContain('has-more-right')
  })

  it('says which side has more once the rows have slid', async () => {
    const wrapper = mountTable()
    const scroll = wrapper.get('.wx-table__scroll').element as HTMLElement

    Object.defineProperty(scroll, 'scrollLeft', { value: 80, configurable: true })
    Object.defineProperty(scroll, 'clientWidth', { value: 400, configurable: true })
    Object.defineProperty(scroll, 'scrollWidth', { value: 900, configurable: true })

    await wrapper.get('.wx-table__scroll').trigger('scroll')

    expect(wrapper.classes()).toContain('has-more-left')
    expect(wrapper.classes()).toContain('has-more-right')
  })
})
