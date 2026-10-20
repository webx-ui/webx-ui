import { disableAutoUnmount, enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterAll, afterEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import { localesKey } from '@webx-ui/core'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import FormEditorPage from './FormEditorPage.vue'
import type { InboxField, InboxForm } from './types'

// The editor's autosave pause outlives a test that never unmounts it, and fires into a torn-down
// jsdom: "Element is not defined" from a ref callback, after every test has already passed. The
// switch is global to the test utils, so it is handed back for the next file in a shared worker.
enableAutoUnmount(afterEach)
afterAll(disableAutoUnmount)

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

/* What the picker of channels is told by the target of `inbox.submission`. */
const CHANNELS = [
  { id: 3, title: 'Managers in Telegram', subtitle: 'Telegram', thumb: null, visible: true },
]

async function panel(loaded: InboxForm = form, at = '/inbox/forms/1') {
  const get = vi.fn().mockImplementation((url: string) =>
    Promise.resolve({
      data: url.includes('/relations/')
        ? CHANNELS
        : url.endsWith('/recipients') || url.includes('/links/')
          ? []
          : loaded,
    }),
  )
  const put = vi.fn().mockResolvedValue({ data: form })
  const post = vi
    .fn()
    .mockImplementation((url: string) =>
      Promise.resolve({ data: url.includes('/links/') ? [] : field({ id: 12, key: 'budget' }) }),
    )

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

  await router.push(at)

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

  it('warns above the tabs when a switched-on form would tell nobody', async () => {
    const { wrapper } = await panel({ ...form, recipients: [], notifies: false })

    expect(wrapper.find('.wx-inbox-editor__silent').exists()).toBe(true)
    expect(wrapper.text()).toContain('Nobody is told about this form')
  })

  it('says why a named administrator gets nothing, and still warns', async () => {
    const { wrapper } = await panel({
      ...form,
      options: { ...form.options, recipients: [{ admin_id: 7 }] },
      recipients: [
        {
          type: 'admin',
          admin_id: 7,
          name: null,
          email: null,
          receives: false,
          problem: 'admin_deleted',
        },
      ],
      notifies: false,
    })

    expect(wrapper.find('.wx-inbox-editor__silent').exists()).toBe(true)
    expect(wrapper.text()).toContain('This administrator no longer exists')
  })

  it('says nothing when somebody would be written to, or when the form is off', async () => {
    const reached = await panel({
      ...form,
      options: { ...form.options, recipients: [{ email: 'sales@example.test' }] },
    })
    expect(reached.wrapper.find('.wx-inbox-editor__silent').exists()).toBe(false)

    const off = await panel({ ...form, is_enabled: false })
    expect(off.wrapper.find('.wx-inbox-editor__silent').exists()).toBe(false)
  })

  it('opens on the tab the address names, which is where the audit sends somebody', async () => {
    const { wrapper } = await panel(form, '/inbox/forms/1?tab=notifications')

    const active = wrapper.find('[role="tab"][data-state="active"]')
    expect(active.text()).toBe('Notifications')
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

  it('says how the site runs the chosen captcha, and warns when it has no keys for it', async () => {
    const { wrapper } = await panel({
      ...form,
      captcha: {
        recaptcha: { type: 'invisible', configured: true },
        turnstile: { type: 'invisible', configured: false },
      },
    })

    expect(wrapper.text()).toContain('This site runs Turnstile invisibly when the form is sent.')
    expect(wrapper.text()).toContain('The site has no keys for this captcha yet')
    // Where the keys are, rather than a pointer at settings the panel does not have.
    expect(wrapper.text()).toContain('WEBX_INBOX_TURNSTILE_KEY')
  })

  it('names the reCAPTCHA key type the site expects', async () => {
    const { wrapper } = await panel({
      ...form,
      options: { ...form.options, 'antispam.captcha': 'recaptcha' },
      captcha: {
        recaptcha: { type: 'checkbox', configured: true },
        turnstile: { type: 'checkbox', configured: true },
      },
    })

    expect(wrapper.text()).toContain('its keys must be v2 Checkbox keys')
    expect(wrapper.text()).not.toContain('The site has no keys for this captcha yet')
  })

  it('offers “Notify through” only when the notifications module is installed', async () => {
    const without = await panel()
    expect(without.wrapper.text()).not.toContain('Notify through')

    const withModule = await panel({
      ...form,
      notifications: true,
      notify_via: 'channels',
      options: { ...form.options, notify_channels: [3] },
      channels: [
        {
          id: 3,
          name: 'Managers in Telegram',
          provider: 'telegram',
          enabled: true,
          deleted: false,
          sends: true,
        },
      ],
    })

    expect(withModule.wrapper.text()).toContain('Notify through')
    expect(withModule.wrapper.text()).toContain('Managers in Telegram')
    expect(withModule.get).toHaveBeenCalledWith(
      '/api/cms/relations/notify-inbox-submission?ids[]=3',
    )
    // A channel that sends tells somebody: no warning, though the form names no recipient.
    expect(withModule.wrapper.find('.wx-inbox-editor__silent').exists()).toBe(false)
  })

  it('sends the chosen channels back with the settings', async () => {
    const { wrapper, put } = await panel({
      ...form,
      notifications: true,
      notify_via: 'channels',
      options: { ...form.options, notify_channels: [3] },
    })

    await wrapper.get('.wx-action-bar button').trigger('click')
    await flushPromises()

    expect(put).toHaveBeenCalledWith(
      '/api/cms/inbox/forms/1',
      expect.objectContaining({ options: expect.objectContaining({ notify_channels: [3] }) }),
    )
  })
  it('tells one way: the switch shows the chosen half and keeps the settings of the other', async () => {
    const { wrapper, put } = await panel({
      ...form,
      notifications: true,
      notify_via: 'email',
      options: {
        ...form.options,
        recipients: [{ email: 'office@example.com' }],
        notify_channels: [3],
      },
    })

    const way = () => wrapper.get('.wx-inbox-notifications__way').attributes('data-way')

    expect(wrapper.text()).toContain('Field with the visitor’s address')
    expect(wrapper.text()).toContain('{{reply_to}}')
    expect(way()).toBe('email')
    expect(wrapper.findAll('.wx-inbox-notifications__way')).toHaveLength(1)
    expect(wrapper.text()).not.toContain('Notify through')

    wrapper.getComponent({ name: 'WxRadioGroup' }).vm.$emit('update:modelValue', 'channels')
    await flushPromises()

    expect(way()).toBe('channels')
    expect(wrapper.text()).toContain('Notify through')
    expect(wrapper.text()).not.toContain('Who is written to')

    await wrapper.get('.wx-action-bar button').trigger('click')
    await flushPromises()

    expect(put).toHaveBeenCalledWith(
      '/api/cms/inbox/forms/1',
      expect.objectContaining({
        options: expect.objectContaining({
          notify_via: 'channels',
          notify_channels: [3],
          // Not wiped: switching back finds the addresses where they were.
          recipients: [{ email: 'office@example.com' }],
        }),
      }),
    )
  })

  it('has no switch without the notifications module: the letter, with the visitor’s field', async () => {
    const { wrapper } = await panel({ ...form, notify_via: 'channels' })

    expect(wrapper.text()).toContain('Who is written to')
    expect(wrapper.text()).toContain('Field with the visitor’s address')
    expect(wrapper.text()).not.toContain('How to tell about a submission')
    expect(wrapper.text()).not.toContain('{{reply_to}}')
    expect(wrapper.get('.wx-inbox-notifications__way').attributes('data-way')).toBe('email')
  })
  it('reads an address saved before the choice as “An address”, and “Nowhere” clears it', async () => {
    const { wrapper, put } = await panel({
      ...form,
      options: { ...form.options, redirect: '/thank-you' },
    })

    const picker = wrapper.getComponent({ name: 'WxLinkPicker' })
    expect(picker.props('modelValue')).toMatchObject({ target: 'url', url: '/thank-you' })
    // A redirect is not clicked: no new tab, no rel to choose.
    expect(picker.props('attributes')).toBe(false)

    picker.vm.$emit('update:modelValue', { ...picker.props('modelValue'), target: 'none' })
    await flushPromises()

    expect(picker.props('modelValue')).toMatchObject({ target: 'none' })

    await wrapper.get('.wx-action-bar button').trigger('click')
    await flushPromises()

    const sent = put.mock.calls.at(-1)?.[1] as { options: Record<string, unknown> }
    expect(sent.options).not.toHaveProperty('redirect')
  })

  it('keeps a page of the site as the record, not as its address', async () => {
    const { wrapper, put } = await panel()

    const page = {
      target: 'entity',
      entity_type: 'page',
      entity_id: 7,
      url: null,
      hash: null,
      new_tab: false,
      rel: [],
    }
    wrapper.getComponent({ name: 'WxLinkPicker' }).vm.$emit('update:modelValue', page)
    await flushPromises()

    await wrapper.get('.wx-action-bar button').trigger('click')
    await flushPromises()

    expect(put).toHaveBeenCalledWith(
      '/api/cms/inbox/forms/1',
      expect.objectContaining({ options: expect.objectContaining({ redirect: page }) }),
    )
  })
})
