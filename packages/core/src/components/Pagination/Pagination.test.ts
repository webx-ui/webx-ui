import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxPagination from './Pagination.vue'
import type { Paginated } from '../Table/types'

/** An en dash and an ellipsis reach the DOM as characters, not as the entities written. */
const DASH = '–'
const ELLIPSIS = '…'

const page: Paginated<unknown> = {
  data: [],
  current_page: 3,
  last_page: 9,
  per_page: 15,
  total: 128,
  from: 31,
  to: 45,
}

function mountPagination(props: Record<string, unknown> = {}) {
  return mount(WxPagination, { props: { paginator: page, ...props } })
}

function pageButtons(wrapper: ReturnType<typeof mountPagination>) {
  return wrapper
    .findAll('.wx-pagination__list li')
    .map((item) => item.text())
    .filter((text) => text !== '')
}

describe('WxPagination', () => {
  it('reads the position out of a paginator, counted the way Laravel counted it', () => {
    const wrapper = mountPagination()

    expect(wrapper.get('.wx-pagination__total').text()).toBe(`31${DASH}45 of 128`)
    expect(wrapper.get('.is-current').text()).toBe('3')
  })

  it('says so plainly when there is nothing to page through', () => {
    const wrapper = mountPagination({
      paginator: {
        data: [],
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
        from: null,
        to: null,
      },
    })

    expect(wrapper.get('.wx-pagination__total').text()).toBe('Nothing to show')
  })

  it('works out the last page from the total when there is no paginator', () => {
    const wrapper = mountPagination({ paginator: null, total: 42, perPage: 10, page: 1 })

    expect(pageButtons(wrapper)).toEqual(['1', '2', ELLIPSIS, '5'])
    expect(wrapper.get('.wx-pagination__total').text()).toBe(`1${DASH}10 of 42`)
  })

  it('reports the page it was asked for', async () => {
    const wrapper = mountPagination()

    await wrapper.findAll('.wx-pagination__button')[2].trigger('click')

    expect(wrapper.emitted('update:page')?.at(-1)).toEqual([2])
    expect(wrapper.emitted('change')?.at(-1)).toEqual([{ page: 2, perPage: 15 }])
  })

  it('steps with the arrows', async () => {
    const wrapper = mountPagination()
    const arrows = wrapper.findAll('.wx-pagination__button--arrow')

    await arrows[0].trigger('click')
    expect(wrapper.emitted('change')?.at(-1)).toEqual([{ page: 2, perPage: 15 }])

    // The model moved with the first click, so forward lands back where it started.
    await arrows[1].trigger('click')
    expect(wrapper.emitted('change')?.at(-1)).toEqual([{ page: 3, perPage: 15 }])
  })

  it('stops at both ends', () => {
    const first = mountPagination({ paginator: { ...page, current_page: 1 } })
    const last = mountPagination({ paginator: { ...page, current_page: 9 } })

    expect(first.findAll('.wx-pagination__button--arrow')[0].attributes('disabled')).toBeDefined()
    expect(last.findAll('.wx-pagination__button--arrow')[1].attributes('disabled')).toBeDefined()
  })

  it('says nothing when the current page is clicked again', async () => {
    const wrapper = mountPagination()

    await wrapper.get('.is-current').trigger('click')

    expect(wrapper.emitted('change')).toBeUndefined()
  })

  it('keeps both ends reachable and folds the rest away', () => {
    const wrapper = mountPagination({ paginator: { ...page, current_page: 5, last_page: 20 } })

    expect(pageButtons(wrapper)).toEqual(['1', ELLIPSIS, '4', '5', '6', ELLIPSIS, '20'])
  })

  it('spells out a gap of one page instead of hiding it behind an ellipsis', () => {
    const wrapper = mountPagination({ paginator: { ...page, current_page: 3, last_page: 5 } })

    expect(pageButtons(wrapper)).toEqual(['1', '2', '3', '4', '5'])
  })

  it('widens the run of pages when more siblings are asked for', () => {
    const wrapper = mountPagination({
      paginator: { ...page, current_page: 10, last_page: 20 },
      siblings: 2,
    })

    expect(pageButtons(wrapper)).toEqual([
      '1',
      ELLIPSIS,
      '8',
      '9',
      '10',
      '11',
      '12',
      ELLIPSIS,
      '20',
    ])
  })

  it('goes back to the first page when the page size changes', async () => {
    const wrapper = mountPagination({ perPageOptions: [15, 50] })

    await wrapper.get('.wx-pagination__select').setValue('50')

    expect(wrapper.emitted('update:perPage')?.at(-1)).toEqual([50])
    expect(wrapper.emitted('update:page')?.at(-1)).toEqual([1])
    expect(wrapper.emitted('change')?.at(-1)).toEqual([{ page: 1, perPage: 50 }])
  })

  it('leaves the page-size control out unless options are given', () => {
    expect(mountPagination().find('.wx-pagination__select').exists()).toBe(false)
  })

  it('counts by default, with no page-size control in sight', () => {
    const wrapper = mountPagination({ paginator: null, total: 60, perPage: 10, page: 2 })

    expect(wrapper.get('.wx-pagination__total').text()).toBe(`11${DASH}20 of 60`)
    expect(wrapper.find('.wx-pagination__select').exists()).toBe(false)
  })

  it('recounts when the page size changes', async () => {
    const wrapper = mountPagination({
      paginator: null,
      total: 128,
      perPage: 15,
      page: 1,
      perPageOptions: [15, 30],
    })
    expect(wrapper.get('.wx-pagination__total').text()).toBe(`1${DASH}15 of 128`)

    await wrapper.get('.wx-pagination__select').setValue('30')

    expect(wrapper.get('.wx-pagination__total').text()).toBe(`1${DASH}30 of 128`)
  })

  it('words the count differently through the slot', () => {
    const wrapper = mount(WxPagination, {
      props: { paginator: page },
      slots: { total: '<em>{{ params.from }} to {{ params.to }}</em>' },
    })

    expect(wrapper.get('.wx-pagination__total em').text()).toBe('31 to 45')
  })

  it('marks the current page for a screen reader', () => {
    const wrapper = mountPagination()

    expect(wrapper.get('.is-current').attributes('aria-current')).toBe('page')
  })

  it('stays quiet while disabled', async () => {
    const wrapper = mountPagination({ disabled: true })

    await wrapper.findAll('.wx-pagination__button')[2].trigger('click')

    expect(wrapper.emitted('change')).toBeUndefined()
  })
})
