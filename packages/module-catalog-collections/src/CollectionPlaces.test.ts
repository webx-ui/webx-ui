import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import CollectionPlaces from './CollectionPlaces.vue'
import type { CollectionPlace } from './types'

const place = (id: number, title: string | null, rest: Partial<CollectionPlace> = {}) => ({
  model: 'Page',
  id,
  title,
  published: true,
  live: true,
  draft: false,
  url: null,
  region: null,
  ...rest,
})

const answer = [
  place(1, 'Home', { url: 'https://example.test/' }),
  place(2, 'About', { live: false, draft: true }),
  place(3, 'Sale', { published: false }),
  place(4, null),
]

function places(count: number) {
  const get = vi.fn().mockResolvedValue({ data: answer })
  const i18n = createI18n()
  const admin = { apiPath: '/api/cms', http: { get }, i18n } as unknown as AdminContext

  const wrapper = mount(CollectionPlaces, {
    attachTo: document.body,
    props: { id: 7, count },
    global: { provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n } },
  })

  return { wrapper, get }
}

const panel = () => document.body.querySelector('.wx-catalog-collection-places__body')

afterEach(() => {
  document.body.innerHTML = ''
})

describe('WxCatalogCollectionPlaces', () => {
  it('says the number and asks nothing until it is opened', () => {
    const { wrapper, get } = places(4)

    expect(wrapper.get('button').text()).toBe('Places: 4')
    expect(get).not.toHaveBeenCalled()

    wrapper.unmount()
  })

  it('names the places, marks what the site does not show, and says what is not counted', async () => {
    const { wrapper, get } = places(4)

    await wrapper.get('button').trigger('click')
    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/catalog/collections/7/usage')

    const rows = [...(panel()?.querySelectorAll('li') ?? [])].map((row) => row.textContent?.trim())

    expect(rows).toEqual(['Home', 'AboutDraft only', 'SaleNot published', '#4'])
    expect(panel()?.querySelector('a')?.getAttribute('href')).toBe('https://example.test/')
    expect(panel()?.textContent).toContain('Insertions in a text and tags in templates')

    wrapper.unmount()
  })

  it('has nothing to open on none, and the words stay', () => {
    const { wrapper } = places(0)

    expect(wrapper.find('button').exists()).toBe(false)
    expect(wrapper.text()).toBe('Places: 0')

    wrapper.unmount()
  })

  it('keeps a click on the words from opening the row', async () => {
    const onRow = vi.fn()
    const { wrapper } = places(4)

    wrapper.element.parentElement?.addEventListener('click', onRow)
    await wrapper.get('button').trigger('click')

    expect(onRow).not.toHaveBeenCalled()

    wrapper.unmount()
  })
})
