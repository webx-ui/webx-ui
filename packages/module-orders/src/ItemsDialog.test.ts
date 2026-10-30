import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import ItemsDialog from './ItemsDialog.vue'
import type { ItemsEditPreview, OrderCardData, OrderLine } from './types'

function line(patch: Partial<OrderLine> = {}): OrderLine {
  return {
    id: 11,
    name: 'Item A',
    sku: 'A-1',
    options: null,
    image: null,
    label: null,
    price: '$51.90',
    qty: 2,
    refunded_qty: 0,
    total: '$103.80',
    discounts: [],
    children: [],
    ...patch,
  }
}

function order(): OrderCardData {
  return {
    id: 7,
    number: '1045',
    locale: 'en',
    currency: 'USD',
    items_revision: 0,
    items: [line(), line({ id: 12, name: 'A product with a much longer name', price: '$4.00' })],
    totals: { paid: null },
  } as unknown as OrderCardData
}

/** The service's dry run of `qty`: each line at its price, the totals summed. */
function preview(qty: Record<number, number>): ItemsEditPreview {
  const totals = (subtotal: number) => ({
    subtotal,
    subtotal_formatted: `$${(subtotal / 100).toFixed(2)}`,
    adjustments_total: 0,
    adjustments_total_formatted: '$0.00',
    total: subtotal,
    total_formatted: `$${(subtotal / 100).toFixed(2)}`,
    tenders_total: 0,
    tenders_total_formatted: '$0.00',
    payable: subtotal,
    payable_formatted: `$${(subtotal / 100).toFixed(2)}`,
  })
  const to = (qty[11] ?? 2) * 5190

  return {
    changes: [
      {
        op: 'qty',
        item_id: 11,
        name: 'Item A',
        options_label: null,
        price: 5190,
        price_formatted: '$51.90',
        qty_from: 2,
        qty_to: qty[11] ?? 2,
        total_from: 10380,
        total_from_formatted: '$103.80',
        total_to: to,
        total_to_formatted: `$${(to / 100).toFixed(2)}`,
      },
    ],
    adjustments: [],
    before: totals(10380 + 800),
    after: totals(to + 800),
    balance: { kind: null, due: 0, over: 0 },
    warnings: [],
    refusal: null,
    field: null,
    message: null,
    revision: null,
    dry_run: true,
  } as unknown as ItemsEditPreview
}

async function open() {
  const i18n = createI18n()
  const post = vi.fn((_url: string, body: { qty: Record<number, number> }) =>
    Promise.resolve({ data: preview(body.qty) }),
  )
  const admin = {
    apiPath: '/api/cms',
    http: { post, put: vi.fn() },
    i18n,
  } as unknown as AdminContext

  const wrapper = mount(ItemsDialog, {
    props: { order: order() },
    attachTo: document.body,
    global: { provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n } },
  })

  await flushPromises()
  await nextTick()

  return { wrapper, post }
}

const rows = () => [...document.querySelectorAll<HTMLElement>('[data-test="edit-line"]')]
const qty = (row: HTMLElement) =>
  row.querySelector<HTMLInputElement>('input[data-test="edit-qty"]')!
const total = (row: HTMLElement) =>
  row.querySelector('[data-test="edit-line-total"]')!.textContent!.replace(/\s+/g, ' ').trim()
const save = () => document.querySelector<HTMLButtonElement>('[data-test="edit-save"]')!

/** Past the editor's debounce, and the answer of the count it sent. */
async function settle(): Promise<void> {
  await new Promise((resolve) => setTimeout(resolve, 300))
  await flushPromises()
  await nextTick()
}

async function type(input: HTMLInputElement, value: string): Promise<void> {
  input.value = value
  input.dispatchEvent(new Event('input'))
  await nextTick()
}

afterEach(() => {
  document.body.innerHTML = ''
})

describe('ItemsDialog', () => {
  it('keeps the quantities in one column whatever the names beside them', async () => {
    const { wrapper } = await open()

    const list = document.querySelector('.wx-orders-edit__list')!
    expect(rows()).toHaveLength(2)
    // Every line is a subgrid of the list's own grid: the columns are the list's, not the line's.
    expect(list.contains(rows()[0]!)).toBe(true)
    expect(rows().every((row) => row.querySelector('.wx-orders-edit__qty-cell'))).toBe(true)
    // The remove button comes last in the line, after the money.
    expect(rows()[0]!.lastElementChild!.classList.contains('wx-orders-edit__remove')).toBe(true)

    wrapper.unmount()
  })

  it('does not take a line out while its quantity is being typed, and brings it back as typed', async () => {
    const { wrapper, post } = await open()
    const [row] = rows()
    const input = qty(row!)

    input.dispatchEvent(new FocusEvent('focus'))
    await type(input, '')
    await settle()

    // Emptied to type another number: not a line taken out, nothing counted, the total stays.
    expect(row!.textContent).not.toContain('Taken out')
    expect(post).not.toHaveBeenCalled()
    expect(total(row!)).toBe('$103.80')
    expect(save().disabled).toBe(true)
    expect(document.querySelector('[data-test="edit-blocked"]')!.textContent).toContain(
      'Enter the quantity',
    )

    await type(input, '3')
    await settle()

    expect(post).toHaveBeenCalledTimes(1)
    expect(post.mock.calls[0]![1]).toMatchObject({ qty: { 11: 3 }, remove: [] })
    expect(total(row!)).toBe('$103.80 $155.70')
    // No reason given: the edit may be saved all the same (decision 103).
    expect(save().disabled).toBe(false)

    input.dispatchEvent(new FocusEvent('blur'))
    await nextTick()
    expect(input.value).toBe('3')

    // Taken out with its button, and brought back: at the 3 typed, not at the 2 placed.
    row!
      .querySelector<HTMLButtonElement>(
        '[data-test="edit-take"] button, button[data-test="edit-take"]',
      )!
      .click()
    await nextTick()
    expect(row!.textContent).toContain('Taken out')

    row!.querySelector<HTMLButtonElement>('[data-test="edit-back"]')!.click()
    await nextTick()
    await settle()

    expect(row!.textContent).not.toContain('Taken out')
    expect(qty(row!).value).toBe('3')
    expect(total(row!)).toBe('$103.80 $155.70')

    wrapper.unmount()
  })

  it('takes a line out at a 0 typed once the field is left, and an emptied field gets its number back', async () => {
    const { wrapper } = await open()
    const [row] = rows()
    let input = qty(row!)

    input.dispatchEvent(new FocusEvent('focus'))
    await type(input, '')
    input.dispatchEvent(new FocusEvent('blur'))
    await nextTick()
    await nextTick()
    expect(qty(row!).value).toBe('2')

    input = qty(row!)
    input.dispatchEvent(new FocusEvent('focus'))
    await type(input, '0')
    expect(row!.textContent).not.toContain('Taken out')

    input.dispatchEvent(new FocusEvent('blur'))
    await nextTick()
    await nextTick()
    expect(row!.textContent).toContain('Taken out')

    row!.querySelector<HTMLButtonElement>('[data-test="edit-back"]')!.click()
    await nextTick()
    expect(qty(row!).value).toBe('2')

    wrapper.unmount()
  })
})
