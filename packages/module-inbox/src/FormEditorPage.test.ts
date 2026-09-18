import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import { localesKey } from '@webx-ui/core'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import FormEditorPage from './FormEditorPage.vue'
import type { InboxField, InboxForm } from './types'

function field(row: Partial<InboxField> & { id: number; key: string }): InboxField {
  return {
    form_id: 1,
    name: row.key,
    type: 'text',
    title: { en: 'Name' },
    placeholder: {},
    help: {},
    options: {},
    is_enabled: true,
    is_required: false,
    is_fullsize: true,
    in_table: false,
    position: 0,
    ...row,
  }
}

const form: InboxForm = {
  id: 1,
  slug: 'contact',
  title: { en: 'Contact' },
  is_enabled: true,
  options: { 'thank-you.heading': { en: 'Thank you' }, 'antispam.captcha': 'turnstile' },
  position: 0,
  submissions_count: 2,
  unread_count: 1,
  fields: [
    field({ id: 10, key: 'name' }),
    field({ id: 11, key: 'email', type: 'email', title: { en: 'E-mail' } }),
  ],
  created_at: null,
  updated_at: null,
}

async function panel() {
  const get = vi
    .fn()
    .mockImplementation((url: string) =>
      Promise.resolve({ data: url.endsWith('/recipients') ? [] : form }),
    )
  const put = vi.fn().mockResolvedValue({ data: form })
  const post = vi.fn().mockResolvedValue({ data: field({ id: 12, key: 'budget' }) })

  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, put, post },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
  } as unknown as AdminContext

  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/inbox', component: { template: '<div />' } },
      { path: '/inbox/forms/:id', component: { template: '<div />' } },
    ],
  })

  await router.push('/inbox/forms/1')

  const wrapper = mount(FormEditorPage, {
    global: {
      plugins: [router],
      provide: {
        [adminKey as symbol]: admin,
        [i18nKey as symbol]: i18n,
        // The site's content languages, which a panel always has: without them a localized
        // field stringifies its map and every one of them reads '[object Object]'.
        [localesKey as symbol]: { list: ref([{ code: 'en' }]), active: ref('en') },
      },
    },
  })

  await flushPromises()

  return { wrapper, get, put, post }
}

/**
 * What the editor loads, what it sends back, and the one rule about it that is not obvious:
 * the settings are keys with dots in them, and a save that split them into a tree would drop
 * every one of them silently (§5).
 */
describe('WxInboxFormEditor', () => {
  it('asks for the form it is on and fills the tabs from it', async () => {
    const { wrapper, get } = await panel()

    expect(get).toHaveBeenCalledWith('/api/cms/inbox/forms/1')
    expect(wrapper.text()).toContain('Contact')
    // Every tab is mounted at once, so what is written on one survives a look at another:
    // the thank-you is in its input while the fields tab is the one on screen.
    const written = wrapper.findAll('input').map((input) => input.element.value)
    expect(written).toContain('Thank you')
    expect(wrapper.text()).toContain('fields[email]')
  })

  it('sends the settings back with their keys whole', async () => {
    const { wrapper, put } = await panel()

    await wrapper.get('.wx-action-bar button').trigger('click')
    await flushPromises()

    expect(put).toHaveBeenCalledWith('/api/cms/inbox/forms/1', {
      slug: 'contact',
      title: { en: 'Contact' },
      is_enabled: true,
      options: { 'thank-you.heading': { en: 'Thank you' }, 'antispam.captcha': 'turnstile' },
    })
  })

  it('shows what the server refused under the field it refused', async () => {
    const { wrapper, put } = await panel()

    put.mockRejectedValueOnce({ body: { errors: { slug: ['That address is taken.'] } } })

    await wrapper.get('.wx-action-bar button').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('That address is taken.')
  })
})
