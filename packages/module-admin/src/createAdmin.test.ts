import { beforeEach, describe, expect, it } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { h, inject } from 'vue'
import { dateTimezoneKey } from '@webx-ui/core'
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

  it('says an address nothing answers for is not there, with the way back', async () => {
    // A section this site does not have installed used to open as an empty content area.
    window.history.replaceState({}, '', '/cms/service-categories')

    const admin = createAdmin({
      el: mountPoint(),
      basePath: '/cms',
      routes: [rootRoute],
      http: stubHttp(),
      modules: [
        { id: 'pages', routes: [{ path: '/pages', component: { render: () => h('p', 'pages') } }] },
      ],
    })

    await admin.mount()
    await admin.router.isReady()
    await flushPromises()

    expect(admin.router.currentRoute.value.name).toBe('webx.not-found')
    expect(document.body.textContent).toContain('There is nothing at this address')

    await admin.router.push('/pages')
    await flushPromises()

    expect(document.body.textContent).toContain('pages')
    expect(document.body.textContent).not.toContain('There is nothing at this address')
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

  it("hands every date picker the site's clock from the manifest", async () => {
    let zone: unknown = 'not read'
    const reader = {
      setup() {
        const provided = inject(dateTimezoneKey)

        return () => {
          zone = provided === undefined || typeof provided === 'string' ? provided : provided.value

          return h('div')
        }
      },
    }
    const admin = createAdmin({
      el: mountPoint(),
      http: stubHttp({
        get: ((url: string) =>
          Promise.resolve(
            url.includes('/manifest')
              ? { data: { ...manifest, timezone: 'Asia/Hong_Kong' } }
              : answer(url),
          )) as never,
      }),
      basePath: '/cms',
      routes: [{ path: '/', component: reader }],
    })

    await admin.mount()
    await flushPromises()

    expect(zone).toBe('Asia/Hong_Kong')
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
    // The manifest came back in the administrator's language already; asking for it again
    // after adopting that language was the same request twice.
    expect(asked.filter((url) => url.includes('/manifest'))).toHaveLength(1)
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
  it('renames the sections when the language changes, without a reload', async () => {
    // The dictionary is not the whole interface: section titles are translated on the server
    // and arrive inside the manifest. Without refetching it the panel switched everything
    // except its own navigation, which went on naming the section in the language nobody was
    // reading any more.
    const titles: Record<string, string> = { en: 'Files', de: 'Dateien' }
    let locale = 'en'

    const admin = createAdmin({
      el: mountPoint(),
      basePath: '/cms',
      routes: [rootRoute],
      modules: [
        { id: 'pages', routes: [{ path: '/pages', component: { render: () => h('div') } }] },
      ],
      http: stubHttp({
        get: ((url: string) => {
          const asked = /\/translations\/(\w+)/.exec(url)

          if (asked !== null) {
            locale = asked[1] as string

            return Promise.resolve({ data: { ...dictionary, locale } })
          }

          if (url.includes('/manifest')) {
            return Promise.resolve({
              data: {
                ...manifest,
                modules: [{ ...manifest.modules[0], title: titles[locale] as string }],
              },
            })
          }

          return Promise.resolve(answer(url))
        }) as never,
      }),
    })

    await admin.mount()

    expect(admin.context.nav.value[0]?.title).toBe('Files')

    await admin.context.setLocale('de')

    expect(admin.i18n.state.locale).toBe('de')
    expect(admin.context.nav.value[0]?.title).toBe('Dateien')
  })

  it('asks for a dictionary once per language and for a stored file once per visit', async () => {
    const asked: string[] = []
    const resolved: string[][] = []

    const admin = createAdmin({
      el: mountPoint(),
      basePath: '/cms',
      routes: [rootRoute],
      modules: [
        {
          id: 'pages',
          assetUrls: (paths) => {
            resolved.push(paths)

            return Promise.resolve(
              Object.fromEntries(
                paths.map((path) => [path, path === 'lost.jpg' ? null : `/s/${path}`]),
              ),
            )
          },
        },
      ],
      http: stubHttp({
        get: ((url: string) => {
          asked.push(url)

          const locale = /\/translations\/(\w+)/.exec(url)?.[1]

          return Promise.resolve(
            locale === undefined ? answer(url) : { data: { ...dictionary, locale } },
          )
        }) as never,
      }),
    })

    await admin.mount()
    await admin.context.setLocale('de')
    await admin.context.setLocale('en')

    // In whichever order the first guess put them — this browser may remember either.
    expect(asked.filter((url) => url.includes('/translations/')).sort()).toEqual([
      '/api/cms/translations/de',
      '/api/cms/translations/en',
    ])
    expect(admin.i18n.state.locale).toBe('en')

    const urls = admin.context.assetUrls!
    const [first, second] = await Promise.all([urls(['me.jpg']), urls(['me.jpg', 'lost.jpg'])])

    expect(first).toEqual({ 'me.jpg': '/s/me.jpg' })
    expect(second).toEqual({ 'me.jpg': '/s/me.jpg', 'lost.jpg': null })
    expect(await urls(['me.jpg', 'lost.jpg'])).toEqual({ 'me.jpg': '/s/me.jpg', 'lost.jpg': null })
    // The photograph once; the lost file again, since an upload may have brought it back.
    expect(resolved).toEqual([['me.jpg'], ['lost.jpg'], ['lost.jpg']])
  })
  it('opens on the section that claimed the root, and on the first one when none did', async () => {
    // Nothing answered at '/' until now: the routes are the modules', and none of them was
    // the front page. Signing in landed on a blank screen.
    const section = (name: string) => ({ render: () => h('p', name) })

    const listed: Manifest = {
      ...manifest,
      modules: [
        { id: 'pages', title: 'Pages', icon: null, order: 0, permissions: [], meta: {} },
        { id: 'inbox', title: 'Inbox', icon: null, order: 1, permissions: [], meta: {} },
      ],
    }

    const http = stubHttp({
      get: ((url: string) =>
        Promise.resolve(url.includes('/manifest') ? { data: listed } : answer(url))) as never,
    })

    const pages = {
      id: 'pages',
      path: '/pages',
      routes: [{ path: '/pages', component: section('pages') }],
    }
    const inbox = {
      id: 'inbox',
      path: '/inbox',
      landing: true,
      routes: [{ path: '/inbox', component: section('inbox') }],
    }

    const claimed = createAdmin({
      el: mountPoint(),
      basePath: '/cms',
      http,
      modules: [pages, inbox],
    })

    await claimed.mount()
    await flushPromises()

    expect(claimed.router.currentRoute.value.path).toBe('/inbox')

    // Without a claim it is the first entry of the menu, which is the order the server gave.
    document.body.innerHTML = ''
    window.history.replaceState({}, '', '/cms')

    const unclaimed = createAdmin({
      el: mountPoint(),
      basePath: '/cms',
      http,
      modules: [pages, { ...inbox, landing: false }],
    })

    await unclaimed.mount()
    await flushPromises()

    expect(unclaimed.router.currentRoute.value.path).toBe('/pages')
  })
})
