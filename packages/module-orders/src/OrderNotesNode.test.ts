import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import { provideOrderCard } from './card'
import OrderNotesNode from './OrderNotesNode.vue'
import type { OrderCardData, OrderNote } from './types'

function order(notes: OrderNote[], note = true): OrderCardData {
  return {
    id: 7,
    number: '1049',
    notes,
    actions: {
      status: true,
      paid: false,
      received: false,
      cancel: false,
      details: true,
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

  const Host = defineComponent({
    setup() {
      provideOrderCard({
        order: ref(data),
        busy: ref(false),
        changeStatus: () => Promise.resolve(),
        mark: () => Promise.resolve(),
        cancel: () => Promise.resolve(),
        refund: () => Promise.resolve(),
        note,
        linkVisitor: () => Promise.resolve(),
        unlinkVisitor: () => Promise.resolve(),
      })

      return () => h(OrderNotesNode)
    },
  })

  const wrapper = mount(Host, {
    global: { provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n } },
  })

  return { wrapper, note }
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
