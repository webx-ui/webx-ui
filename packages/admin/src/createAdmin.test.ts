import { beforeEach, describe, expect, it } from 'vitest'
import { h } from 'vue'
import { createAdmin } from './createAdmin'
import type { Http } from './http'
import type { Manifest } from './types'

const english = {
  code: 'en',
  name: 'English',
  nativeName: 'English',
  direction: 'ltr',
  default: true,
} as const

const manifest: Manifest = {
  title: 'WebX UI',
  path: '/cms',
  apiPath: '/api/cms',
  locale: 'en',
  locales: [english],
  panelLocales: [english],
  modules: [{ id: 'pages', title: 'Pages', icon: null, order: 0, permissions: [], meta: {} }],
}

const dictionary = {
  locale: 'en',
  fallback: 'en',
  namespaces: { 'webx-admin': { shell: { loading: 'Loading the panel…' } } },
}

/** The panel asks for three things at boot; answering all of them keeps the stub honest. */
function answer(url: string): unknown {
  if (url.includes('/translations/')) {
    return { data: dictionary }
  }

  if (url.endsWith('/locales')) {
    return { data: { panel: [english], content: [english] } }
  }

  return { data: manifest }
}

function stubHttp(overrides: Partial<Http> = {}): Http {
  const reject = () => Promise.reject(new Error('not stubbed'))

  return {
    get: ((url: string) => Promise.resolve(answer(url))) as never,
    post: reject as never,
    put: reject as never,
    patch: reject as never,
    delete: reject as never,
    ...overrides,
  }
}

/** A panel with no route for `/` warns, and that warning is not what these tests are about. */
const rootRoute = { path: '/', component: { render: () => h('div') } }

function mountPoint(): HTMLElement {
  const el = document.createElement('div')
  el.id = 'webx-app'
  document.body.append(el)

  return el
}

describe('createAdmin', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
    window.history.replaceState({}, '', '/cms')
  })

  it('resolves a route a plugin added, even when the visit starts on it', async () => {
    // The order that made this worth a test: installing the router is what starts the first
    // navigation, so a route added afterwards is one the visit has already failed to match.
    // Opening /cms worked and opening /cms/login directly showed an empty page.
    window.history.replaceState({}, '', '/cms/login')

    const admin = createAdmin({
      el: mountPoint(),
      basePath: '/cms',
      http: stubHttp(),
      plugins: [
        {
          install(created) {
            created.router.addRoute({
              path: '/login',
              meta: { public: true },
              component: { render: () => h('p', 'sign in') },
            })
          },
        },
      ],
    })

    await admin.mount()
    await admin.router.isReady()

    expect(admin.router.currentRoute.value.matched).toHaveLength(1)
    expect(document.body.textContent).toContain('sign in')
  })

  it('reads the manifest address out of the page when the shell wrote one', async () => {
    const meta = document.createElement('meta')
    meta.name = 'webx-manifest'
    meta.content = '/custom/cms/manifest'
    document.head.append(meta)

    let asked = ''

    const admin = createAdmin({
      el: mountPoint(),
      routes: [rootRoute],
      http: stubHttp({
        get: ((url: string) => {
          if (url.includes('/manifest')) {
            asked = url
          }

          return Promise.resolve(answer(url))
        }) as never,
      }),
    })

    await admin.mount()

    expect(asked).toBe('/custom/cms/manifest')
    // And the API root is read back out of it, because that is all the page says.
    expect(admin.context.apiPath).toBe('/custom/cms')

    meta.remove()
  })

  it('goes to ready once the manifest arrives', async () => {
    const admin = createAdmin({
      el: mountPoint(),
      http: stubHttp(),
      basePath: '/cms',
      routes: [rootRoute],
    })

    await admin.mount()

    expect(admin.context.state.status).toBe('ready')
    expect(admin.context.state.manifest?.title).toBe('WebX UI')
  })

  it('treats a 401 on the manifest as nobody being signed in, not as a failure', async () => {
    const admin = createAdmin({
      el: mountPoint(),
      basePath: '/cms',
      routes: [rootRoute],
      http: stubHttp({
        get: (() =>
          Promise.reject(Object.assign(new Error('Unauthenticated.'), { status: 401 }))) as never,
      }),
    })

    await admin.mount()

    expect(admin.context.state.status).toBe('unauthenticated')
    expect(admin.context.state.error).toBeNull()
  })

  it('says so when the panel genuinely could not start', async () => {
    const admin = createAdmin({
      el: mountPoint(),
      basePath: '/cms',
      routes: [rootRoute],
      http: stubHttp({
        get: (() =>
          Promise.reject(Object.assign(new Error('Server error'), { status: 500 }))) as never,
      }),
    })

    await admin.mount()

    expect(admin.context.state.status).toBe('error')
    expect(admin.context.state.error).toBe('Server error')
  })

  it('has its words before it paints, and adopts the administrator’s language after', async () => {
    // Two separate moments. The sign-in screen is drawn in whatever the browser asked for,
    // because there is nobody to ask yet; the manifest is the first time the panel learns
    // which language this particular administrator reads it in.
    const asked: string[] = []

    const admin = createAdmin({
      el: mountPoint(),
      basePath: '/cms',
      routes: [rootRoute],
      http: stubHttp({
        get: ((url: string) => {
          asked.push(url)

          if (url.includes('/translations/')) {
            return Promise.resolve({
              data: { ...dictionary, locale: url.endsWith('/uk') ? 'uk' : 'en' },
            })
          }

          if (url.includes('/manifest')) {
            return Promise.resolve({ data: { ...manifest, locale: 'uk' } })
          }

          return Promise.resolve(answer(url))
        }) as never,
      }),
    })

    await admin.mount()

    expect(asked.filter((url) => url.includes('/translations/'))).toEqual([
      '/api/cms/translations/en',
      '/api/cms/translations/uk',
    ])
    expect(admin.i18n.state.locale).toBe('uk')
    // The dictionary was asked for before the manifest: the first paint is not in English
    // and then something else a moment later.
    expect(asked.indexOf('/api/cms/translations/en')).toBeLessThan(
      asked.findIndex((url) => url.includes('/manifest')),
    )
  })

  it('shows only the modules both halves have', async () => {
    const admin = createAdmin({
      el: mountPoint(),
      basePath: '/cms',
      routes: [rootRoute],
      http: stubHttp(),
      // The server reports `pages`; the front end has `pages` and a `media` nobody asked for.
      modules: [
        { id: 'pages', routes: [{ path: '/pages', component: { render: () => h('div') } }] },
        { id: 'media', routes: [{ path: '/media', component: { render: () => h('div') } }] },
      ],
    })

    await admin.mount()

    expect(admin.context.nav.value.map((entry) => entry.id)).toEqual(['pages'])
  })
})
