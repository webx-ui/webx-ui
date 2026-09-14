import { describe, expect, it, vi } from 'vitest'
import { computed, reactive } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { WebxUI } from '@webx-ui/core'
import {
  adminKey,
  createI18n,
  i18nKey,
  type AdminContext,
  type AdminUser,
} from '@webx-ui/module-admin'
import UserMenu from './UserMenu.vue'
import { authKey, avatarResolverKey, type AuthSession, type AvatarResolver } from './session'

function panel(user: AdminUser): AdminContext {
  const i18n = createI18n({ locale: 'en' })

  return {
    http: {} as never,
    basePath: '/cms',
    apiPath: '/api/cms',
    state: reactive({ status: 'ready', manifest: null, user, error: null }),
    i18n,
    modules: [],
    nav: computed(() => []),
    reload: () => Promise.resolve(),
    setLocale: () => Promise.resolve(),
    setUser() {},
    useSessionLoader() {},
    can: () => true,
  }
}

const session: AuthSession = {
  me: () => Promise.resolve(null),
  login: () => Promise.reject(new Error('not stubbed')),
  logout: () => Promise.resolve(),
  setLocale: () => Promise.resolve(),
}

function draw(user: AdminUser, resolve: AvatarResolver | null) {
  const admin = panel(user)

  return mount(UserMenu, {
    global: {
      plugins: [WebxUI],
      provide: {
        [adminKey as symbol]: admin,
        [i18nKey as symbol]: admin.i18n,
        [authKey as symbol]: session,
        [avatarResolverKey as symbol]: resolve,
      },
    },
  })
}

const alexx: AdminUser = {
  id: 1,
  name: 'Alexx',
  email: 'alexx@example.test',
  isSuper: true,
  permissions: [],
  avatar: 'people/alexx.jpg',
}

describe('WxUserMenu', () => {
  it('shows the photograph the session names, through the resolver the plugin was given', async () => {
    const resolve = vi.fn(async (key: string) => `https://cdn.example.test/${key}`)
    const wrapper = draw(alexx, resolve)

    await flushPromises()

    expect(resolve).toHaveBeenCalledWith('people/alexx.jpg')
    expect(wrapper.find('.wx-avatar img').attributes('src')).toBe(
      'https://cdn.example.test/people/alexx.jpg',
    )
  })

  it('falls back to initials without a resolver, as a panel with no library must', async () => {
    const wrapper = draw(alexx, null)

    await flushPromises()

    expect(wrapper.find('.wx-avatar img').exists()).toBe(false)
    expect(wrapper.text()).toContain('AL')
  })
})
