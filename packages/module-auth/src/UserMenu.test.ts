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
    groups: computed(() => ({ top: [], groups: [] })),
    types: {},
    loadScreen: () => Promise.resolve([]),
    screenPatch: () => [],
    reload: () => Promise.resolve(),
    refreshManifest: () => Promise.resolve(),
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

function draw(user: AdminUser, resolve: AvatarResolver | null, expanded = false) {
  const admin = panel(user)

  return mount(UserMenu, {
    props: { expanded },
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

  /* Initials tell two people apart; a name says who somebody is. The shell says which fits. */
  it('says the name where the shell reports room for it', async () => {
    expect(draw(alexx, null).find('.wx-user-menu__name').exists()).toBe(false)

    const wide = draw(alexx, null, true)

    expect(wide.find('.wx-user-menu__name').text()).toBe('Alexx')
  })
})
