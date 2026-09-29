import { disableAutoUnmount, enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterAll, afterEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import { adminKey, adminTypes, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import { localesKey } from '@webx-ui/core'
import { coreTypes, type ScreenNode } from '@webx-ui/schema'
import { catalog } from './module'
import ProductEditorPage from './ProductEditorPage.vue'
import type { ProductDetail, ProductImage, ProductRow } from './types'

// The gallery's caption pause outlives a test that never unmounts it (docs/pitfalls).
enableAutoUnmount(afterEach)
afterAll(disableAutoUnmount)

const lamp: ProductRow = {
  id: 7,
  name: 'Desk lamp',
  sku: 'L-7',
  unit: 'pcs',
  priority: 0,
  is_published: false,
  state: 'unpublished',
  visible: false,
  category: null,
  image: null,
  url: 'https://shop.test/desk-lamp-7',
  created_at: null,
  updated_at: null,
  deleted_at: null,
}

function image(id: number, position: number): ProductImage {
  return {
    id,
    path: `catalog/0/7/${id}.jpg`,
    url: `/files/${id}.jpg`,
    thumb: null,
    alt: { en: `Picture ${id}` },
    title: {},
    width: 800,
    height: 600,
    size: 1000,
    position,
  }
}

function detail(over: Partial<ProductRow> = {}): ProductDetail {
  return {
    product: { ...lamp, ...over },
    values: { name: { en: 'Desk lamp' }, sku: 'L-7' },
    images: [image(1, 1), image(2, 2)],
  }
}

const screen: ScreenNode[] = [
  {
    id: 'tabs',
    type: 'wx-tabs',
    children: [
      {
        id: 'main',
        type: 'wx-tab',
        label: 'Main',
        children: [
          { id: 'name', type: 'wx-input', name: 'name', localized: true },
          { id: 'sku', type: 'wx-input', name: 'sku' },
        ],
      },
      {
        id: 'images-tab',
        type: 'wx-tab',
        label: 'Pictures',
        children: [{ id: 'gallery', type: 'wx-catalog-gallery' }],
      },
    ],
  },
]

async function panel(first = detail()) {
  const get = vi.fn().mockResolvedValue({ data: first })
  const put = vi.fn().mockImplementation((url: string, body: { images?: unknown[] }) =>
    Promise.resolve(
      url.endsWith('/images')
        ? {
            data: (body.images as { id: number }[]).map((one, index) => image(one.id, index + 1)),
          }
        : { data: detail({ name: 'Brass desk lamp' }) },
    ),
  )
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, put, post: vi.fn(), delete: vi.fn() },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
    types: { ...coreTypes, ...adminTypes, ...catalog().types },
    loadScreen: () => Promise.resolve(screen),
    screenPatch: () => [],
  } as unknown as AdminContext

  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/catalog/products', component: { template: '<div />' } },
      { path: '/catalog/deleted', component: { template: '<div class="deleted" />' } },
      {
        path: '/catalog/products/:id(\\d+)',
        component: ProductEditorPage,
        props: { base: '/catalog' },
      },
    ],
  })

  await router.push('/catalog/products/7')
  await router.isReady()

  const wrapper = mount(
    { template: '<router-view />' },
    {
      global: {
        plugins: [router],
        provide: {
          [adminKey as symbol]: admin,
          [i18nKey as symbol]: i18n,
          [localesKey as symbol]: {
            list: ref([{ code: 'en', name: 'English' }]),
            active: ref('en'),
          },
        },
      },
    },
  )

  await flushPromises()

  return { wrapper, get, put, router }
}

afterEach(() => {
  vi.useRealTimers()
})

describe('WxCatalogProductEditor', () => {
  it('saves the values of the screen, and nothing of the gallery', async () => {
    const { wrapper, put } = await panel()

    await wrapper.findAll('input')[0]!.setValue('Brass desk lamp')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Save')!
      .trigger('click')
    await flushPromises()

    expect(put).toHaveBeenCalledWith('/api/cms/catalog/products/7', {
      values: { name: { en: 'Brass desk lamp' }, sku: 'L-7' },
    })
  })

  it('turns a taken article number into a way to the product holding it', async () => {
    const { wrapper, put, router } = await panel()

    put.mockRejectedValueOnce({
      status: 422,
      body: {
        message: 'The article number is taken by the product Old lamp',
        errors: { sku: ['The article number is taken by the product Old lamp'] },
        meta: {
          taken_by: { id: 3, name: 'Old lamp', url: '/cms/catalog/products/3', deleted: true },
        },
      },
    })

    await wrapper.findAll('input')[1]!.setValue('OLD-3')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Save')!
      .trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('The article number is taken by the product Old lamp')

    // Deleted: it has no editor, so the way to it is «Deleted», searched by that number.
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Find it in «Deleted»')!
      .trigger('click')
    await flushPromises()

    // The number typed is not saved, and leaving says so first.
    const leave = [...document.body.querySelectorAll('button')].find(
      (button) => button.textContent?.trim() === 'Leave',
    )

    leave!.click()
    await flushPromises()

    expect(router.currentRoute.value.fullPath).toBe('/catalog/deleted?q=OLD-3')
  })

  it('opens an unpublished product on the site too — that is the trimmed page', async () => {
    const { wrapper } = await panel()

    expect(wrapper.find('a[href="https://shop.test/desk-lamp-7"]').exists()).toBe(true)
  })

  it('saves a new order of the pictures as the whole gallery at once', async () => {
    const { wrapper, put } = await panel()

    await wrapper.findAll('.wx-tabs__tab')[1]!.trigger('mousedown')
    await flushPromises()

    const grip = wrapper.find('.wx-catalog-gallery [aria-label^="Reorder"]')

    await grip.trigger('keydown', { key: ' ' })
    await grip.trigger('keydown', { key: 'ArrowDown' })
    await flushPromises()

    expect(put).toHaveBeenCalledWith('/api/cms/catalog/products/7/images', {
      images: [
        { id: 2, alt: { en: 'Picture 2' }, title: {} },
        { id: 1, alt: { en: 'Picture 1' }, title: {} },
      ],
    })
  })

  it('saves the captions a moment after the last keystroke', async () => {
    const { wrapper, put } = await panel()

    await wrapper.findAll('.wx-tabs__tab')[1]!.trigger('mousedown')
    await flushPromises()

    vi.useFakeTimers()

    await wrapper.find('.wx-catalog-gallery input').setValue('A brass lamp on a desk')
    expect(put).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(1000)
    await flushPromises()

    expect(put).toHaveBeenCalledWith('/api/cms/catalog/products/7/images', {
      images: [
        { id: 1, alt: { en: 'A brass lamp on a desk' }, title: {} },
        { id: 2, alt: { en: 'Picture 2' }, title: {} },
      ],
    })
  })
})
