import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { createRouter, createWebHistory } from 'vue-router'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import InboxPage from './InboxPage.vue'
import type { InboxForm } from './types'

function form(row: Partial<InboxForm> & { id: number }): InboxForm {
  return {
    slug: 'contact',
    title: { en: 'Contact' },
    is_enabled: true,
    options: {},
    position: 0,
    submissions_count: 0,
    unread_count: 0,
    created_at: null,
    updated_at: null,
    ...row,
  }
}

function panel(forms: InboxForm[], can: (permission: string) => boolean = () => true) {
  const get = vi.fn().mockResolvedValue({ data: forms })
  const post = vi.fn().mockResolvedValue({ data: {} })

  // The real dictionary, because the package's own English is what a panel sees before the
  // server's translations arrive — and a screen that shows keys until then is the bug.
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, post },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can,
  } as unknown as AdminContext

  const router = createRouter({
    history: createWebHistory(),
    // A real route for '/': the screen replaces the query on it, and a catch-all with a
    // required parameter cannot be replaced onto.
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/:all(.*)', component: { template: '<div />' } },
    ],
  })

  return {
    get,
    post,
    router,
    wrapper: mount(InboxPage, {
      global: {
        plugins: [router],
        provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
      },
    }),
  }
}

/**
 * What the column asks for and what it draws. Nothing about how it looks — jsdom computes no
 * layout, so the two panes, the 270px column and the phone are checked in a browser.
 */
describe('WxInboxPage', () => {
  it('lists the forms with what is waiting in each', async () => {
    const { wrapper, get } = panel([
      form({ id: 1, submissions_count: 12, unread_count: 3 }),
      form({ id: 2, slug: 'callback', title: { en: 'Call back' }, is_enabled: false }),
    ])

    await flushPromises()

    expect(get).toHaveBeenCalledWith('/api/cms/inbox/forms')
    expect(wrapper.text()).toContain('Contact')
    expect(wrapper.text()).toContain('callback')
    // Unread in colour; a form nobody has written to says nothing rather than zero.
    expect(wrapper.text()).toContain('3')
    expect(wrapper.text()).not.toContain('12')
    expect(wrapper.text()).toContain('Off')
  })

  it('keeps the chosen form in the address, so coming back lands on it', async () => {
    const { wrapper, router } = panel([form({ id: 7 })])

    await flushPromises()
    await wrapper.get('.wx-inbox-form').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query.form).toBe('7')
  })

  it('offers no delete for a form that has taken submissions', async () => {
    // Switched off, never deleted (§2.4) — and the line is left out rather than greyed,
    // because a menu is a list of what is possible.
    const { wrapper } = panel([form({ id: 1, submissions_count: 4 })])

    await flushPromises()
    await wrapper.get('.wx-actions button').trigger('click')
    await flushPromises()

    const menu = document.body.textContent ?? ''

    expect(menu).toContain('Settings')
    expect(menu).toContain('Duplicate')
    expect(menu).not.toContain('Delete')
  })

  it('says nothing about managing forms to somebody who may only read them', async () => {
    const { wrapper } = panel([form({ id: 1 })], (permission) => permission === 'inbox.view')

    await flushPromises()

    expect(wrapper.text()).not.toContain('New form')
    expect(wrapper.find('.wx-actions').exists()).toBe(false)
  })
})
