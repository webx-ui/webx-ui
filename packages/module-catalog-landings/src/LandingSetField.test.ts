import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, nextTick, ref } from 'vue'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import { WxCatalogFacetSet } from '@webx-ui/module-catalog'
import type { ScreenModel } from '@webx-ui/schema'
import { provideLandingEditor } from './editor'
import LandingSetField from './LandingSetField.vue'
import type { BaseFacet, LandingDetail, LandingFilters, SetCount } from './types'

const brand: BaseFacet = {
  key: 'brand',
  label: 'Brand',
  kind: 'terms',
  min: null,
  max: null,
  values: [{ value: '1', label: 'Acme', count: 4 }],
}

function field(set: LandingFilters | null, answer: Partial<SetCount> = {}) {
  const get = vi.fn().mockResolvedValue({ data: [brand] })
  const post = vi.fn().mockResolvedValue({
    data: { count: 7, taken: null, suggested: { en: 'acme' }, ...answer },
  })
  const i18n = createI18n()
  const admin = { apiPath: '/api/cms', http: { get, post }, i18n } as unknown as AdminContext
  const model = ref(set)
  const values = ref<ScreenModel>({ category_id: 5 })
  const suggested = ref<Record<string, string>>({})
  const landing = ref({
    id: 9,
    chips: [{ key: 'brand', label: 'Brand', text: 'Old brand' }],
  } as unknown as LandingDetail)

  const Host = defineComponent({
    setup() {
      provideLandingEditor({
        landing,
        values,
        locked: ref(false),
        suggested,
        list: '/catalog/landings',
      })

      return () =>
        h(LandingSetField, {
          modelValue: model.value,
          'onUpdate:modelValue': (next: LandingFilters | null | undefined) => {
            model.value = next ?? null
          },
        })
    },
  })

  const wrapper = mount(Host, {
    global: {
      provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
      stubs: { WxLink: true },
    },
  })

  return { wrapper, get, post, model, values, suggested }
}

beforeEach(() => vi.useFakeTimers())
afterEach(() => vi.useRealTimers())

async function settle(): Promise<void> {
  await vi.advanceTimersByTimeAsync(400)
  await flushPromises()
}

describe('wx-catalog-landing-set', () => {
  it('hands the shared field the facets of the base', async () => {
    const { wrapper, get } = field(null)

    await settle()

    expect(get.mock.calls[0]?.[0]).toContain('facets')
    expect(wrapper.findComponent(WxCatalogFacetSet).props('facets')).toEqual([brand])
  })

  it('asks the facets again when the base changes', async () => {
    const { get, values } = field(null)

    await settle()
    values.value = { category_id: 6 }
    await nextTick()
    await settle()

    expect(get).toHaveBeenCalledTimes(2)
  })

  it('counts the set, except the landing itself, and shares the suggestion', async () => {
    const { wrapper, post, suggested } = field({ brand: { values: ['1'] } })

    await settle()

    expect(post.mock.calls[0]?.[1]).toEqual({
      category_id: 5,
      filters: { brand: { values: ['1'] } },
      except: 9,
    })
    expect(wrapper.findComponent(WxCatalogFacetSet).props('count')).toBe(7)
    expect(suggested.value).toEqual({ en: 'acme' })
  })

  it('does not count a set that says nothing', async () => {
    const { post } = field({ brand: { values: [] } })

    await settle()

    expect(post).not.toHaveBeenCalled()
  })

  it('takes the shared field’s edits as its value', async () => {
    const { wrapper, model } = field(null)

    await settle()
    wrapper.findComponent(WxCatalogFacetSet).vm.$emit('update:modelValue', {
      brand: { values: ['1'] },
    })

    expect(model.value).toEqual({ brand: { values: ['1'] } })
  })

  it('warns when another landing holds the set', async () => {
    const { wrapper } = field(
      { brand: { values: ['1'] } },
      { taken: { id: 3, name: 'Acme laptops' } },
    )

    await settle()

    expect(wrapper.text()).toContain('Acme laptops')
  })

  it('names a value the base no longer counts by the saved chip', async () => {
    const { wrapper } = field({ brand: { values: ['8'] } })

    await settle()

    const label = wrapper.findComponent(WxCatalogFacetSet).props('valueLabel') as (
      key: string,
      value: string,
    ) => string | undefined

    expect(label('brand', '8')).toBe('Old brand')
    expect(label('color', '8')).toBeUndefined()
  })
})
