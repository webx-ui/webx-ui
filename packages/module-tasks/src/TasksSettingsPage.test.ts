import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import {
  adminKey,
  adminMessages,
  adminTypes,
  createI18n,
  HttpError,
  i18nKey,
  type AdminContext,
} from '@webx-ui/module-admin'
import { localesKey } from '@webx-ui/core'
import type * as Core from '@webx-ui/core'
import { coreTypes, type ScreenNode } from '@webx-ui/schema'
import TasksSettingsPage from './TasksSettingsPage.vue'

const { toast } = vi.hoisted(() => ({ toast: { success: vi.fn(), danger: vi.fn() } }))

vi.mock('@webx-ui/core', async (original) => ({ ...(await original<typeof Core>()), toast }))

const screen: ScreenNode[] = [
  { id: 'token', type: 'wx-input', name: 'tasks.telegram.token' },
  { id: 'chats', type: 'wx-input', name: 'tasks.telegram.chats' },
]

/* What the server answers once a token was saved and the page is opened afresh. */
const saved = {
  values: { 'tasks.telegram.token': '', 'tasks.telegram.chats': '111' },
  token_set: true,
}

async function page(post: ReturnType<typeof vi.fn>) {
  const put = vi.fn().mockResolvedValue({ data: saved })
  const i18n = createI18n()
  i18n.defaults('webx-admin', adminMessages)

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get: vi.fn().mockResolvedValue({ data: saved }), post, put, delete: vi.fn() },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
    types: { ...coreTypes, ...adminTypes },
    loadScreen: () => Promise.resolve(screen),
    screenPatch: () => [],
  } as unknown as AdminContext

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:any(.*)*', component: { template: '<div />' } }],
  })

  const wrapper = mount(TasksSettingsPage, {
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
  })

  await flushPromises()

  return { wrapper, put }
}

const button = (wrapper: Awaited<ReturnType<typeof page>>['wrapper'], text: string) =>
  wrapper.findAll('button').find((b) => b.text() === text)!

afterEach(() => {
  toast.success.mockReset()
  toast.danger.mockReset()
  document.body.innerHTML = ''
})

describe('WxTasksSettingsPage', () => {
  it('says what Telegram said when the test message is refused, not that somebody changed it', async () => {
    const words = 'Telegram did not take the message — 111: Unauthorized'
    const post = vi.fn().mockRejectedValue(new HttpError(words, 422, {}, null, { message: words }))
    const { wrapper } = await page(post)

    await button(wrapper, 'Send a test message').trigger('click')
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/tasks/settings/test', {})
    expect(toast.danger).toHaveBeenCalledWith(words)
  })

  it('saves over a token set earlier, sending the empty field that keeps it', async () => {
    const { wrapper, put } = await page(vi.fn())

    await wrapper.find('input[name="tasks.telegram.chats"]').setValue('111,222')
    await button(wrapper, 'Save').trigger('click')
    await flushPromises()

    expect(put).toHaveBeenCalledWith('/api/cms/tasks/settings', {
      values: { 'tasks.telegram.token': '', 'tasks.telegram.chats': '111,222' },
      forget_token: undefined,
    })
    expect(toast.danger).not.toHaveBeenCalled()
    expect(toast.success).toHaveBeenCalledWith('The settings are saved.')
  })
})
