import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { ref } from 'vue'
import { feedEditorKey, type FeedEditorContext } from './editor'
import FeedStockMapField from './FeedStockMapField.vue'
import type { FeedsMeta, FeedValues } from './types'

function context(stock: boolean): FeedEditorContext {
  return {
    locked: ref(false),
    values: ref({ options: {} } as FeedValues),
    meta: ref({
      stock,
      stock_statuses: stock
        ? [
            { code: 'in-stock', title: 'In stock', is_purchasable: true },
            { code: 'on-order', title: 'On order', is_purchasable: false },
          ]
        : [],
    } as unknown as FeedsMeta),
  } as unknown as FeedEditorContext
}

function field(stock: boolean, modelValue: Record<string, string> | null = null) {
  return mount(FeedStockMapField, {
    props: { modelValue },
    global: { provide: { [feedEditorKey as symbol]: context(stock) } },
  })
}

describe('FeedStockMapField', () => {
  it('draws a row for each status', () => {
    expect(field(true).findAll('.wx-catalog-feed-stock__row')).toHaveLength(2)
  })

  it('warns of a value Google takes only with a date', () => {
    expect(field(true).find('.wx-alert').exists()).toBe(false)
    expect(field(true, { 'on-order': 'backorder' }).find('.wx-alert').exists()).toBe(true)
  })

  it('offers one default for all products on a site with no statuses', () => {
    expect(field(false).findAll('.wx-catalog-feed-stock__row')).toHaveLength(1)
  })
})
