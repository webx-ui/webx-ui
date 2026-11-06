import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, nextTick, ref } from 'vue'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'
import { blankValues } from './api'
import CollectionPreviewPanel from './CollectionPreviewPanel.vue'
import { provideCollectionEditor } from './editor'
import type { CollectionDetail, CollectionPreset, CollectionPreview } from './types'

const answer: CollectionPreview = {
  products: [{ id: 1, name: 'Camera', sku: 'WX-9001', image: null, price: 100, old_price: 120 }],
  count: 1,
  more: false,
  limit: 8,
  dropped: [
    { id: 2, name: 'Lens', sku: null, reason: 'status', status: 'Out of stock' },
    { id: 3, name: 'Tripod', sku: null, reason: 'unpublished', status: null },
  ],
}

function panel(reply: Partial<CollectionPreview> = {}) {
  const post = vi.fn().mockResolvedValue({ data: { ...answer, ...reply } })
  const i18n = createI18n()
  const admin = { apiPath: '/api/cms', http: { post }, i18n } as unknown as AdminContext
  const values = ref<ScreenModel>({ ...blankValues(), products: [1, 2, 3] })

  const Host = defineComponent({
    setup() {
      provideCollectionEditor({
        collection: ref<CollectionDetail | null>(null),
        values,
        locked: ref(false),
        presets: ref<CollectionPreset[]>([]),
      })

      return () => h(CollectionPreviewPanel)
    },
  })

  const wrapper = mount(Host, {
    global: { provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n } },
  })

  return { wrapper, post, values }
}

async function settle(): Promise<void> {
  await vi.advanceTimersByTimeAsync(400)
  await flushPromises()
}

beforeEach(() => vi.useFakeTimers())
afterEach(() => vi.useRealTimers())

describe('WxCatalogCollectionPreview', () => {
  it('asks on the values of the form, a moment after they change', async () => {
    const { post, values } = panel()

    expect(post).not.toHaveBeenCalled()
    await settle()
    expect(post).toHaveBeenCalledTimes(1)
    expect(post.mock.calls[0]?.[0]).toBe('/api/cms/catalog/collections/preview')

    values.value = { ...values.value, limit: 3 }
    await nextTick()
    values.value = { ...values.value, limit: 4 }
    await settle()

    expect(post).toHaveBeenCalledTimes(2)
    expect(post.mock.calls[1]?.[1]).toMatchObject({ limit: 4, products: [1, 2, 3] })
  })

  it('asks nothing when what changed decides no product', async () => {
    const { post, values } = panel()

    await settle()
    values.value = { ...values.value, title: { en: 'Picks' }, view: 'carousel' }
    await settle()

    expect(post).toHaveBeenCalledTimes(1)
  })

  it('shows the products and says why the others are out', async () => {
    const { wrapper } = panel()

    await settle()

    const text = wrapper.text()

    expect(text).toContain('Camera')
    expect(text).toContain('Products that fit: 1')
    expect(text).toContain('Cannot be bought: Out of stock')
    expect(text).toContain('Not published')
  })

  it('warns when nothing fits', async () => {
    const { wrapper } = panel({ products: [], count: 0, dropped: [] })

    await settle()

    expect(wrapper.text()).toContain('Nothing fits')
  })

  it('says a filter stopped counting', async () => {
    const { wrapper } = panel({ count: 48, more: true, dropped: [] })

    await settle()

    expect(wrapper.text()).toContain('more than 48')
  })
})
