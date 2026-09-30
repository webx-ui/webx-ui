import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { WxSelect } from '@webx-ui/core'
import { adminKey, type AdminContext } from '../admin'
import { adminTypes } from '../screenTypes'
import SourceSelect from './SourceSelect.vue'

enableAutoUnmount(afterEach)

const brands = [
  { id: 1, name: 'Northwind' },
  { id: 2, name: 'Contoso' },
]

function select(props: Record<string, unknown>, list = brands) {
  const get = vi.fn().mockResolvedValue({ data: list, prefix: 'brands' })
  const admin = { apiPath: '/api/cms', http: { get } } as unknown as AdminContext

  const wrapper = mount(SourceSelect, {
    props,
    attachTo: document.body,
    global: { provide: { [adminKey as symbol]: admin } },
  })

  return { wrapper, get }
}

const input = (wrapper: ReturnType<typeof select>['wrapper']) =>
  wrapper.get('.wx-select__input').element as HTMLInputElement

describe('WxSourceSelect (`wx-select` in a screen)', () => {
  it('is the panel’s `wx-select`, over the schema’s', () => {
    expect(adminTypes['wx-select']?.component).toBe(SourceSelect)
  })

  it('offers the records of the list its `source` names, by name', async () => {
    const { wrapper, get } = select({ source: 'catalog/brands', modelValue: 2, filterable: true })
    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/catalog/brands')
    // The value arrives before the list does; the box still ends up saying the name.
    expect(input(wrapper).value).toBe('Contoso')

    await wrapper.get('.wx-select__toggle').trigger('click')
    await flushPromises()

    const offered = [...document.querySelectorAll('.wx-select__option')].map((one) =>
      one.textContent?.trim(),
    )
    expect(offered).toEqual(['Northwind', 'Contoso'])
  })

  it('hands everything else to the core’s select — what empty means, the cross that empties it', async () => {
    const { wrapper } = select({
      source: 'catalog/brands',
      modelValue: 1,
      clearable: true,
      filterable: true,
      placeholder: 'No brand',
    })
    await flushPromises()

    expect(input(wrapper).placeholder).toBe('No brand')

    await wrapper.get('.wx-select__clear').trigger('click')

    // The listener goes through with everything else, so it is the core's select that says so.
    expect(wrapper.getComponent(WxSelect).emitted('update:modelValue')?.at(-1)).toEqual([null])
  })

  it('is the core’s select with the options of the screen when there is no source', async () => {
    const { wrapper, get } = select({
      modelValue: 'pcs',
      filterable: true,
      options: [{ label: 'Pieces', value: 'pcs' }],
    })
    await flushPromises()

    expect(get).not.toHaveBeenCalled()
    expect(input(wrapper).value).toBe('Pieces')
  })
})
