import { describe, expect, it } from 'vitest'
import { mount, type VueWrapper } from '@vue/test-utils'
import { h, nextTick, withDirectives } from 'vue'
import WxSelectionArea from './SelectionArea.vue'
import { vWxSelect } from './directive'
import type { SelectionValue } from './types'

/*
 * A two by two grid, a hundred by fifty each, inside an area three hundred wide. jsdom
 * lays nothing out, so the rectangles are told rather than measured — which is enough for
 * the arithmetic, and no use at all for the layout itself. That is checked in a browser.
 */
const boxes: Record<string, [number, number, number, number]> = {
  a: [0, 0, 100, 50],
  b: [100, 0, 200, 50],
  c: [0, 50, 100, 100],
  d: [100, 50, 200, 100],
}

function rect(el: Element, [left, top, right, bottom]: [number, number, number, number]) {
  el.getBoundingClientRect = () =>
    ({
      left,
      top,
      right,
      bottom,
      width: right - left,
      height: bottom - top,
      x: left,
      y: top,
      toJSON: () => ({}),
    }) as DOMRect
}

/*
 * jsdom has no PointerEvent, so a mouse event is sent under the pointer event's name and
 * told what pointer it came from. Test Utils cannot do this one: it assigns `clientX` to
 * an event that has only a getter for it.
 */
type PointerInit = MouseEventInit & { pointerId?: number; pointerType?: string }

function fire(el: Element, type: string, init: PointerInit = {}) {
  const event = new MouseEvent(type, { bubbles: true, cancelable: true, ...init })
  Object.defineProperty(event, 'pointerId', { get: () => init.pointerId ?? 1 })
  Object.defineProperty(event, 'pointerType', { get: () => init.pointerType ?? 'mouse' })
  el.dispatchEvent(event)
  return nextTick()
}

function area(props: Record<string, unknown> = {}, extra?: () => unknown) {
  const held: { wrapper?: VueWrapper } = {}

  const wrapper = mount(WxSelectionArea, {
    attachTo: document.body,
    props: {
      /* The frame loop is for edge scrolling, and there is no scrolling here to do. */
      edgeScroll: 0,
      /* Held the way a parent holds it, so a second gesture builds on the first. */
      modelValue: [],
      'onUpdate:modelValue': (value: SelectionValue[]) =>
        held.wrapper?.setProps({ modelValue: value }),
      ...props,
    },
    slots: {
      default: () => [
        ...Object.keys(boxes).map((key) => h('div', { 'data-wx-selectable': key, class: 'item' })),
        extra?.(),
      ],
    },
  })

  held.wrapper = wrapper

  rect(wrapper.element, [0, 0, 300, 200])
  const items = wrapper.findAll('.item')
  Object.keys(boxes).forEach((key, index) => rect(items[index].element, boxes[key]))

  return wrapper
}

function item(wrapper: VueWrapper, index: number) {
  return wrapper.findAll('.item')[index].element
}

function down(el: Element, x: number, y: number, keys: PointerInit = {}) {
  return fire(el, 'pointerdown', { clientX: x, clientY: y, button: 0, ...keys })
}

function move(el: Element, x: number, y: number, keys: PointerInit = {}) {
  return fire(el, 'pointermove', { clientX: x, clientY: y, ...keys })
}

function up(el: Element, x: number, y: number, keys: PointerInit = {}) {
  return fire(el, 'pointerup', { clientX: x, clientY: y, ...keys })
}

async function click(el: Element, x: number, y: number, keys: PointerInit = {}) {
  await down(el, x, y, keys)
  await up(el, x, y, keys)
}

const finger = { pointerType: 'touch' } as const

function chosen(wrapper: VueWrapper) {
  return wrapper.emitted('update:modelValue')?.at(-1)?.[0]
}

describe('WxSelectionArea', () => {
  it('catches what the box is drawn over', async () => {
    const wrapper = area()
    const el = wrapper.element

    await down(el, 10, 10)
    await move(el, 90, 40)
    expect(chosen(wrapper)).toEqual(['a'])

    await move(el, 150, 40)
    expect(chosen(wrapper)).toEqual(['a', 'b'])

    await move(el, 150, 90)
    expect(chosen(wrapper)).toEqual(['a', 'b', 'c', 'd'])

    await up(el, 150, 90)
    expect(wrapper.emitted('end')?.at(-1)?.[0]).toEqual(['a', 'b', 'c', 'd'])
  })

  it('lets go of what the box is dragged back off', async () => {
    const wrapper = area()
    const el = wrapper.element

    await down(el, 10, 10)
    await move(el, 150, 40)
    await move(el, 90, 40)

    expect(chosen(wrapper)).toEqual(['a'])
  })

  it('draws the box from any corner', async () => {
    const wrapper = area()
    const el = wrapper.element

    await down(el, 190, 90)
    await move(el, 110, 60)

    expect(chosen(wrapper)).toEqual(['d'])
  })

  it('can be asked to cover an item rather than touch it', async () => {
    const wrapper = area({ match: 'contain' })
    const el = wrapper.element

    await down(el, 0, 0)
    await move(el, 150, 60)

    expect(chosen(wrapper)).toEqual(['a'])
  })

  it('shows the box while it is being drawn, and not after', async () => {
    const wrapper = area()
    const el = wrapper.element

    expect(wrapper.find('.wx-selection-area__box').exists()).toBe(false)

    await down(el, 10, 10)
    await move(el, 150, 90)

    const box = wrapper.find('.wx-selection-area__box')
    expect(box.attributes('style')).toContain('left: 10px')
    expect(box.attributes('style')).toContain('width: 140px')

    await up(el, 150, 90)
    expect(wrapper.find('.wx-selection-area__box').exists()).toBe(false)
  })

  it('is a click until the pointer has travelled', async () => {
    const wrapper = area()
    const el = wrapper.element

    await down(el, 10, 10)
    await move(el, 12, 11)

    expect(wrapper.emitted('start')).toBeUndefined()
    expect(wrapper.find('.wx-selection-area__box').exists()).toBe(false)
  })

  it('picks one item on a click, and clears on a click beside them', async () => {
    const wrapper = area()

    await click(item(wrapper, 1), 120, 20)
    expect(chosen(wrapper)).toEqual(['b'])

    await click(wrapper.element, 250, 150)
    expect(chosen(wrapper)).toEqual([])
  })

  it('adds one at a time with ctrl, and a run of them with shift', async () => {
    const wrapper = area()

    await click(item(wrapper, 0), 10, 10)

    await click(item(wrapper, 2), 10, 60, { ctrlKey: true })
    expect(chosen(wrapper)).toEqual(['a', 'c'])

    /* Again on the same one takes it back out. */
    await click(item(wrapper, 2), 10, 60, { ctrlKey: true })
    expect(chosen(wrapper)).toEqual(['a'])

    /* The run reaches back to the last one clicked, not to the first one selected. */
    await click(item(wrapper, 3), 110, 60, { shiftKey: true })
    expect(chosen(wrapper)).toEqual(['a', 'c', 'd'])
  })

  it('adds to the selection when a drag is held with a modifier', async () => {
    const wrapper = area({ modelValue: ['a'] })
    const el = wrapper.element

    await down(el, 110, 60, { shiftKey: true })
    await move(el, 190, 90)

    expect(chosen(wrapper)).toEqual(['a', 'd'])
  })

  it('takes away when a drag is held with alt', async () => {
    const wrapper = area({ modelValue: ['a', 'b', 'c'] })
    const el = wrapper.element

    await down(el, 10, 10, { altKey: true })
    await move(el, 90, 40)

    expect(chosen(wrapper)).toEqual(['b', 'c'])
  })

  /* ----------------------------------------------------------------- touch */

  it('picks the item a finger taps, with no box turned on', async () => {
    const wrapper = area()

    await click(item(wrapper, 1), 120, 20, finger)

    expect(chosen(wrapper)).toEqual(['b'])
    expect(wrapper.find('.wx-selection-area__box').exists()).toBe(false)
  })

  it('builds a selection one tap at a time, and takes one back out', async () => {
    const wrapper = area()

    await click(item(wrapper, 1), 120, 20, finger)
    await click(item(wrapper, 2), 10, 60, finger)
    expect(chosen(wrapper)).toEqual(['b', 'c'])

    /* Again on the same one takes it back out — there is no modifier to do it with. */
    await click(item(wrapper, 2), 10, 60, finger)
    expect(chosen(wrapper)).toEqual(['b'])

    /* And the background is still the way back to none. */
    await click(wrapper.element, 250, 150, finger)
    expect(chosen(wrapper)).toEqual([])
  })

  it('leaves the selection alone when a finger travels — that gesture is a scroll', async () => {
    const wrapper = area()

    await click(item(wrapper, 1), 120, 20, finger)

    await down(item(wrapper, 0), 10, 10, finger)
    await move(wrapper.element, 150, 90, finger)
    await up(wrapper.element, 150, 90, finger)

    expect(chosen(wrapper)).toEqual(['b'])
    expect(wrapper.emitted('start')).toBeUndefined()
  })

  it('draws the box with a finger once touch is asked for', async () => {
    const wrapper = area({ touch: true })

    await down(wrapper.element, 10, 10, finger)
    await move(wrapper.element, 150, 90, finger)

    expect(chosen(wrapper)).toEqual(['a', 'b', 'c', 'd'])
    expect(wrapper.find('.wx-selection-area__box').exists()).toBe(true)
    expect(wrapper.classes()).toContain('is-touch')
  })

  it('chooses nothing when the browser takes the gesture back', async () => {
    const wrapper = area()

    await down(item(wrapper, 1), 120, 20, finger)
    await fire(wrapper.element, 'pointercancel', { clientX: 120, clientY: 20, ...finger })

    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('leaves a drag that begins on a control alone', async () => {
    const wrapper = area({}, () => h('button', { class: 'act' }, 'Delete'))

    await down(wrapper.find('.act').element, 10, 10)
    await move(wrapper.element, 150, 90)

    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('does nothing at all when disabled', async () => {
    const wrapper = area({ disabled: true })
    const el = wrapper.element

    await down(el, 10, 10)
    await move(el, 150, 90)

    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('takes every item on ctrl+a and drops them on escape', async () => {
    const wrapper = area()

    await wrapper.trigger('keydown', { key: 'a', ctrlKey: true })
    expect(chosen(wrapper)).toEqual(['a', 'b', 'c', 'd'])

    await wrapper.trigger('keydown', { key: 'Escape' })
    expect(chosen(wrapper)).toEqual([])
  })

  it('puts back what a drag had taken when escape ends it', async () => {
    const wrapper = area({ modelValue: ['c'] })
    const el = wrapper.element

    await down(el, 10, 10)
    await move(el, 90, 40)
    expect(chosen(wrapper)).toEqual(['a'])

    await wrapper.trigger('keydown', { key: 'Escape' })
    expect(chosen(wrapper)).toEqual(['c'])
    expect(wrapper.find('.wx-selection-area__box').exists()).toBe(false)
  })

  it('keeps the type the directive was given', async () => {
    const wrapper = mount(WxSelectionArea, {
      attachTo: document.body,
      props: { edgeScroll: 0 },
      slots: {
        default: () =>
          [7, 8].map((id) => withDirectives(h('div', { class: 'item' }), [[vWxSelect, id]])),
      },
    })

    rect(wrapper.element, [0, 0, 300, 200])
    rect(item(wrapper, 0), boxes.a)
    rect(item(wrapper, 1), boxes.b)

    await down(wrapper.element, 10, 10)
    await move(wrapper.element, 150, 40)

    expect(chosen(wrapper)).toEqual([7, 8])
  })
})
