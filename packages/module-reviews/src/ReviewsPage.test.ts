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
import ReviewsPage from './ReviewsPage.vue'
import type { ReviewDetail, ReviewRow, ReviewsList } from './types'

const { confirm } = vi.hoisted(() => ({ confirm: vi.fn() }))

/* The dialog itself is the core's; what is checked here is that the form asks at all. */
vi.mock('@webx-ui/core', async (original) => ({
  ...(await original<typeof Core>()),
  confirm,
}))

function row(id: number, name: string, over: Partial<ReviewRow> = {}): ReviewRow {
  return {
    id,
    name,
    job_title: null,
    rating: null,
    photo: null,
    published: true,
    position: id,
    locales: ['en'],
    categories: [],
    updated_at: '2026-09-24T09:00:00+00:00',
    deleted_at: null,
    ...over,
  }
}

const list: ReviewsList = {
  data: [
    row(1, 'Anna Petrova', {
      job_title: 'CEO, Acme',
      rating: 4,
      photo: { thumb: '/thumbs/anna.jpg' },
      categories: [{ id: 3, title: 'Clinic' }],
    }),
    row(2, 'Oleg Sydorenko', { published: false }),
    // Published, and yet in no language: no text anywhere.
    row(3, 'Maria Lopez', { locales: [] }),
  ],
  filters: { categories: [{ id: 3, title: 'Clinic' }] },
}

function detail(id: number, name: string): ReviewDetail {
  return {
    review: { id, name, published: true, deleted_at: null },
    values: { name: { en: name }, text: { en: 'Great.' }, published: true, categories: [] },
  }
}

/* A slice of `reviews.form`: enough to type a name and see a save go out. */
const screen: ScreenNode[] = [
  { id: 'name', type: 'wx-input', name: 'name', localized: true },
  { id: 'published', type: 'wx-switch', name: 'published' },
]

let router: Router

async function panel(query = '', can = true) {
  const get = vi.fn().mockImplementation((url: string) => {
    const one = /\/reviews\/(\d+)$/.exec(url)

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
      { path: '/reviews', component: ReviewsPage, props: { base: '/reviews' } },
      { path: '/elsewhere', component: { template: '<div />' } },
    ],
  })

  await router.push(`/reviews${query}`)
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
 * The list, its order, and the form beside it. Where the form goes on a phone — into the drawer,
 * with its own way back — is a width, and jsdom measures none: that is checked in a browser.
 */
describe('WxReviewsPage', () => {
  it('asks for every review and says who wrote it, how well, and where it is seen', async () => {
    const { wrapper, get } = await panel()

    expect(get).toHaveBeenCalledWith('/api/cms/reviews')
    expect(wrapper.findAll('.wx-review-row')).toHaveLength(3)

    const [anna, draft, unseen] = wrapper.findAll('.wx-review-row')
    expect(anna!.text()).toContain('CEO, Acme')
    expect(anna!.text()).toContain('Clinic')
    expect(anna!.get('.wx-review-row__stars').attributes('aria-label')).toBe('4 / 5')
    expect(anna!.get('.wx-review-row__stars').text()).toBe('★★★★☆')
    expect(anna!.get('.wx-avatar img').attributes('src')).toBe('/thumbs/anna.jpg')
    expect(anna!.text()).not.toContain('Not published')

    // No rating is no stars, not five empty ones; no photo is the initials.
    expect(draft!.find('.wx-review-row__stars').exists()).toBe(false)
    expect(draft!.find('.wx-avatar img').exists()).toBe(false)
    expect(draft!.get('.wx-avatar').text()).toBe('OS')
    expect(draft!.text()).toContain('Not published')

    // Published, seen nowhere: the warning, not the grey badge of a draft.
    expect(unseen!.find('.wx-badge--warning').exists()).toBe(true)
  })

  it('writes the whole order when nothing narrows the list', async () => {
    const { wrapper, post } = await panel()

    await moveFirstDown(wrapper)

    expect(post).toHaveBeenCalledWith('/api/cms/reviews/reorder', { ids: [2, 1, 3] })
  })

  it("writes one category's order when the list is narrowed to it", async () => {
    const { wrapper, get, post } = await panel('?category=3')

    expect(get).toHaveBeenCalledWith('/api/cms/reviews?category=3')

    await moveFirstDown(wrapper)

    expect(post).toHaveBeenCalledWith('/api/cms/reviews/reorder', { ids: [2, 1, 3], category: 3 })
  })

  it('offers no grips over a search: the gaps are rows nobody can see', async () => {
    const { wrapper, get } = await panel('?q=anna')

    expect(get).toHaveBeenCalledWith('/api/cms/reviews?search=anna')
    expect(wrapper.findAll('.wx-sortable-list__grip')).toHaveLength(0)
  })

  it('opens a new review as a form, and makes it on the first save', async () => {
    const { wrapper, post } = await panel('?category=3')

    await wrapper.get('.wx-reviews__new').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query.review).toBe('new')
    expect(post).not.toHaveBeenCalledWith('/api/cms/reviews', expect.anything())

    await wrapper.get('.wx-review input').setValue('Brand new')
    await wrapper.get('.wx-review .wx-action-bar button').trigger('click')
    await flushPromises()

    // In the category the list was narrowed to: that is where it was written.
    expect(post).toHaveBeenCalledWith('/api/cms/reviews', {
      values: {
        name: { en: 'Brand new' },
        job_title: {},
        text: {},
        published: false,
        categories: [3],
      },
    })
    expect(router.currentRoute.value.query.review).toBe('9')
  })

  it('saves with Ctrl+S', async () => {
    const { wrapper, put } = await panel('?review=1')

    await wrapper.get('.wx-review input').setValue('Anna Petrova-Koval')
    await wrapper.get('.wx-review').trigger('keydown', { key: 's', ctrlKey: true })
    await flushPromises()

    expect(put).toHaveBeenCalledWith('/api/cms/reviews/1', {
      values: expect.objectContaining({ name: { en: 'Anna Petrova-Koval' } }),
    })
  })

  it('puts a refusal under the field it names', async () => {
    const { wrapper, put } = await panel('?review=1')

    put.mockRejectedValueOnce({ status: 422, body: { errors: { name: ['Too long.'] } } })
    await wrapper.get('.wx-review input').setValue('x')
    await wrapper.get('.wx-review .wx-action-bar button').trigger('click')
    await flushPromises()

    expect(wrapper.get('.wx-review').text()).toContain('Too long.')
  })

  it('asks before another review takes the place of unsaved words', async () => {
    const { wrapper } = await panel('?review=1')

    confirm.mockResolvedValueOnce(false)
    await wrapper.get('.wx-review input').setValue('Half-written')
    await wrapper.findAll('.wx-review-row')[1]!.trigger('click')
    await flushPromises()

    expect(confirm).toHaveBeenCalledOnce()
    expect(router.currentRoute.value.query.review).toBe('1')
  })

  it('does not ask while the search above the list changes the address', async () => {
    vi.useFakeTimers()
    const { wrapper } = await panel('?review=1')

    await wrapper.get('.wx-review input').setValue('Half-written')
    await wrapper.get('.wx-reviews__search input').setValue('anna')
    await vi.advanceTimersByTimeAsync(400)
    await flushPromises()
    vi.useRealTimers()

    expect(confirm).not.toHaveBeenCalled()
    expect(router.currentRoute.value.query).toMatchObject({ q: 'anna', review: '1' })
  })

  it('brings a review back from the bin, and the bin has no new line', async () => {
    const { wrapper, post } = await panel('?view=trashed')

    expect(wrapper.find('.wx-reviews__new').exists()).toBe(false)

    await wrapper.findAll('.wx-actions__menu button')[0]!.trigger('click')
    await flushPromises()
    const items = [...document.querySelectorAll<HTMLElement>('.wx-dropdown-item')]

    expect(items.map((item) => item.textContent?.trim())).toEqual(['Restore'])
    items[0]!.click()
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/reviews/1/restore', {})
  })

  it('has no new line and no grips for somebody who only reads', async () => {
    const { wrapper } = await panel('', false)

    expect(wrapper.find('.wx-reviews__new').exists()).toBe(false)
    expect(wrapper.findAll('.wx-sortable-list__grip')).toHaveLength(0)
  })
})
