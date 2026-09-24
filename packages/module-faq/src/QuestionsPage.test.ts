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
import AnchorField from './AnchorField.vue'
import QuestionsPage from './QuestionsPage.vue'
import type { QuestionDetail, QuestionRow, QuestionsList } from './types'

const { confirm } = vi.hoisted(() => ({ confirm: vi.fn() }))

/* The dialog itself is the core's; what is checked here is that the form asks at all. */
vi.mock('@webx-ui/core', async (original) => ({
  ...(await original<typeof Core>()),
  confirm,
}))

function row(id: number, question: string, over: Partial<QuestionRow> = {}): QuestionRow {
  return {
    id,
    question,
    anchor: question.toLowerCase().replace(/\W+/g, '-'),
    published: true,
    position: id,
    locales: ['en'],
    categories: [],
    updated_at: '2026-09-24T09:00:00+00:00',
    deleted_at: null,
    ...over,
  }
}

const list: QuestionsList = {
  data: [
    row(1, 'How do I pay', { categories: [{ id: 3, title: 'Payment' }] }),
    row(2, 'Is there a warranty', { published: false }),
    // Published, and yet in no language: no answer anywhere.
    row(3, 'Do you ship abroad', { locales: [] }),
  ],
  filters: { categories: [{ id: 3, title: 'Payment' }] },
}

function detail(id: number, text: string): QuestionDetail {
  return {
    question: row(id, text),
    values: { question: { en: text }, published: true, categories: [] },
  }
}

/* The form without the rich text, whose editor jsdom cannot draw; the anchor is the node to see. */
const screen: ScreenNode[] = [
  { id: 'question', type: 'wx-input', name: 'question', localized: true },
  { id: 'published', type: 'wx-switch', name: 'published' },
  { id: 'anchor', type: 'wx-faq-anchor', label: 'Link' },
]

let router: Router

async function panel(query = '', can = true) {
  const get = vi.fn().mockImplementation((url: string) => {
    const one = /\/faq\/questions\/(\d+)$/.exec(url)

    return Promise.resolve(one ? { data: detail(Number(one[1]), 'How do I pay') } : list)
  })
  const post = vi
    .fn()
    .mockImplementation((url: string) =>
      Promise.resolve(url.endsWith('/reorder') ? undefined : { data: detail(9, 'Brand new') }),
    )
  const put = vi.fn().mockResolvedValue({ data: detail(1, 'How do I pay by card') })
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
    types: {
      ...coreTypes,
      ...adminTypes,
      'wx-faq-anchor': { component: AnchorField, kind: 'field' },
    },
    loadScreen: () => Promise.resolve(screen),
    screenPatch: () => [],
  } as unknown as AdminContext

  router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/faq', component: QuestionsPage, props: { base: '/faq' } },
      { path: '/elsewhere', component: { template: '<div />' } },
    ],
  })

  await router.push(`/faq${query}`)
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
describe('WxFaqQuestionsPage', () => {
  it('asks for the whole FAQ and says which rows are not on the site', async () => {
    const { wrapper, get } = await panel()

    expect(get).toHaveBeenCalledWith('/api/cms/faq/questions')
    expect(wrapper.findAll('.wx-faq-row')).toHaveLength(3)

    const [paid, draft, unseen] = wrapper.findAll('.wx-faq-row')
    expect(paid!.text()).toContain('Payment')
    expect(paid!.text()).not.toContain('Not published')
    expect(draft!.text()).toContain('Not published')
    // Published, seen nowhere: the warning, not the grey badge of a draft.
    expect(unseen!.find('.wx-badge--warning').exists()).toBe(true)
  })

  it('writes the whole order when nothing narrows the list', async () => {
    const { wrapper, post } = await panel()

    await moveFirstDown(wrapper)

    expect(post).toHaveBeenCalledWith('/api/cms/faq/questions/reorder', { ids: [2, 1, 3] })
  })

  it("writes one category's order when the list is narrowed to it", async () => {
    const { wrapper, get, post } = await panel('?category=3')

    expect(get).toHaveBeenCalledWith('/api/cms/faq/questions?category=3')

    await moveFirstDown(wrapper)

    expect(post).toHaveBeenCalledWith('/api/cms/faq/questions/reorder', {
      ids: [2, 1, 3],
      category: 3,
    })
  })

  it('offers no grips over a search: the gaps are rows nobody can see', async () => {
    const { wrapper, get } = await panel('?q=pay')

    expect(get).toHaveBeenCalledWith('/api/cms/faq/questions?search=pay')
    expect(wrapper.findAll('.wx-sortable-list__grip')).toHaveLength(0)
  })

  it('opens a new question as a form, and makes it on the first save', async () => {
    const { wrapper, post } = await panel('?category=3')

    await wrapper.get('.wx-faq__new').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query.question).toBe('new')
    expect(post).not.toHaveBeenCalledWith('/api/cms/faq/questions', expect.anything())
    // No anchor before there is a question to make it from.
    expect(wrapper.get('.wx-faq-anchor').text()).toContain('first saved')

    await wrapper.get('.wx-faq-question input').setValue('Brand new')
    await wrapper.get('.wx-faq-question .wx-action-bar button').trigger('click')
    await flushPromises()

    // In the category the list was narrowed to: that is where it was written.
    expect(post).toHaveBeenCalledWith('/api/cms/faq/questions', {
      values: { question: { en: 'Brand new' }, answer: {}, published: false, categories: [3] },
    })
    expect(router.currentRoute.value.query.question).toBe('9')
  })

  it('saves with Ctrl+S and shows the anchor it cannot change', async () => {
    const { wrapper, put } = await panel('?question=1')

    expect(wrapper.get('.wx-faq-anchor').text()).toContain('#how-do-i-pay')

    await wrapper.get('.wx-faq-question input').setValue('How do I pay by card')
    await wrapper.get('.wx-faq-question').trigger('keydown', { key: 's', ctrlKey: true })
    await flushPromises()

    expect(put).toHaveBeenCalledWith('/api/cms/faq/questions/1', {
      values: expect.objectContaining({ question: { en: 'How do I pay by card' } }),
    })
  })

  it('copies the anchor as a fragment: a question has no page of its own', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined)
    Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true })

    const { wrapper } = await panel('?question=1')

    await wrapper.get('.wx-faq-anchor button').trigger('click')

    expect(writeText).toHaveBeenCalledWith('#how-do-i-pay')
  })

  it('asks before another question takes the place of unsaved words', async () => {
    const { wrapper } = await panel('?question=1')

    confirm.mockResolvedValueOnce(false)
    await wrapper.get('.wx-faq-question input').setValue('Half-written')
    await wrapper.findAll('.wx-faq-row')[1]!.trigger('click')
    await flushPromises()

    expect(confirm).toHaveBeenCalledOnce()
    expect(router.currentRoute.value.query.question).toBe('1')
  })

  it('does not ask while the search above the list changes the address', async () => {
    vi.useFakeTimers()
    const { wrapper } = await panel('?question=1')

    await wrapper.get('.wx-faq-question input').setValue('Half-written')
    await wrapper.get('.wx-faq__search input').setValue('pay')
    await vi.advanceTimersByTimeAsync(400)
    await flushPromises()
    vi.useRealTimers()

    expect(confirm).not.toHaveBeenCalled()
    expect(router.currentRoute.value.query).toMatchObject({ q: 'pay', question: '1' })
  })

  it('has no new line and no grips for somebody who only reads', async () => {
    const { wrapper } = await panel('', false)

    expect(wrapper.find('.wx-faq__new').exists()).toBe(false)
    expect(wrapper.findAll('.wx-sortable-list__grip')).toHaveLength(0)
  })
})
