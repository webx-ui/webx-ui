import { describe, expect, it } from 'vitest'
import { mount, type VueWrapper } from '@vue/test-utils'
import { ref } from 'vue'
import { localesKey } from '../../composables/useLocalized'
import WxRepeater from './Repeater.vue'

interface Office {
  city: string
  address: string
}

const offices: Office[] = [
  { city: 'Kyiv', address: 'Khreshchatyk 1' },
  { city: 'Lviv', address: 'Rynok 2' },
]

function repeater(props: Record<string, unknown> = {}, slots: Record<string, string> = {}) {
  const held: { wrapper?: VueWrapper } = {}

  const wrapper = mount(WxRepeater, {
    attachTo: document.body,
    props: {
      /* Held the way a parent holds it: the next write builds on the last. */
      modelValue: offices.map((office) => ({ ...office })),
      'onUpdate:modelValue': (value: unknown[]) => held.wrapper?.setProps({ modelValue: value }),
      ...props,
    },
    slots,
  })

  held.wrapper = wrapper
  return wrapper
}

function model(wrapper: VueWrapper): Office[] {
  /* A generic component's props are not indexable by name, and the value is ours anyway. */
  return (wrapper.props() as unknown as { modelValue: Office[] }).modelValue
}

function rows(wrapper: VueWrapper) {
  return wrapper.findAll('.wx-repeater__row')
}

function removeButton(wrapper: VueWrapper, index: number) {
  return wrapper.findAll('.wx-repeater .wx-action')[index]!
}

describe('WxRepeater', () => {
  it('draws a row per item and the fields of one row in the slot', () => {
    const wrapper = repeater({}, { default: '<span class="city">{{ params.item.city }}</span>' })

    expect(rows(wrapper)).toHaveLength(2)
    expect(wrapper.findAll('.city').map((one) => one.text())).toEqual(['Kyiv', 'Lviv'])
  })

  it('appends what `newItem` builds', async () => {
    const wrapper = repeater({ newItem: () => ({ city: '', address: '' }) })

    await wrapper.get('.wx-repeater__add').trigger('click')

    expect(model(wrapper)).toHaveLength(3)
    expect(model(wrapper)[2]).toEqual({ city: '', address: '' })
    expect(wrapper.emitted('add')?.[0]?.[1]).toBe(2)
  })

  it('adds an empty object when nothing builds one', async () => {
    const wrapper = repeater()

    await wrapper.get('.wx-repeater__add').trigger('click')

    expect(model(wrapper)[2]).toEqual({})
  })

  it('stops adding at `max` and removing at `min`', async () => {
    const wrapper = repeater({ max: 2, min: 2 })

    expect(wrapper.get('.wx-repeater__add').attributes('disabled')).toBeDefined()

    await removeButton(wrapper, 0).trigger('click')

    expect(model(wrapper)).toHaveLength(2)
  })

  it('removes the row that was clicked', async () => {
    const wrapper = repeater()

    await removeButton(wrapper, 0).trigger('click')

    expect(model(wrapper).map((office) => office.city)).toEqual(['Lviv'])
    expect(wrapper.emitted('remove')?.[0]?.[0]).toEqual(offices[0])
  })

  it('writes a field through `update` without touching the item it was given', async () => {
    const first = { city: 'Kyiv', address: 'Khreshchatyk 1' }
    const wrapper = repeater(
      { modelValue: [first] },
      {
        default: `<button class="rename" @click="params.update({ city: 'Odesa' })">rename</button>`,
      },
    )

    await wrapper.get('.rename').trigger('click')

    expect(model(wrapper)[0]).toEqual({ city: 'Odesa', address: 'Khreshchatyk 1' })
    expect(first.city).toBe('Kyiv')
  })

  it('names a row by a key of the item, and by its position without one', () => {
    expect(repeater({ itemLabel: 'city' }).get('.wx-repeater__title').text()).toBe('#1 · Kyiv')
    expect(
      repeater({ itemLabel: () => 'Office' })
        .get('.wx-repeater__title')
        .text(),
    ).toBe('Office')
    expect(repeater({ collapsible: true }).get('.wx-repeater__title').text()).toBe('#1')
  })

  it('names a row by a translated field in the language being edited, else any filled in', () => {
    const wrapper = mount(WxRepeater, {
      props: {
        itemLabel: 'title',
        modelValue: [{ title: { en: 'Coffee', ru: 'Кофе' } }, { title: { en: '', ru: 'Чай' } }],
      },
      global: {
        provide: { [localesKey as symbol]: { list: ref([]), active: ref('en') } },
      },
    })

    expect(wrapper.findAll('.wx-repeater__title').map((one) => one.text())).toEqual([
      '#1 · Coffee',
      '#2 · Чай',
    ])
  })

  it('folds a row and unfolds it again', async () => {
    const wrapper = repeater({ collapsible: true, itemLabel: 'city' })
    const head = wrapper.findAll('.wx-repeater__head')[0]!

    expect(head.attributes('aria-expanded')).toBe('true')

    await head.trigger('click')
    expect(head.attributes('aria-expanded')).toBe('false')
    expect(wrapper.findAll('.wx-repeater__body')[0]!.attributes('style')).toContain('display: none')

    await head.trigger('click')
    expect(head.attributes('aria-expanded')).toBe('true')
  })

  it('starts folded when asked, and opens the row that was just added', async () => {
    const wrapper = repeater({ collapsed: true })

    expect(wrapper.findAll('.wx-repeater__head')[0]!.attributes('aria-expanded')).toBe('false')

    await wrapper.get('.wx-repeater__add').trigger('click')

    expect(wrapper.findAll('.wx-repeater__head')[2]!.attributes('aria-expanded')).toBe('true')
  })

  it('keeps the row of an item when the one above it goes', async () => {
    const wrapper = repeater({ collapsible: true, itemLabel: 'city' })

    /* Fold the second row; after the first is removed the same office is still folded. */
    await wrapper.findAll('.wx-repeater__head')[1]!.trigger('click')
    await removeButton(wrapper, 0).trigger('click')

    const head = wrapper.findAll('.wx-repeater__head')[0]!
    expect(head.text()).toBe('#1 · Lviv')
    expect(head.attributes('aria-expanded')).toBe('false')
  })

  it('leaves no grip when it cannot be sorted', () => {
    expect(repeater().findAll('.wx-sortable-list__grip')).toHaveLength(2)
    expect(repeater({ sortable: false }).findAll('.wx-sortable-list__grip')).toHaveLength(0)
  })

  it('says what an empty repeater is missing', () => {
    const wrapper = repeater({ modelValue: [], emptyText: 'No offices yet' })

    expect(wrapper.text()).toContain('No offices yet')
  })
})
