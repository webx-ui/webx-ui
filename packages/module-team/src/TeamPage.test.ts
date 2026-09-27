import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
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
import type * as Core from '@webx-ui/core'
import { coreTypes, type ScreenNode } from '@webx-ui/schema'
import TeamPage from './TeamPage.vue'
import type { MemberDetail, MemberRow, MembersList } from './types'

const { confirm } = vi.hoisted(() => ({ confirm: vi.fn() }))

/* The dialog itself is the core's; what is checked here is that the form asks at all. */
vi.mock('@webx-ui/core', async (original) => ({
  ...(await original<typeof Core>()),
  confirm,
}))

function row(id: number, name: string, over: Partial<MemberRow> = {}): MemberRow {
  return {
    id,
    name,
    job_title: null,
    initials: '',
    photo: null,
    published: true,
    position: id,
    updated_at: '2026-09-27T09:00:00+00:00',
    deleted_at: null,
    ...over,
  }
}

const list: MembersList = {
  data: [
    row(1, 'Anna Petrova', { job_title: 'Orthodontist', photo: { thumb: '/thumbs/anna.jpg' } }),
    row(2, 'Oleg Sydorenko', { published: false }),
    row(3, 'Maria Lopez', { job_title: 'Hygienist' }),
  ],
}

function detail(id: number, name: string): MemberDetail {
  return {
    member: { id, name, published: true, deleted_at: null },
    values: {
      name: { en: name },
      job_title: {},
      text: {},
      photo: null,
      socials: [{ network: 'instagram', url: 'https://instagram.com/anna' }],
      published: true,
    },
  }
}

/* A slice of `team.form`: a name to type, and the links, whose rows carry their own errors. */
const screen: ScreenNode[] = [
  { id: 'name', type: 'wx-input', name: 'name', localized: true },
  {
    id: 'socials',
    type: 'wx-repeater',
    name: 'socials',
    children: [
      {
        id: 'social-network',
        type: 'wx-select',
        name: 'network',
        props: { options: [{ value: 'instagram', label: 'Instagram' }] },
      },
      { id: 'social-url', type: 'wx-input', name: 'url' },
    ],
  },
  { id: 'published', type: 'wx-switch', name: 'published' },
]

let router: Router

async function panel(query = '', can = true) {
  const get = vi.fn().mockImplementation((url: string) => {
    const one = /\/team\/(\d+)$/.exec(url)

    return Promise.resolve(one ? { data: detail(Number(one[1]), 'Anna Petrova') } : list)
  })
  const post = vi.fn().mockImplementation((url: string) => {
    if (url.endsWith('/reorder')) return Promise.resolve(undefined)
    if (url.endsWith('/restore')) return Promise.resolve({ data: row(2, 'Oleg Sydorenko') })

    return Promise.resolve({ data: detail(9, 'Brand new') })
  })
  const put = vi.fn().mockResolvedValue({ data: detail(1, 'Anna Petrova-Koval') })
  const i18n = createI18n()
  // What the panel seeds before any screen: the words of the order are the panel's, not ours.
  i18n.defaults('webx-admin', adminMessages)

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, post, put, delete: vi.fn().mockResolvedValue(undefined) },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => can,
    types: { ...coreTypes, ...adminTypes },
    loadScreen: () => Promise.resolve(screen),
    screenPatch: () => [],
  } as unknown as AdminContext

  router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/team', component: TeamPage, props: { base: '/team' } },
      { path: '/elsewhere', component: { template: '<div />' } },
    ],
  })

  await router.push(`/team${query}`)
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
          // What a panel always provides; without it a localized field hands back a plain string.
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

/** Pick the first row up with the keyboard and put it one place down — a drag, minus the mouse. */
async function moveFirstDown(wrapper: Awaited<ReturnType<typeof panel>>['wrapper']) {
  const grip = wrapper.findAll('.wx-sortable-list__grip')[0]!

  await grip.trigger('keydown', { key: ' ' })
  await grip.trigger('keydown', { key: 'ArrowDown' })
  await flushPromises()
}

beforeEach(() => {
  confirm.mockReset()
})

afterEach(() => {
  document.body.innerHTML = ''
})

/**
 * The list, its one order, and the form beside it. Where the form goes on a phone — into the
 * drawer, with its own way back — is a width, and jsdom measures none: that is checked in a
 * browser.
 */
describe('WxTeamPage', () => {
  it('asks for everybody and says who they are and whether they are on the site', async () => {
    const { wrapper, get } = await panel()

    expect(get).toHaveBeenCalledWith('/api/cms/team')
    expect(wrapper.findAll('.wx-member-row')).toHaveLength(3)

    const [anna, draft] = wrapper.findAll('.wx-member-row')
    expect(anna!.text()).toContain('Orthodontist')
    expect(anna!.get('.wx-avatar img').attributes('src')).toBe('/thumbs/anna.jpg')
    expect(anna!.text()).not.toContain('Not published')

    // No photo is the initials; a draft says so.
    expect(draft!.find('.wx-avatar img').exists()).toBe(false)
    expect(draft!.get('.wx-avatar').text()).toBe('OS')
    expect(draft!.text()).toContain('Not published')
  })

  it('has no category filter: there are no categories (decision 1)', async () => {
    const { wrapper } = await panel()

    expect(wrapper.find('.wx-team__bar .wx-select').exists()).toBe(false)
  })

  it('writes the one order there is', async () => {
    const { wrapper, post } = await panel()

    await moveFirstDown(wrapper)

    expect(post).toHaveBeenCalledWith('/api/cms/team/reorder', { ids: [2, 1, 3] })
  })

  it('offers no grips over a search: the gaps are rows nobody can see', async () => {
    const { wrapper, get } = await panel('?q=anna')

    expect(get).toHaveBeenCalledWith('/api/cms/team?search=anna')
    expect(wrapper.findAll('.wx-sortable-list__grip')).toHaveLength(0)
  })

  it('opens a new person as a form, and makes them on the first save', async () => {
    const { wrapper, post } = await panel()

    await wrapper.get('.wx-team__new').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query.member).toBe('new')
    expect(post).not.toHaveBeenCalledWith('/api/cms/team', expect.anything())

    await wrapper.get('.wx-member input').setValue('Brand new')
    await wrapper.get('.wx-member .wx-action-bar button').trigger('click')
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/team', {
      values: expect.objectContaining({
        name: { en: 'Brand new' },
        socials: [],
        published: false,
      }),
    })
    expect(router.currentRoute.value.query.member).toBe('9')
  })

  it('saves with Ctrl+S', async () => {
    const { wrapper, put } = await panel('?member=1')

    await wrapper.get('.wx-member input').setValue('Anna Petrova-Koval')
    await wrapper.get('.wx-member').trigger('keydown', { key: 's', ctrlKey: true })
    await flushPromises()

    expect(put).toHaveBeenCalledWith('/api/cms/team/1', {
      values: expect.objectContaining({
        name: { en: 'Anna Petrova-Koval' },
        socials: [{ network: 'instagram', url: 'https://instagram.com/anna' }],
      }),
    })
  })

  it('puts a refused link under the row it belongs to', async () => {
    const { wrapper, put } = await panel('?member=1')

    put.mockRejectedValueOnce({
      status: 422,
      body: { errors: { 'socials.0.url': ['Starts with https://.'] } },
    })
    await wrapper.get('.wx-member input').setValue('x')
    await wrapper.get('.wx-member .wx-action-bar button').trigger('click')
    await flushPromises()

    const item = wrapper.get('.wx-member .wx-repeater__row')

    expect(item.text()).toContain('Starts with https://.')
  })

  it('asks before another person takes the place of unsaved words', async () => {
    const { wrapper } = await panel('?member=1')

    confirm.mockResolvedValueOnce(false)
    await wrapper.get('.wx-member input').setValue('Half-written')
    await wrapper.findAll('.wx-member-row')[1]!.trigger('click')
    await flushPromises()

    expect(confirm).toHaveBeenCalledOnce()
    expect(router.currentRoute.value.query.member).toBe('1')
  })

  it('does not ask while the search above the list changes the address', async () => {
    vi.useFakeTimers()
    const { wrapper } = await panel('?member=1')

    await wrapper.get('.wx-member input').setValue('Half-written')
    await wrapper.get('.wx-team__search input').setValue('anna')
    await vi.advanceTimersByTimeAsync(400)
    await flushPromises()
    vi.useRealTimers()

    expect(confirm).not.toHaveBeenCalled()
    expect(router.currentRoute.value.query).toMatchObject({ q: 'anna', member: '1' })
  })

  it('brings a person back from the bin, and the bin has no new line', async () => {
    const { wrapper, post } = await panel('?view=trashed')

    expect(wrapper.find('.wx-team__new').exists()).toBe(false)

    await wrapper.findAll('.wx-actions__menu button')[0]!.trigger('click')
    await flushPromises()
    const items = [...document.querySelectorAll<HTMLElement>('.wx-dropdown-item')]

    expect(items.map((item) => item.textContent?.trim())).toEqual(['Restore'])
    items[0]!.click()
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/team/1/restore', {})
  })

  it('has no new line and no grips for somebody who only reads', async () => {
    const { wrapper } = await panel('', false)

    expect(wrapper.find('.wx-team__new').exists()).toBe(false)
    expect(wrapper.findAll('.wx-sortable-list__grip')).toHaveLength(0)
  })
})
