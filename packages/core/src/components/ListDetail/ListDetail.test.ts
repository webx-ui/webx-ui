import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import WxListDetail from './ListDetail.vue'

/** A stand-in for the browser's observer: the test decides how wide the screen is. */
let emit: ((width: number) => void) | undefined

enableAutoUnmount(afterEach)

beforeEach(() => {
  vi.stubGlobal(
    'ResizeObserver',
    class {
      constructor(private callback: ResizeObserverCallback) {
        emit = (width: number) => {
          this.callback(
            [{ contentRect: { width } } as ResizeObserverEntry],
            this as unknown as ResizeObserver,
          )
        }
      }
      observe() {}
      unobserve() {}
      disconnect() {}
    },
  )
})

afterEach(() => {
  emit = undefined
  vi.unstubAllGlobals()
})

const slots = {
  filters: '<div class="t-filters">Views</div>',
  list: '<div class="t-list">Rows</div>',
  detail: '<div class="t-detail">One record</div>',
  empty: '<div class="t-empty">Pick one</div>',
}

function mountPane(props: Record<string, unknown> = {}) {
  return mount(WxListDetail, { props: { open: true, ...props }, slots })
}

describe('WxListDetail', () => {
  it('gives all three columns until it has been measured', () => {
    const wrapper = mountPane()

    expect(wrapper.find('.wx-list-detail__filters').exists()).toBe(true)
    expect(wrapper.find('.wx-list-detail__list').exists()).toBe(true)
    expect(wrapper.find('.wx-list-detail__detail').exists()).toBe(true)
  })

  it('adds its thresholds up out of the widths it was given', async () => {
    const wrapper = mountPane()

    // 240 + 380 + 420 is the room three columns need.
    emit?.(1040)
    await nextTick()
    expect(wrapper.find('.wx-list-detail__filters').exists()).toBe(true)

    emit?.(1039)
    await nextTick()
    expect(wrapper.find('.wx-list-detail__filters').exists()).toBe(false)
    expect(wrapper.find('.wx-list-detail__detail').exists()).toBe(true)

    // 380 + 420 is what is left for two.
    emit?.(799)
    await nextTick()
    expect(wrapper.find('.wx-list-detail__detail').exists()).toBe(false)
    expect(wrapper.find('.wx-list-detail__list--alone').exists()).toBe(true)
  })

  it('moves with the widths, not with numbers of its own', async () => {
    const wrapper = mountPane({ listWidth: 300, detailMin: 300, filtersWidth: 200 })

    emit?.(801)
    await nextTick()

    // 200 + 300 + 300 fits where the defaults would have folded the column away.
    expect(wrapper.find('.wx-list-detail__filters').exists()).toBe(true)
  })

  it('shows the empty slot in the pane while nothing is open', async () => {
    const wrapper = mountPane({ open: false })

    emit?.(1200)
    await nextTick()

    expect(wrapper.find('.t-detail').exists()).toBe(false)
    expect(wrapper.get('.t-empty').text()).toBe('Pick one')
  })

  it('raises the record as a panel once the pane no longer fits', async () => {
    const wrapper = mountPane({ open: false })

    emit?.(600)
    await nextTick()
    expect(wrapper.findComponent({ name: 'WxDrawer' }).props('open')).toBe(false)

    await wrapper.setProps({ open: true })
    expect(wrapper.findComponent({ name: 'WxDrawer' }).props('open')).toBe(true)
  })

  it('keeps the record open when the screen grows', async () => {
    const wrapper = mountPane({ open: true })

    emit?.(600)
    await nextTick()
    emit?.(1200)
    await nextTick()

    // The panel became a column; nothing was closed on the way.
    expect(wrapper.emitted('update:open')).toBeUndefined()
    expect(wrapper.find('.wx-list-detail__detail').exists()).toBe(true)
  })

  it('hands the list a way to reach filters that no longer have a column', async () => {
    const wrapper = mount(WxListDetail, {
      props: { open: true },
      slots: {
        ...slots,
        list: `
          <template #list="{ filtersInline, openFilters }">
            <button class="t-filter-button" :disabled="filtersInline" @click="openFilters">
              Filters
            </button>
          </template>
        `,
      },
    })

    emit?.(700)
    await nextTick()

    await wrapper.get('.t-filter-button').trigger('click')

    const drawers = wrapper.findAllComponents({ name: 'WxDrawer' })
    expect(drawers.some((drawer) => drawer.props('title') === 'Filters')).toBe(true)
    expect(wrapper.emitted('update:filtersOpen')?.at(-1)).toEqual([true])
  })

  it('closes the filters panel when the column comes back', async () => {
    const wrapper = mountPane({ filtersOpen: true })

    emit?.(700)
    await nextTick()
    emit?.(1400)
    await nextTick()

    expect(wrapper.emitted('update:filtersOpen')?.at(-1)).toEqual([false])
  })
})
