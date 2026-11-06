import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { WxBadge, WxInputNumber, WxPopconfirm, WxSelect } from '@webx-ui/core'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import FacetSetField from './FacetSetField.vue'
import { cleanSet } from './filters'
import type { BaseFacet, FacetSetValue } from './types'

const facets: BaseFacet[] = [
  {
    key: 'brand',
    label: 'Brand',
    kind: 'terms',
    min: null,
    max: null,
    values: [
      { value: '1', label: 'Acme', count: 4 },
      { value: '2', label: 'Globex', count: 0 },
    ],
  },
  { key: 'price', label: 'Price', kind: 'range', min: 10, max: 900, values: [] },
]

function field(modelValue: FacetSetValue | null, props: Record<string, unknown> = {}) {
  return mount(FacetSetField, { props: { modelValue, facets, ...props } })
}

/** Mounted inside a panel, so that the words are the words and not their keys. */
function worded(props: Record<string, unknown>) {
  const i18n = createI18n()

  return mount(FacetSetField, {
    props: { facets, ...props },
    global: {
      provide: {
        [adminKey as symbol]: { i18n } as unknown as AdminContext,
        [i18nKey as symbol]: i18n,
      },
    },
  })
}

function hints(wrapper: ReturnType<typeof worded>, key: string): (string | undefined)[] {
  return wrapper
    .find(`[data-facet="${key}"]`)
    .findAll('input')
    .map((input) => input.attributes('placeholder'))
}

function written(wrapper: ReturnType<typeof field>): unknown {
  return wrapper.emitted('update:modelValue')?.at(-1)?.[0]
}

afterEach(() => vi.unstubAllGlobals())

describe('WxCatalogFacetSet', () => {
  it('asks nothing of the server: it mounts outside a panel and fetches nothing', () => {
    const fetch = vi.fn()

    vi.stubGlobal('fetch', fetch)

    const wrapper = field({ brand: { values: ['1'] } }, { count: 3 })

    expect(wrapper.find('[data-facet="brand"]').exists()).toBe(true)
    expect(fetch).not.toHaveBeenCalled()
  })

  it('offers the facets the set does not hold, and adds each with an empty choice', () => {
    const wrapper = field({ brand: { values: ['1'] } })
    const add = wrapper.findAllComponents(WxSelect).at(-1)!

    expect(add.props('options')).toEqual([{ value: 'price', label: 'Price' }])

    add.vm.$emit('update:modelValue', 'price')

    expect(written(wrapper)).toEqual({
      brand: { values: ['1'] },
      price: { min: null, max: null },
    })
  })

  it('adds a list facet with no values yet', () => {
    const wrapper = field(null)

    wrapper.findAllComponents(WxSelect).at(-1)!.vm.$emit('update:modelValue', 'brand')

    expect(written(wrapper)).toEqual({ brand: { values: [] } })
  })

  it('writes the values picked, as strings', () => {
    const wrapper = field({ brand: { values: [] } })

    wrapper.findComponent(WxSelect).vm.$emit('update:modelValue', [1, '2'])

    expect(written(wrapper)).toEqual({ brand: { values: ['1', '2'] } })
  })

  it('writes one end of a range and keeps the other', () => {
    const wrapper = field({ price: { min: 100, max: 500 } })

    wrapper.findAllComponents(WxInputNumber).at(1)!.vm.$emit('update:modelValue', undefined)

    expect(written(wrapper)).toEqual({ price: { min: 100, max: null } })
  })

  it('removes a facet once confirmed', () => {
    const wrapper = field({ brand: { values: ['1'] }, price: { min: 1, max: null } })

    wrapper.findComponent(WxPopconfirm).vm.$emit('confirm')

    expect(written(wrapper)).toEqual({ price: { min: 1, max: null } })
  })

  it('keeps a facet that is not on offer, labelled by its key', () => {
    const wrapper = field({ 'p.7': { values: ['12'] } })

    expect(wrapper.find('[data-facet="p.7"]').text()).toContain('p.7')
  })

  it('names a chosen value the facets no longer count by the owner’s word, or by its id', () => {
    const named = field(
      { brand: { values: ['9'] } },
      { valueLabel: (key: string, value: string) => `${key}-${value}` },
    )
    const bare = field({ brand: { values: ['9'] } })

    expect(named.findComponent(WxSelect).props('options')).toContainEqual({
      value: '9',
      label: 'brand-9 (0)',
    })
    expect(bare.findComponent(WxSelect).props('options')).toContainEqual({
      value: '9',
      label: '#9 (0)',
    })
  })

  it('shows the number it is given, and nothing while there is none', () => {
    expect(field(null).findComponent(WxBadge).exists()).toBe(false)
    expect(field(null, { count: 0 }).findComponent(WxBadge).props('type')).toBe('warning')
    expect(field(null, { count: 5, counting: true }).findComponent(WxBadge).exists()).toBe(false)
  })

  it('says the number in the panel’s words', () => {
    const wrapper = worded({ modelValue: null, count: 5 })

    expect(wrapper.findComponent(WxBadge).text()).toBe('Products: 5')
  })

  it('hints a range with the bounds of its facet', () => {
    const wrapper = worded({ modelValue: { price: { min: null, max: null } } })

    expect(hints(wrapper, 'price')).toEqual(['10', '900'])
  })

  it('hints a range with the words when its facet has no bounds', () => {
    const unbound: BaseFacet = {
      key: 'width',
      label: 'Width',
      kind: 'range',
      min: null,
      max: null,
      values: [],
    }
    const wrapper = worded({
      modelValue: { width: { min: null, max: null } },
      facets: [unbound],
    })

    expect(hints(wrapper, 'width')).toEqual(['From', 'To'])
  })

  it('takes a bound of zero for a bound, not for a missing one', () => {
    const zero: BaseFacet = {
      key: 'discount',
      label: 'Discount',
      kind: 'range',
      min: 0,
      max: 0,
      values: [],
    }
    const wrapper = worded({
      modelValue: { discount: { min: null, max: null } },
      facets: [zero],
    })

    expect(hints(wrapper, 'discount')).toEqual(['0', '0'])
  })

  it('names the ends of a range by the words, whatever the hint says', () => {
    const wrapper = worded({ modelValue: { price: { min: null, max: null } } })
    const labels = wrapper
      .find('[data-facet="price"]')
      .findAll('input')
      .map((input) => input.attributes('aria-label'))

    expect(labels).toEqual(['Price: From', 'Price: To'])
  })

  it('locks every control when disabled', () => {
    const wrapper = field({ brand: { values: ['1'] } }, { disabled: true })

    for (const select of wrapper.findAllComponents(WxSelect)) {
      expect(select.props('disabled')).toBe(true)
    }
  })
})

describe('cleanSet', () => {
  it('drops the facets that say nothing', () => {
    expect(
      cleanSet({
        brand: { values: [] },
        color: { values: ['red'] },
        price: { min: null, max: null },
        weight: { min: 0, max: null },
      }),
    ).toEqual({ color: { values: ['red'] }, weight: { min: 0, max: null } })
  })
})
