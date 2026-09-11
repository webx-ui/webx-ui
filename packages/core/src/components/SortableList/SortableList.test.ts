import { describe, expect, it } from 'vitest'
import { mount, type VueWrapper } from '@vue/test-utils'
import WxSortableList from './SortableList.vue'

interface Product {
  id: number
  title: string
}

const products: Product[] = [
  { id: 1, title: 'Alternator Belt' },
  { id: 2, title: 'Drive Pump Belt' },
  { id: 3, title: 'Water Coolant Tank Cap' },
]

function list(props: Record<string, unknown> = {}, slots: Record<string, string> = {}) {
  const held: { wrapper?: VueWrapper } = {}

  const wrapper = mount(WxSortableList, {
    attachTo: document.body,
    props: {
      /* Held the way a parent holds it, so a second key press builds on the first. */
      modelValue: [...products],
      'onUpdate:modelValue': (value: unknown[]) => held.wrapper?.setProps({ modelValue: value }),
      ...props,
    },
    slots,
  })

  held.wrapper = wrapper
  return wrapper
}

function titles(wrapper: VueWrapper) {
  return wrapper.findAll('.wx-sortable-list__content').map((row) => row.text())
}

function grip(wrapper: VueWrapper, index: number) {
  return wrapper.findAll('.wx-sortable-list__grip')[index]
}

function said(wrapper: VueWrapper) {
  return wrapper.get('.wx-sortable-list__live').text()
}

describe('WxSortableList', () => {
  it('draws a row per item and names it from the item itself', () => {
    const wrapper = list()

    expect(wrapper.findAll('.wx-sortable-list__row')).toHaveLength(3)
    expect(titles(wrapper)).toEqual(products.map((product) => product.title))
  })

  it('shows a heading only when there is one to show', () => {
    expect(list().find('.wx-sortable-list__head').exists()).toBe(false)

    const titled = list({ title: 'Pick the products' }, { extra: '<button>Find</button>' })
    expect(titled.get('.wx-sortable-list__title').text()).toBe('Pick the products')
    expect(titled.get('.wx-sortable-list__extra').text()).toBe('Find')
  })

  it('hands the row and its position to the slot', () => {
    const wrapper = list(
      {},
      { default: '<span>{{ params.index }}: {{ params.item.title }}</span>' },
    )

    expect(titles(wrapper)[1]).toBe('1: Drive Pump Belt')
  })

  it('carries a grip, and gives it the name of the row it holds', () => {
    const wrapper = list()

    expect(wrapper.findAll('.wx-sortable-list__grip')).toHaveLength(3)
    expect(grip(wrapper, 0).attributes('aria-label')).toBe('Reorder: Alternator Belt')
    expect(wrapper.find('.wx-sortable-list__row').attributes('tabindex')).toBeUndefined()
  })

  it('moves the tab stop to the row itself when the whole row is the handle', () => {
    const wrapper = list({ handle: 'row' })

    expect(wrapper.find('.wx-sortable-list__grip').exists()).toBe(false)
    const row = wrapper.get('.wx-sortable-list__row')
    expect(row.attributes('tabindex')).toBe('0')
    expect(row.attributes('role')).toBe('button')
  })

  it('picks a row up, moves it and drops it with the keyboard', async () => {
    const wrapper = list()

    await grip(wrapper, 0).trigger('keydown', { key: ' ' })
    expect(grip(wrapper, 0).attributes('aria-pressed')).toBe('true')
    expect(said(wrapper)).toContain('Picked up Alternator Belt')

    await grip(wrapper, 0).trigger('keydown', { key: 'ArrowDown' })
    expect(titles(wrapper)).toEqual([
      'Drive Pump Belt',
      'Alternator Belt',
      'Water Coolant Tank Cap',
    ])
    expect(wrapper.emitted('move')?.at(-1)?.[0]).toMatchObject({ from: 0, to: 1, via: 'keyboard' })
    expect(said(wrapper)).toBe('Alternator Belt is now 2 of 3.')

    await grip(wrapper, 1).trigger('keydown', { key: ' ' })
    expect(said(wrapper)).toBe('Dropped Alternator Belt, 2 of 3.')
    expect(grip(wrapper, 1).attributes('aria-pressed')).toBe('false')
  })

  it('puts a row back where it came from on escape', async () => {
    const wrapper = list()

    await grip(wrapper, 2).trigger('keydown', { key: ' ' })
    await grip(wrapper, 2).trigger('keydown', { key: 'ArrowUp' })
    await grip(wrapper, 1).trigger('keydown', { key: 'ArrowUp' })
    expect(titles(wrapper)[0]).toBe('Water Coolant Tank Cap')

    await grip(wrapper, 0).trigger('keydown', { key: 'Escape' })
    expect(titles(wrapper)).toEqual(products.map((product) => product.title))
    expect(said(wrapper)).toBe('Move cancelled.')
  })

  it('says so rather than moving a row off either end', async () => {
    const wrapper = list()

    await grip(wrapper, 0).trigger('keydown', { key: ' ' })
    await grip(wrapper, 0).trigger('keydown', { key: 'ArrowUp' })

    expect(titles(wrapper)).toEqual(products.map((product) => product.title))
    expect(said(wrapper)).toBe('Alternator Belt is already 1 of 3.')
  })

  it('leaves the keys of a button inside the row alone', async () => {
    const wrapper = list({ handle: 'row' }, { actions: '<button class="drop">Delete</button>' })

    await wrapper.get('.drop').trigger('keydown', { key: ' ' })

    expect(said(wrapper)).toBe('')
    expect(wrapper.emitted('move')).toBeUndefined()
  })

  it('moves nothing when disabled', async () => {
    const wrapper = list({ disabled: true })

    expect(grip(wrapper, 0).attributes('tabindex')).toBe('-1')

    await grip(wrapper, 0).trigger('keydown', { key: ' ' })
    await grip(wrapper, 0).trigger('keydown', { key: 'ArrowDown' })

    expect(titles(wrapper)).toEqual(products.map((product) => product.title))
    expect(wrapper.emitted('move')).toBeUndefined()
  })

  it('says what an empty list is, inside the list so a row can be dropped into it', () => {
    const wrapper = list({ modelValue: [], emptyText: 'No products yet' })

    expect(wrapper.get('.wx-sortable-list__body .wx-sortable-list__empty').text()).toBe(
      'No products yet',
    )
  })

  it('takes a key from a field or from a function', () => {
    const byField = list({ itemKey: 'title' })
    expect(byField.findAll('.wx-sortable-list__row')).toHaveLength(3)

    const byFunction = list({ itemKey: (item: Product) => `p-${item.id}` })
    expect(byFunction.findAll('.wx-sortable-list__row')).toHaveLength(3)
  })
})
