import { describe, expect, it } from 'vitest'
import { mount, type VueWrapper } from '@vue/test-utils'
import WxTransfer from './Transfer.vue'
import type { TransferItem, TransferValue } from './types'

const items: TransferItem[] = [
  { value: 1, label: 'Editor' },
  { value: 2, label: 'Author', description: 'Writes and publishes their own' },
  { value: 3, label: 'Reviewer' },
  { value: 4, label: 'Owner', disabled: true },
]

function transfer(props: Record<string, unknown> = {}) {
  const held: { wrapper?: VueWrapper } = {}

  const wrapper = mount(WxTransfer, {
    props: {
      items,
      /* Held the way a parent holds it, so a second move builds on the first. */
      modelValue: [],
      'onUpdate:modelValue': (value: TransferValue[]) =>
        held.wrapper?.setProps({ modelValue: value }),
      ...props,
    },
  })

  held.wrapper = wrapper
  return wrapper
}

function panel(wrapper: VueWrapper, side: 0 | 1) {
  return wrapper.findAll('.wx-transfer__panel')[side]
}

function labels(wrapper: VueWrapper, side: 0 | 1) {
  return panel(wrapper, side)
    .findAll('.wx-transfer__label')
    .map((label) => label.text())
}

function rows(wrapper: VueWrapper, side: 0 | 1) {
  return panel(wrapper, side).findAll('.wx-transfer__item input[type=checkbox]')
}

function buttons(wrapper: VueWrapper) {
  return wrapper.findAll('.wx-transfer__controls button')
}

describe('WxTransfer', () => {
  it('puts everything on the left until it is chosen', () => {
    const wrapper = transfer()

    expect(labels(wrapper, 0)).toEqual(['Editor', 'Author', 'Reviewer', 'Owner'])
    expect(labels(wrapper, 1)).toEqual([])
    expect(panel(wrapper, 1).get('.wx-transfer__empty').text()).toBe('Nothing here')
  })

  it('shows the right panel in the order the model holds', () => {
    const wrapper = transfer({ modelValue: [3, 1] })

    expect(labels(wrapper, 1)).toEqual(['Reviewer', 'Editor'])
    expect(labels(wrapper, 0)).toEqual(['Author', 'Owner'])
  })

  it('moves what is ticked, and says which way it went', async () => {
    const wrapper = transfer()

    await rows(wrapper, 0)[0].setValue(true)
    await rows(wrapper, 0)[2].setValue(true)
    expect(panel(wrapper, 0).get('.wx-transfer__count').text()).toBe('2/4')

    await buttons(wrapper)[0].trigger('click')

    expect(labels(wrapper, 1)).toEqual(['Editor', 'Reviewer'])
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([[1, 3]])
    expect(wrapper.emitted('change')?.at(-1)?.[0]).toEqual({ values: [1, 3], to: 'right' })
    expect(wrapper.get('.wx-transfer__live').text()).toBe('2 moved to Selected.')
  })

  it('unticks what it has moved', async () => {
    const wrapper = transfer()

    await rows(wrapper, 0)[0].setValue(true)
    await buttons(wrapper)[0].trigger('click')

    expect(panel(wrapper, 0).get('.wx-transfer__count').text()).toBe('0/3')
    expect(panel(wrapper, 1).get('.wx-transfer__count').text()).toBe('0/1')
  })

  it('sends them back', async () => {
    const wrapper = transfer({ modelValue: [1, 2] })

    await rows(wrapper, 1)[1].setValue(true)
    await buttons(wrapper)[1].trigger('click')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([[1]])
    expect(wrapper.emitted('change')?.at(-1)?.[0]).toEqual({ values: [2], to: 'left' })
  })

  it('leaves a disabled row where it is', async () => {
    const wrapper = transfer()

    const owner = rows(wrapper, 0)[3]
    expect(owner.attributes('disabled')).toBeDefined()

    /* Even if something ticks it anyway, it is not among the movable. */
    await wrapper.findAll('.wx-transfer__item')[3].trigger('dblclick')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('moves one row on a double-click', async () => {
    const wrapper = transfer()

    await wrapper.findAll('.wx-transfer__item')[1].trigger('dblclick')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([[2]])
    expect(labels(wrapper, 1)).toEqual(['Author'])
  })

  it('keeps the buttons off until there is something to move', async () => {
    const wrapper = transfer()

    expect(buttons(wrapper)[0].attributes('disabled')).toBeDefined()
    expect(buttons(wrapper)[1].attributes('disabled')).toBeDefined()

    await rows(wrapper, 0)[0].setValue(true)
    expect(buttons(wrapper)[0].attributes('disabled')).toBeUndefined()
  })

  it('searches a panel, and ticks only what the search left showing', async () => {
    const wrapper = transfer({ searchable: true })

    await panel(wrapper, 0).get('input[type=search]').setValue('rev')
    expect(labels(wrapper, 0)).toEqual(['Reviewer'])

    await panel(wrapper, 0).get('.wx-transfer__head input[type=checkbox]').setValue(true)
    expect(panel(wrapper, 0).get('.wx-transfer__count').text()).toBe('1/4')

    await buttons(wrapper)[0].trigger('click')
    expect(labels(wrapper, 1)).toEqual(['Reviewer'])
  })

  it('searches the description as well as the label', async () => {
    const wrapper = transfer({ searchable: true })

    await panel(wrapper, 0).get('input[type=search]').setValue('publishes')

    expect(labels(wrapper, 0)).toEqual(['Author'])
  })

  it('moves nothing when disabled', async () => {
    const wrapper = transfer({ disabled: true, modelValue: [1] })

    await wrapper.findAll('.wx-transfer__item')[0].trigger('dblclick')

    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    expect(buttons(wrapper)[0].attributes('disabled')).toBeDefined()
  })
})
