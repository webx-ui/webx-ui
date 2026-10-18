import { describe, expect, it, vi } from 'vitest'
import { computed, reactive } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { WebxUI } from '@webx-ui/core'
import {
  adminKey,
  createI18n,
  createTheme,
  i18nKey,
  themeKey,
  type AdminContext,
  type AdminUser,
} from '@webx-ui/module-admin'
import UserMenu from './UserMenu.vue'
import { authKey, avatarResolverKey, type AuthSession, type AvatarResolver } from './session'

function panel(user: AdminUser, siteUrl: string | null = null): AdminContext {
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
    siteUrl: computed(() => siteUrl),
    types: {},
    pickImage: null,
    assetUrls: null,
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

const setTheme = vi.fn(() => Promise.resolve())
const setSiteBar = vi.fn(() => Promise.resolve())

const session: AuthSession = {
  me: () => Promise.resolve(null),
  login: () => Promise.reject(new Error('not stubbed')),
  logout: () => Promise.resolve(),
  setLocale: () => Promise.resolve(),
  setTheme,
  setSiteBar,
  updateProfile: () => Promise.reject(new Error('not stubbed')),
  requestEmailChange: () => Promise.reject(new Error('not stubbed')),
}

function draw(
  user: AdminUser,
  resolve: AvatarResolver | null,
  expanded = false,
  siteUrl: string | null = null,
) {
  const admin = panel(user, siteUrl)

  return mount(UserMenu, {
    props: { expanded },
    global: {
      plugins: [WebxUI],
      provide: {
        [adminKey as symbol]: admin,
        [i18nKey as symbol]: admin.i18n,
        [authKey as symbol]: session,
        [themeKey as symbol]: createTheme({ storageKey: null }),
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

  /*
   * The switch paints the panel itself and then tells the server, which is why the account
   * call is the thing worth asserting: the colour has already changed by then.
   */
  it('writes the theme down against the administrator', async () => {
    const wrapper = draw(alexx, null)

    await wrapper.get('.wx-user-menu__trigger').trigger('click')
    await flushPromises()

    const dark = document.querySelectorAll('.wx-theme-switch [role="radio"]')[2]

    dark?.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await flushPromises()

    expect(setTheme).toHaveBeenCalledWith('dark')
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark')

    document.documentElement.removeAttribute('data-theme')
  })

  /*
   * The panel on the site's own pages, switched off for this person alone (no dictionary here,
   * so the item is found by its key). The server decides
   * on every page whether to put it there, so the call is the whole of it.
   */
  it('switches the panel on the site off for this administrator', async () => {
    const wrapper = draw({ ...alexx, siteBar: true }, null)

    await wrapper.get('.wx-user-menu__trigger').trigger('click')
    await flushPromises()

    const item = [...document.querySelectorAll('.wx-dropdown-item')].find((node) =>
      node.textContent?.includes('menu.site-bar'),
    )

    item?.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await flushPromises()

    expect(setSiteBar).toHaveBeenCalledWith(false)

    wrapper.unmount()
  })

  it('offers no switch where the server has no panel on the site', async () => {
    const wrapper = draw(alexx, null)

    await wrapper.get('.wx-user-menu__trigger').trigger('click')
    await flushPromises()

    expect(document.body.textContent).not.toContain('menu.site-bar')

    wrapper.unmount()
  })

  it('offers the way to the site, in a new tab, where the panel knows one', async () => {
    const wrapper = draw(alexx, null, false, 'https://shop.example.test/')

    await wrapper.get('.wx-user-menu__trigger').trigger('click')
    await flushPromises()

    const link = [...document.querySelectorAll<HTMLAnchorElement>('a.wx-dropdown-item')].find(
      (node) => node.textContent?.includes('nav.open-site'),
    )

    expect(link?.getAttribute('href')).toBe('https://shop.example.test/')
    expect(link?.getAttribute('target')).toBe('_blank')
    expect(link?.getAttribute('rel')).toBe('noopener')

    wrapper.unmount()
  })

  it('offers no way to the site where the project hid it', async () => {
    const wrapper = draw(alexx, null)

    await wrapper.get('.wx-user-menu__trigger').trigger('click')
    await flushPromises()

    expect(document.body.textContent).not.toContain('nav.open-site')

    wrapper.unmount()
  })

  /* Initials tell two people apart; a name says who somebody is. The shell says which fits. */
  it('says the name where the shell reports room for it', async () => {
    expect(draw(alexx, null).find('.wx-user-menu__name').exists()).toBe(false)

    const wide = draw(alexx, null, true)

    expect(wide.find('.wx-user-menu__name').text()).toBe('Alexx')
    expect(wide.find('.wx-user-menu__email').text()).toBe('alexx@example.test')
    expect(wide.find('.wx-user-menu__chevron').exists()).toBe(true)
  })
})
