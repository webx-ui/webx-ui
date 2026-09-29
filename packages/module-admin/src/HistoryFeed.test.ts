import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h, ref, type Ref } from 'vue'
import { afterEach, describe, expect, it, vi } from 'vitest'
import HistoryFeed from './HistoryFeed.vue'
import { adminKey, type AdminContext } from './admin'
import { provideHistorySubject, type HistoryEntry, type HistoryPage } from './history'
import { createI18n, i18nKey } from './i18n'
import { adminMessages } from './messages'

function entry(over: Partial<HistoryEntry> & { id: number }): HistoryEntry {
  return {
    event: 'updated',
    source: 'panel',
    subject: { type: 'catalog.product', id: 7 },
    admin: { id: 3, name: 'Anna' },
    grant_id: null,
    changes: [],
    run: null,
    created_at: '2026-09-29T08:10:00Z',
    ...over,
  }
}

function page(data: HistoryEntry[], current = 1, last = 1): HistoryPage {
  return { data, current_page: current, last_page: last, total: data.length }
}

function context(get: ReturnType<typeof vi.fn>) {
  const i18n = createI18n()
  i18n.defaults('webx-admin', adminMessages)

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
  } as unknown as AdminContext

  return { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n }
}

const mounted: { unmount(): void }[] = []

afterEach(() => {
  mounted.splice(0).forEach((wrapper) => wrapper.unmount())
})

function feed(get: ReturnType<typeof vi.fn>, props: Record<string, unknown>) {
  const wrapper = mount(HistoryFeed, {
    props: { type: 'catalog.product', ...props },
    attachTo: document.body,
    global: { provide: context(get) },
  })
  mounted.push(wrapper)

  return wrapper
}

/**
 * The journal of a record, as the frame draws it.
 *
 * Worth a test: the address (the panel's own, with the registered type), what a line says — who,
 * what, through which door, and each field as "was → is" — and the two ways the id arrives: as a
 * prop, or from the editor that hosts the screen.
 */
describe('WxHistory', () => {
  it('asks the panel for the type and id and draws was → is', async () => {
    const get = vi.fn().mockResolvedValue(
      page([
        entry({
          id: 2,
          source: 'mcp',
          changes: [
            { field: 'price', label: 'Price', from: '100.00', to: '120.00' },
            { field: 'name.ru', label: 'Name (RU)', from: null, to: 'Чайник' },
            {
              field: 'body',
              label: 'Text',
              from: null,
              to: null,
              long: true,
              from_length: 40,
              to_length: 900,
            },
          ],
        }),
        entry({ id: 1, event: 'created', admin: null, source: 'console' }),
      ]),
    )

    const wrapper = feed(get, { id: 7 })
    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/history/catalog.product/7', { query: { page: 1 } })

    const text = wrapper.text()
    expect(text).toContain('Anna')
    expect(text).toContain('Changed · through an agent')
    expect(text).toContain('Price:')
    expect(wrapper.find('.wx-history-change__from').text()).toBe('100.00')
    expect(wrapper.find('.wx-history-change__to').text()).toBe('120.00')
    expect(text).toContain('empty')
    expect(text).toContain('changed, 40 → 900 characters')
    // Written by nobody signed in: said so, not left blank.
    expect(text).toContain('Nobody signed in')
    expect(text).toContain('Created · from the console')
  })

  it('takes the id from the editor above it, and asks nothing for a record not saved yet', async () => {
    const get = vi.fn().mockResolvedValue(page([]))
    const id: Ref<number | null> = ref(null)

    const Host = defineComponent({
      setup() {
        provideHistorySubject({ id })
        return () => h(HistoryFeed, { type: 'catalog.product' })
      },
    })

    const wrapper = mount(Host, { global: { provide: context(get) } })
    mounted.push(wrapper)
    await flushPromises()

    expect(get).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('The history begins with the first save.')

    id.value = 12
    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/history/catalog.product/12', { query: { page: 1 } })
    expect(wrapper.text()).toContain('Nothing has been changed here yet.')
  })

  it('adds the next page under the first', async () => {
    const get = vi
      .fn()
      .mockResolvedValueOnce(page([entry({ id: 3 })], 1, 2))
      .mockResolvedValueOnce(page([entry({ id: 2 }), entry({ id: 1 })], 2, 2))

    const wrapper = feed(get, { id: 7 })
    await flushPromises()

    await wrapper.find('.wx-history__more button').trigger('click')
    await flushPromises()

    expect(get).toHaveBeenLastCalledWith('/api/cms/history/catalog.product/7', {
      query: { page: 2 },
    })
    expect(wrapper.findAll('.wx-history-entry')).toHaveLength(3)
    expect(wrapper.find('.wx-history__more').exists()).toBe(false)
  })

  it('links a row made in a run to the run, and opens it', async () => {
    const run = entry({
      id: 40,
      event: 'run',
      source: 'import',
      subject: { type: 'catalog.product', id: null },
      summary: { what: 'prices.csv', rows: 2 },
      rows: 2,
    })

    const get = vi.fn().mockImplementation((path: string) =>
      Promise.resolve(
        path.includes('/runs/')
          ? { run, rows: page([entry({ id: 41, source: 'import' })]) }
          : page([
              entry({
                id: 41,
                source: 'import',
                run: { id: 40, summary: { what: 'prices.csv' } },
              }),
            ]),
      ),
    )

    const wrapper = feed(get, { id: 7 })
    await flushPromises()

    const link = wrapper.find('.wx-history-entry__run')
    expect(link.text()).toBe('Part of a run · prices.csv')

    await link.trigger('click')
    await flushPromises()

    expect(get).toHaveBeenLastCalledWith('/api/cms/history/runs/40', {
      query: { page: 1, search: null },
    })
    expect(document.body.textContent).toContain('Rows: 2')
    expect(document.body.textContent).toContain('prices.csv')
  })
})
