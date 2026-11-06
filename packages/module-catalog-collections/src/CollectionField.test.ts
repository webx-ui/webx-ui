import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import CollectionField from './CollectionField.vue'

function field(key: string | null, rows: unknown[]) {
  const get = vi.fn().mockResolvedValue({ data: rows })
  const i18n = createI18n()
  const admin = { apiPath: '/api/cms', http: { get }, i18n } as unknown as AdminContext

  const wrapper = mount(CollectionField, {
    props: { modelValue: key },
    global: { provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n } },
  })

  return { wrapper, get }
}

const row = (extra: object = {}) => ({
  id: 1,
  title: 'New arrivals',
  subtitle: 'fresh',
  key: 'fresh',
  thumb: null,
  visible: true,
  ...extra,
})

describe('WxCatalogCollectionField', () => {
  it('names a stored key by asking the relation target for it', async () => {
    const { wrapper, get } = field('fresh', [row()])

    await flushPromises()

    expect(String(get.mock.calls[0]?.[0])).toContain('/relations/catalog-collection')
    expect(wrapper.text()).toContain('New arrivals')
    expect(wrapper.text()).toContain('fresh')
  })

  it('keeps a key nobody has and marks it, which is how one in the bin reads', async () => {
    const { wrapper } = field('gone', [])

    await flushPromises()

    expect(wrapper.text()).toContain('gone')
    expect(wrapper.text()).toContain('No such collection')
  })

  it('marks one the site does not show', async () => {
    const { wrapper } = field('fresh', [row({ visible: false })])

    await flushPromises()

    expect(wrapper.text()).toContain('Off')
  })

  it('says so when nothing is chosen, and asks nothing', async () => {
    const { wrapper, get } = field(null, [])

    await flushPromises()

    expect(wrapper.text()).toContain('No collection chosen.')
    expect(get).not.toHaveBeenCalled()
  })
})
