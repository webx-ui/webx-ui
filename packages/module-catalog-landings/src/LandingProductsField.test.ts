import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import LandingProductsField from './LandingProductsField.vue'

// The list is teleported, so it would outlive its wrapper and leak into the next test.
enableAutoUnmount(afterEach)

const rows = [
  { id: 1, name: 'Aero 14 laptop', sku: 'WX-9001' },
  { id: 25, name: 'Pulse earbuds', sku: 'WX-9025' },
]

function field() {
  const get = vi.fn().mockImplementation(async (url: string) => {
    const q = new URL(url, 'https://site.test').searchParams.get('q')?.toLowerCase() ?? ''

    return { data: rows.filter((row) => row.name.toLowerCase().startsWith(q)) }
  })
  const i18n = createI18n()
  const admin = { apiPath: '/api/cms', http: { get }, i18n } as unknown as AdminContext

  const wrapper = mount(LandingProductsField, {
    props: { modelValue: [] },
    attachTo: document.body,
    global: { provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n } },
  })

  return { wrapper, get }
}

async function type(wrapper: ReturnType<typeof field>['wrapper'], text: string): Promise<void> {
  await wrapper.get('input').setValue(text)
  vi.advanceTimersByTime(400)
  await flushPromises()
  await nextTick()
}

function choose(name: string): void {
  const option = [...document.body.querySelectorAll<HTMLElement>('.wx-autocomplete__option')].find(
    (one) => one.textContent?.includes(name),
  )

  option?.dispatchEvent(new MouseEvent('click', { bubbles: true }))
}

describe('WxCatalogLandingProducts', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('empties the box after a pick, so the next product is searched for on its own', async () => {
    const { wrapper, get } = field()

    await type(wrapper, 'aero')
    choose('Aero 14 laptop')
    await flushPromises()
    await wrapper.setProps({
      modelValue: wrapper.emitted('update:modelValue')?.at(-1)?.[0] as number[],
    })

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([[1]])
    expect((wrapper.get('input').element as HTMLInputElement).value).toBe('')

    await type(wrapper, 'pulse')

    expect(new URL(String(get.mock.lastCall?.[0]), 'https://site.test').searchParams.get('q')).toBe(
      'pulse',
    )

    choose('Pulse earbuds')
    await flushPromises()

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([[1, 25]])
  })
})
