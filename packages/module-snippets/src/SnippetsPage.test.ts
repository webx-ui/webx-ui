import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { createRouter, createWebHistory, type Router } from 'vue-router'
import {
  adminKey,
  adminMessages,
  adminTypes,
  createI18n,
  i18nKey,
  type AdminContext,
} from '@webx-ui/module-admin'
import { localesKey } from '@webx-ui/core'
import { coreTypes, type ScreenNode } from '@webx-ui/schema'
import SnippetsPage from './SnippetsPage.vue'
import type { SnippetDetail, SnippetPlace, SnippetRow, SnippetsList } from './types'

function row(id: number, name: string, place: string, over: Partial<SnippetRow> = {}): SnippetRow {
  return {
    id,
    name,
    place,
    place_declared: true,
    consent: 'necessary',
    only_paths: [],
    except_paths: [],
    locales: [],
    on_stage: false,
    enabled: true,
    position: id,
    bytes: 20,
    updated_at: '2026-10-14T10:30:00+00:00',
    deleted_at: null,
    ...over,
  }
}

function place(
  key: string,
  snippets: SnippetRow[],
  over: Partial<SnippetPlace> = {},
): SnippetPlace {
  return {
    key,
    label: key,
    built_in: true,
    declared: true,
    last_seen: '2026-10-16',
    snippets,
    ...over,
  }
}

function list(everything = true): SnippetsList {
  return {
    data: [
      place('head', [
        row(1, 'Tag manager', 'head', { consent: 'statistics', only_paths: ['/blog/*'] }),
        row(2, 'Verification', 'head', { enabled: false, locales: ['en'] }),
      ]),
      place('body_end', [row(3, 'Chat', 'body_end', { on_stage: true })]),
      // Declared by the site and never printed by a page.
      place('before-footer', [], { built_in: false, last_seen: null }),
      // Taken out of the config, a code still in it.
      place('promo', [row(4, 'Promo', 'promo', { place_declared: false, enabled: false })], {
        built_in: false,
        declared: false,
        last_seen: null,
      }),
    ],
    environment: everything ? 'production' : 'staging',
    everything_printed: everything,
  }
}

function detail(id: number, warnings: string[] = []): SnippetDetail {
  return {
    snippet: row(id, 'Promo', 'promo', { place_declared: false, enabled: false }),
    values: { name: 'Promo', place: 'promo', code: '<i>x</i>', enabled: false },
    warnings,
  }
}

/* The form without the code editor, which jsdom cannot draw: the editor is CodeMirror. */
const screen: ScreenNode[] = [
  { id: 'name', type: 'wx-input', name: 'name' },
  { id: 'enabled', type: 'wx-switch', name: 'enabled' },
]

let router: Router

async function panel(query = '', options: { everything?: boolean; warnings?: string[] } = {}) {
  const get = vi.fn().mockImplementation((url: string) => {
    const one = /\/snippets\/(\d+)$/.exec(url)

    if (one) return Promise.resolve({ data: detail(Number(one[1]), options.warnings) })

    return Promise.resolve(list(options.everything ?? true))
  })
  const post = vi.fn().mockResolvedValue(undefined)
  const put = vi
    .fn()
    .mockImplementation((_url: string, body: { values: Record<string, unknown> }) =>
      Promise.resolve({
        data: { ...detail(1), snippet: row(1, 'Tag manager', 'head', body.values) },
      }),
    )
  const i18n = createI18n()
  i18n.defaults('webx-admin', adminMessages)

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, post, put, delete: vi.fn().mockResolvedValue(undefined) },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
    types: { ...coreTypes, ...adminTypes },
    loadScreen: () => Promise.resolve(screen),
    screenPatch: () => [],
  } as unknown as AdminContext

  router = createRouter({
    history: createWebHistory(),
    routes: [{ path: '/snippets', component: SnippetsPage, props: { base: '/snippets' } }],
  })

  await router.push(`/snippets${query}`)
  await router.isReady()

  const wrapper = mount(
    { template: '<router-view />' },
    {
      attachTo: document.body,
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

  return { wrapper, get, post, put }
}

afterEach(() => {
  document.body.innerHTML = ''
})

/**
 * The places, their order and the switch. Whether the list and the form fit side by side, and
 * how a drag between places looks, is a width and a pointer — checked in a browser.
 */
describe('WxSnippetsPage', () => {
  it('lists every place with its codes, in the order of the config', async () => {
    const { wrapper, get } = await panel()

    expect(get).toHaveBeenCalledWith('/api/cms/snippets')

    const places = wrapper.findAll('.wx-snippets-place')
    expect(places.map((one) => one.attributes('data-place'))).toEqual([
      'head',
      'body_end',
      'before-footer',
      'promo',
    ])

    const [tags, verification] = places[0]!.findAll('.wx-snippets-row')
    expect(tags!.text()).toContain('Analytics')
    expect(tags!.text()).toContain('Only on paths: 1')
    expect(tags!.text()).toContain('All languages')
    expect(verification!.text()).toContain('Off')
    expect(verification!.text()).toContain('EN')
    expect(places[1]!.text()).toContain('Dev too')
  })

  it('points out a place no page has printed and a place nobody declares', async () => {
    const { wrapper } = await panel()

    const places = wrapper.findAll('.wx-snippets-place')

    expect(places[0]!.text()).not.toContain('Not printed on any page yet')
    expect(places[2]!.text()).toContain('Not printed on any page yet')
    expect(places[2]!.text()).toContain('No codes in this place.')

    expect(places[3]!.classes()).toContain('is-undeclared')
    expect(places[3]!.text()).toContain('Not declared')
    expect(places[3]!.text()).toContain('printed nowhere')
    // Nothing is dragged out of or into a place that prints nothing, and nothing is added to it.
    expect(places[3]!.find('.wx-sortable-list__grip').exists()).toBe(false)
    expect(places[3]!.find('.wx-snippets-place__head button').exists()).toBe(false)
  })

  it('says outside production that only some codes are printed', async () => {
    const quiet = await panel()
    expect(quiet.wrapper.text()).not.toContain('The site runs as')
    quiet.wrapper.unmount()

    const { wrapper } = await panel('', { everything: false })
    expect(wrapper.text()).toContain('The site runs as “staging”')
  })

  it('writes the whole place when a code is moved inside it', async () => {
    const { wrapper, post } = await panel()
    const grip = wrapper.findAll('.wx-snippets-place')[0]!.findAll('.wx-sortable-list__grip')[0]!

    await grip.trigger('keydown', { key: ' ' })
    await grip.trigger('keydown', { key: 'ArrowDown' })
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/snippets/reorder', { place: 'head', ids: [2, 1] })
  })

  it('switches a code on and off with `enabled` alone', async () => {
    const { wrapper, put } = await panel()
    const toggle = wrapper.findAll('.wx-snippets-place')[0]!.findAll('[role="switch"]')[1]!

    await toggle.trigger('click')
    await flushPromises()

    expect(put).toHaveBeenCalledWith('/api/cms/snippets/2', { values: { enabled: true } })
  })

  it('opens a code with what is wrong with it above the form', async () => {
    const { wrapper } = await panel('?snippet=4', {
      warnings: ['The code has <body>: paste only what the service asks to put inside the page.'],
    })

    const pane = wrapper.find('.wx-snippets-pane')

    expect(pane.text()).toContain('The place “promo” is no longer declared')
    expect(pane.text()).toContain('The code has <body>')
  })
})
