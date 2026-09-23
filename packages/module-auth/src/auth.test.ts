import { afterEach, describe, expect, it, vi } from 'vitest'
import { computed, createApp, nextTick, reactive } from 'vue'
import { flushPromises } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { createI18n, createTheme, type Admin, type AdminContext } from '@webx-ui/module-admin'
import { auth } from './index'

/**
 * The plugin around the sign-in screen, with what happens after it: where the person is taken
 * once they are in.
 */
function panel(status: 'unauthenticated' | 'ready' = 'unauthenticated'): Admin {
  const i18n = createI18n({ locale: 'en' })

  const context: AdminContext = {
    http: { get: () => Promise.resolve({ data: null }), post: () => Promise.resolve({}) } as never,
    basePath: '/cms',
    apiPath: '/api/cms',
    state: reactive({ status, manifest: null, user: null, error: null }),
    i18n,
    modules: [],
    nav: computed(() => []),
    groups: computed(() => ({ top: [], groups: [] })),
    types: {},
    pickImage: null,
    loadScreen: () => Promise.resolve([]),
    screenPatch: () => [],
    reload: () => Promise.resolve(),
    refreshManifest: () => Promise.resolve(),
    setLocale: () => Promise.resolve(),
    setUser() {},
    useSessionLoader() {},
    can: () => true,
  }

  const router = createRouter({
    history: createMemoryHistory('/cms'),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/pages', component: { template: '<div />' } },
    ],
  })

  return {
    app: createApp({ template: '<div />' }),
    router,
    context,
    i18n,
    theme: createTheme(),
    mount: () => Promise.resolve(),
  }
}

async function signIn(admin: Admin): Promise<void> {
  admin.context.state.status = 'ready'
  await nextTick()
  // The watcher answers with a navigation, and a navigation is a promise of its own.
  await flushPromises()
}

describe('after signing in', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('goes on to the panel route the person was heading for', async () => {
    const admin = panel()
    auth().install(admin)
    await admin.router.push('/pages')

    expect(admin.router.currentRoute.value.path).toBe('/login')
    expect(admin.router.currentRoute.value.query.next).toBe('/pages')

    await signIn(admin)

    expect(admin.router.currentRoute.value.path).toBe('/pages')
  })

  it('loads a whole address on this site as a page rather than as a route', async () => {
    const assign = vi.fn()
    vi.stubGlobal('location', { origin: 'https://site.test', assign })

    const admin = panel()
    auth().install(admin)
    const consent = 'https://site.test/oauth/authorize?client_id=1&state=x'
    await admin.router.push({ path: '/login', query: { next: consent } })

    await signIn(admin)

    expect(assign).toHaveBeenCalledWith(consent)
    expect(admin.router.currentRoute.value.path).toBe('/login')
  })

  it('does not follow a whole address on another site', async () => {
    const assign = vi.fn()
    vi.stubGlobal('location', { origin: 'https://site.test', assign })

    const admin = panel()
    auth().install(admin)
    await admin.router.push({
      path: '/login',
      query: { next: 'https://elsewhere.test/oauth/authorize' },
    })

    await signIn(admin)

    expect(assign).not.toHaveBeenCalled()
  })
})
