import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import { provideOrderCard } from './card'
import OrderNotesNode from './OrderNotesNode.vue'
import type { OrderCardData, OrderNote } from './types'

function order(notes: OrderNote[], note = true, pinned: string | null = null): OrderCardData {
  return {
    id: 7,
    number: '1049',
    manager_note: pinned,
    notes,
    actions: {
      status: true,
      paid: false,
      received: false,
      cancel: false,
      details: note,
      note,
      refund: false,
      visitor: false,
      visitor_search: false,
    },
  } as unknown as OrderCardData
}

function card(data: OrderCardData, kept = true) {
  const i18n = createI18n()
  const admin = { apiPath: '/api/cms', http: {}, i18n, assetUrls: null } as unknown as AdminContext
  const note = vi.fn<(body: string) => Promise<boolean>>(() => Promise.resolve(kept))
  const pin = vi.fn<(text: string | null) => Promise<boolean>>(() => Promise.resolve(kept))

  const Host = defineComponent({
    setup() {
      provideOrderCard({
        order: ref(data),
        busy: ref(false),
        changeStatus: () => Promise.resolve(),
        mark: () => Promise.resolve(),
        cancel: () => Promise.resolve(),
        refund: () => Promise.resolve(),
        reverseRefund: () => Promise.resolve(),
        note,
        pin,
        linkVisitor: () => Promise.resolve(),
        unlinkVisitor: () => Promise.resolve(),
      })

      return () => h(OrderNotesNode)
    },
  })

  const wrapper = mount(Host, {
    global: { provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n } },
  })

  return { wrapper, note, pin }
}

const manager: OrderNote = {
  id: 11,
  body: 'Called the buyer.\nDeliver after six.',
  source: 'panel',
  author: { id: 1, name: 'Ada', avatar: null },
  created_at: '2026-10-30T10:00:00+00:00',
}

/**
 * The notes of an order are lines of its history: the feed reads them off the card, writes through
 * the card's `note()`, and never offers to change one.
 */
describe('WxOrdersNotes', () => {
  it("names the manager, an agent and a module, as the history's lines do", () => {
    const { wrapper } = card(
      order([
        manager,
        { ...manager, id: 12, source: 'mcp', author: { id: 1, name: 'Ada', avatar: null } },
        { ...manager, id: 13, source: 'delivery', author: null },
      ]),
    )

    const names = wrapper.findAll('.wx-note__head').map((head) => head.find('.wx-text').text())
    expect(names).toEqual(['Ada', 'Agent: Ada', 'delivery'])
    expect(wrapper.find('.wx-note__text').element.textContent).toBe(
      'Called the buyer.\nDeliver after six.',
    )
    expect(wrapper.find('.wx-note__head button').exists()).toBe(false)
  })

  it('writes through the card and empties the box once the note is kept', async () => {
    const { wrapper, note } = card(order([]))

    await wrapper.find('.wx-notes__new textarea').setValue('  Reserved until Friday.  ')
    await wrapper.find('.wx-notes__new .wx-button').trigger('click')
    await flushPromises()

    expect(note).toHaveBeenCalledWith('Reserved until Friday.')
    expect(wrapper.find<HTMLTextAreaElement>('.wx-notes__new textarea').element.value).toBe('')
  })

  it('keeps what was typed when the note was not kept', async () => {
    const { wrapper } = card(order([]), false)

    await wrapper.find('.wx-notes__new textarea').setValue('Reserved until Friday.')
    await wrapper.find('.wx-notes__new .wx-button').trigger('click')
    await flushPromises()

    expect(wrapper.find<HTMLTextAreaElement>('.wx-notes__new textarea').element.value).toBe(
      'Reserved until Friday.',
    )
  })

  it('lets a reader without `orders.manage` read the notes, with no box', () => {
    const { wrapper } = card(order([manager], false))

    expect(wrapper.findAll('.wx-note')).toHaveLength(1)
    expect(wrapper.find('.wx-notes__new').exists()).toBe(false)
  })
})

/**
 * The pinned note (decision 91): the order's one standing line, over the feed and apart from it,
 * changed in place through the card's `pin()`; emptied, it is unpinned.
 */
describe('WxOrdersNotes — the pinned note', () => {
  it('stands over the feed with its caption, the line breaks kept', () => {
    const { wrapper } = card(order([manager], true, 'Regular buyer.\nCall in the morning.'))

    const plate = wrapper.find('.wx-orders-notes__pinned')
    expect(plate.text()).toContain('Pinned')
    expect(wrapper.find('.wx-orders-notes__pinned-text').element.textContent).toBe(
      'Regular buyer.\nCall in the morning.',
    )
    // Over the feed, not in it.
    const html = wrapper.html()
    expect(html.indexOf('wx-orders-notes__pinned')).toBeLessThan(html.indexOf('wx-note__text'))
  })

  it('without one, offers to pin a note to whoever may manage the order', async () => {
    const { wrapper, pin } = card(order([]))

    expect(wrapper.find('.wx-orders-notes__pinned').exists()).toBe(false)
    await wrapper.find('.wx-orders-notes__pin').trigger('click')
    await wrapper.find('.wx-orders-notes__pin-editor textarea').setValue('  VIP  ')
    await wrapper.find('.wx-orders-notes__pin-editor .wx-button').trigger('click')
    await flushPromises()

    expect(pin).toHaveBeenCalledWith('VIP')
    expect(wrapper.find('.wx-orders-notes__pin-editor').exists()).toBe(false)
  })

  it('emptied, is unpinned', async () => {
    const { wrapper, pin } = card(order([], true, 'VIP'))

    await wrapper.find('.wx-orders-notes__pencil').trigger('click')
    await wrapper.find('.wx-orders-notes__pin-editor textarea').setValue('   ')
    await wrapper.find('.wx-orders-notes__pin-editor .wx-button').trigger('click')
    await flushPromises()

    expect(pin).toHaveBeenCalledWith(null)
  })

  it('keeps the editor open when the change was not kept', async () => {
    const { wrapper } = card(order([], true, 'VIP'), false)

    await wrapper.find('.wx-orders-notes__pencil').trigger('click')
    await wrapper.find('.wx-orders-notes__pin-editor textarea').setValue('VIP, call first')
    await wrapper.find('.wx-orders-notes__pin-editor .wx-button').trigger('click')
    await flushPromises()

    expect(wrapper.find('.wx-orders-notes__pin-editor').exists()).toBe(true)
  })

  it('is read without `orders.manage`, with no pencil and no link to pin one', () => {
    const { wrapper } = card(order([], false, 'VIP'))

    expect(wrapper.find('.wx-orders-notes__pinned').exists()).toBe(true)
    expect(wrapper.find('.wx-orders-notes__pencil').exists()).toBe(false)

    const empty = card(order([], false))
    expect(empty.wrapper.find('.wx-orders-notes__pin').exists()).toBe(false)
  })
})
