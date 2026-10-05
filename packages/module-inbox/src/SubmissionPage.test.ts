import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import SubmissionPage from './SubmissionPage.vue'
import type { InboxStatus, InboxSubmission } from './types'

const status: InboxStatus = {
  id: 1,
  key: 'new',
  title: { en: 'New' },
  color: 'primary',
  is_default: true,
  is_spam: false,
  is_closed: false,
  position: 0,
  submissions_count: null,
}

const done: InboxStatus = { ...status, id: 2, key: 'done', title: { en: 'Done' }, is_closed: true }

function submission(over: Partial<InboxSubmission> = {}): InboxSubmission {
  return {
    id: 5,
    form: { id: 7, slug: 'contact', title: { en: 'Contact' } },
    status,
    assignee: null,
    values: [
      // The label is the snapshot written when it arrived, not the field's current words.
      {
        id: 1,
        field_id: 1,
        name: 'name',
        label: 'Your name',
        type: 'text',
        value: 'Ada',
        payload: null,
      },
      {
        id: 2,
        field_id: 2,
        name: 'email',
        label: 'Your e-mail',
        type: 'email',
        value: 'ada@example.test',
        payload: null,
      },
    ],
    files: [],
    meta: { page: 'https://example.test/contacts', ip: '203.0.113.4' },
    events: [{ id: 1, type: 'created', from: null, to: 'new', author: null, created_at: null }],
    is_read: true,
    source: 'web',
    placement: null,
    notified_at: '2026-09-18T08:10:00Z',
    notify_error: null,
    notification: {
      state: 'delivered',
      error: null,
      queued_at: null,
      delivered_at: '2026-09-18T08:10:00Z',
      recipients: [],
    },
    previous_id: 6,
    next_id: null,
    created_at: '2026-09-18T08:10:00Z',
    updated_at: null,
    ...over,
  }
}

function panel(record: InboxSubmission, can: (permission: string) => boolean = () => true) {
  const get = vi.fn().mockImplementation((path: string) => {
    if (path.endsWith('/statuses')) return Promise.resolve({ data: [status, done] })
    if (path.endsWith('/recipients')) return Promise.resolve({ data: [] })
    if (path.includes('/notes')) return Promise.resolve({ data: [] })

    return Promise.resolve({ data: record })
  })

  const put = vi.fn().mockResolvedValue({ data: { ...record, status: done } })
  const post = vi.fn().mockResolvedValue({ data: { count: 1 } })

  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, put, post, delete: vi.fn() },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can,
  } as unknown as AdminContext

  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/inbox', component: { template: '<div />' } },
      { path: '/inbox/submissions/:id', component: { template: '<div />' } },
      { path: '/:all(.*)', component: { template: '<div />' } },
    ],
  })

  return { get, put, post, router, admin, i18n }
}

async function open(record: InboxSubmission, can?: (permission: string) => boolean) {
  const context = panel(record, can)

  await context.router.replace({ path: '/inbox/submissions/5', query: { view: 'unread' } })
  await context.router.isReady()

  const wrapper = mount(SubmissionPage, {
    props: { base: '/inbox' },
    global: {
      plugins: [context.router],
      provide: { [adminKey as symbol]: context.admin, [i18nKey as symbol]: context.i18n },
    },
  })

  await flushPromises()

  return { ...context, wrapper }
}

/**
 * The card. What it asks for, what it shows and what changing a status sends — not how any of
 * it is arranged, which is a browser's question.
 */
describe('WxInboxSubmissionPage', () => {
  it('reads the answers with the words they were asked in', async () => {
    const { wrapper, get } = await open(submission())

    // The filter travels with it, so the neighbours it reports are the ones either side of it
    // in the list somebody was actually looking at (§11).
    expect(get).toHaveBeenCalledWith(
      '/api/cms/inbox/submissions/5',
      expect.objectContaining({ query: expect.objectContaining({ view: 'unread' }) }),
    )

    expect(wrapper.text()).toContain('Your name')
    expect(wrapper.text()).toContain('Ada')
    expect(wrapper.text()).toContain('Your e-mail')

    // Where it came from, which is the first question asked of a form that sits on nine pages.
    // Behind the third tab: everything about the submission rather than in it is one card that
    // changes its contents. Reka opens a tab on `mousedown`, not on a click (CLAUDE.md §4).
    await wrapper.findAll('.wx-tabs__tab')[2]!.trigger('mousedown')
    await nextTick()

    expect(wrapper.text()).toContain('https://example.test/contacts')
    // No placement, no line for it: most forms stand in one place and say nothing.
    expect(wrapper.text()).not.toContain('Placement')
  })

  it('says where on the site the form stood, when the page said so', async () => {
    const { wrapper } = await open(submission({ placement: 'footer' }))

    await wrapper.findAll('.wx-tabs__tab')[2]!.trigger('mousedown')
    await nextTick()

    expect(wrapper.text()).toMatch(/Placement\s*footer/)
  })

  it('names a handler by its short name and says why it failed', async () => {
    const { wrapper } = await open(
      submission({
        events: [
          {
            id: 1,
            type: 'handled',
            from: 'App\\Inbox\\SyncToCrm',
            to: null,
            author: null,
            created_at: null,
          },
          {
            id: 2,
            type: 'handler_error',
            from: 'App\\Inbox\\SendGift',
            to: 'The CRM answered 503.',
            author: null,
            created_at: null,
          },
        ],
      }),
    )

    await wrapper.findAll('.wx-tabs__tab')[1]!.trigger('mousedown')
    await nextTick()

    expect(wrapper.text()).toContain('Handed to SyncToCrm')
    expect(wrapper.text()).toContain('SendGift failed: The CRM answered 503.')
  })

  it('says the form named nobody, rather than that a letter is still on its way', async () => {
    const { wrapper } = await open(
      submission({
        notified_at: null,
        events: [
          { id: 1, type: 'created', from: null, to: 'new', author: null, created_at: null },
          { id: 2, type: 'no_recipients', from: null, to: null, author: null, created_at: null },
        ],
      }),
    )

    await wrapper.findAll('.wx-tabs__tab')[1]!.trigger('mousedown')
    await nextTick()

    expect(wrapper.text()).toContain('Nobody was told: the form names no recipients')

    await wrapper.findAll('.wx-tabs__tab')[2]!.trigger('mousedown')
    await nextTick()

    expect(wrapper.text()).toContain('No notification: the form names nobody to tell')
    expect(wrapper.text()).not.toContain('No notification was sent')
  })

  it('answers by mail to whoever wrote in', async () => {
    const { wrapper } = await open(submission())

    const link = wrapper.find('a[href^="mailto:"]')

    expect(link.exists()).toBe(true)
    expect(link.attributes('href')).toContain('mailto:ada@example.test')
  })

  it('has no reply button when nothing that arrived was an address', async () => {
    const { wrapper } = await open(
      submission({
        values: [
          {
            id: 1,
            field_id: 1,
            name: 'name',
            label: 'Your name',
            type: 'text',
            value: 'Ada',
            payload: null,
          },
        ],
      }),
    )

    expect(wrapper.find('a[href^="mailto:"]').exists()).toBe(false)
  })

  it('sends the new status as soon as it is chosen', async () => {
    const { wrapper, put } = await open(submission())

    const select = wrapper.findComponent({ name: 'WxSelect' })
    select.vm.$emit('update:modelValue', 2)
    await flushPromises()

    expect(put).toHaveBeenCalledWith('/api/cms/inbox/submissions/5', { status_id: 2 })
  })

  it('shows a reader the record and none of the controls that change it', async () => {
    const { wrapper } = await open(submission(), (permission) => permission === 'inbox.view')

    expect(wrapper.text()).toContain('Ada')
    // No menu at all rather than a menu of greyed lines: a menu is a list of what is possible.
    expect(wrapper.find('.wx-actions').exists()).toBe(false)
  })

  it('says a letter that never left, why, and sends it again', async () => {
    const failed = submission({
      notify_error: 'Connection could not be established with host "127.0.0.1:1025"',
      notification: {
        state: 'failed',
        error: 'Connection could not be established with host "127.0.0.1:1025"',
        queued_at: null,
        delivered_at: null,
        recipients: [
          {
            address: 'sales@example.test',
            state: 'failed',
            error: 'Connection could not be established with host "127.0.0.1:1025"',
            at: '2026-09-18T08:10:00Z',
          },
        ],
      },
    })
    const { wrapper, post } = await open(failed)

    await wrapper.findAll('.wx-tabs__tab')[2]!.trigger('mousedown')
    await nextTick()

    expect(wrapper.text()).toContain('The notification could not be sent')
    expect(wrapper.text()).toContain('127.0.0.1:1025')
    expect(wrapper.text()).toMatch(/sales@example\.test\s*failed/)

    post.mockResolvedValueOnce({ data: submission() })

    const again = wrapper
      .findAll('button')
      .find((button) => button.text() === 'Send the notification again')

    await again!.trigger('click')
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/inbox/submissions/5/notify')
    expect(wrapper.text()).toContain('The notification went out')
  })

  it('does not call a letter waiting in the queue sent', async () => {
    const { wrapper } = await open(
      submission({
        notified_at: null,
        notification: {
          state: 'queued',
          error: null,
          queued_at: '2026-09-18T08:10:00Z',
          delivered_at: null,
          recipients: [],
        },
      }),
    )

    await wrapper.findAll('.wx-tabs__tab')[2]!.trigger('mousedown')
    await nextTick()

    expect(wrapper.text()).toContain('The notification is waiting in the queue')
    expect(wrapper.text()).not.toContain('The notification went out')
  })
})
