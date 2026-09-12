import { createApp, h, type App, type Component } from 'vue'
import { createRouter, createWebHistory, type Router, type RouteRecordRaw } from 'vue-router'
import { WebxUI } from '@webx-ui/core'
import AdminNav from './AdminNav.vue'
import AdminShell from './AdminShell.vue'
import { createAdminContext, provideAdmin, type AdminContext } from './admin'
import { createHttp, type Http } from './http'
import { createI18n, provideI18n, type Dictionary, type I18n, type LocaleDescriptor } from './i18n'
import { adminMessages } from './messages'
import type { AdminModule, Manifest } from './types'

const STORED_LOCALE = 'webx.locale'

export interface CreateAdminOptions {
  /** Where to mount. Defaults to `#webx-app`, which is what the Blade shell renders. */
  el?: string | Element
  /**
   * Where the manifest lives. Defaults to the `webx-manifest` meta tag the Blade shell writes,
   * and to `/api/cms/manifest` when there is none — which is the case under a dev server,
   * where the page is Vite's own index.html.
   */
  manifestUrl?: string
  /**
   * Where the panel's JSON lives, e.g. `/api/cms`. Modules build their own addresses from it.
   * Derived from the manifest URL when not given.
   */
  apiPath?: string
  /** Sections of the panel. */
  modules?: AdminModule[]
  /** Routes that belong to no module: a dashboard, a 404. */
  routes?: RouteRecordRaw[]
  /**
   * Where the panel is served, for the router's history base. Taken from the manifest when it
   * arrives; given here for the first paint, before it has.
   */
  basePath?: string
  /** Replaces the panel's name in the header — a logo, usually. */
  brand?: Component
  /** The corner of the header: who is signed in, and the way out. */
  userMenu?: Component
  /** Extensions that need the router and the context: an auth module, most of all. */
  plugins?: AdminPlugin[]
  /**
   * The language to draw the panel in before the server has been asked. Defaults to the last
   * one used, then to the page's `lang`, then to the browser's. Whatever is chosen, the server
   * narrows it to a language the panel actually has.
   */
  locale?: string
  /** Swappable for tests. */
  http?: Http
}

/**
 * Something that needs the assembled panel rather than a slot in it — it adds routes, guards
 * the router, or tells the panel how to find out who is signed in.
 */
export interface AdminPlugin {
  install(admin: Admin): void
}

export interface Admin {
  app: App
  router: Router
  context: AdminContext
  i18n: I18n
  mount(): Promise<void>
}

/**
 * Assemble the panel.
 *
 * Mounting does not wait for the server. The manifest needs a signed-in session, so a visit
 * that starts at the sign-in screen would otherwise stare at a blank page until a request it
 * is bound to lose comes back.
 */
export function createAdmin(options: CreateAdminOptions = {}): Admin {
  const manifestUrl = options.manifestUrl ?? readManifestUrl() ?? '/api/cms/manifest'
  // The Blade shell writes the manifest address rather than the API root, and every module
  // needs the root, so it is read back out of the one thing the page does say.
  const apiPath = options.apiPath ?? manifestUrl.replace(/\/manifest\/?$/, '')
  const basePath = options.basePath ?? '/cms'
  const modules = options.modules ?? []

  const routes: RouteRecordRaw[] = [...(options.routes ?? [])]

  for (const module of modules) {
    routes.push(...(module.routes ?? []))
  }

  const router = createRouter({
    history: createWebHistory(basePath),
    routes,
  })

  const i18n = createI18n({ locale: options.locale ?? preferredLocale() })

  const http =
    options.http ??
    createHttp({
      baseUrl: '',
      onUnauthenticated: () => {
        context.setUser(null)
        context.state.status = 'unauthenticated'
      },
      // Every request says which language the panel is currently showing. It decides what
      // the server writes its own messages in — a 422 under a field — for anybody who has
      // not stored a preference yet, which is everybody until they choose one. Without it,
      // signing in on a Russian sign-in screen lands in an English panel.
      headers: () => ({ 'X-Webx-Locale': i18n.state.locale }),
    })

  i18n.defaults('webx-admin', adminMessages)

  async function loadDictionary(locale: string): Promise<void> {
    const body = await http.get<{
      data: { locale: string; fallback: string; namespaces: Dictionary }
    }>(`${apiPath}/translations/${locale}`)

    i18n.load(body.data.namespaces, body.data.locale, body.data.fallback)
    rememberLocale(body.data.locale)
    markDocumentLanguage(body.data.locale, i18n.state.panelLocales)
  }

  const context = createAdminContext({
    http,
    basePath,
    apiPath,
    modules,
    i18n,
    loadDictionary,
    loadManifest: async () => {
      const body = await http.get<{ data: Manifest }>(manifestUrl)

      return body.data
    },
  })

  const app = createApp(rootComponent(options))

  app.use(WebxUI)
  provideAdmin(app, context)
  provideI18n(app, i18n)

  const admin: Admin = {
    app,
    router,
    context,
    i18n,
    async mount() {
      // The one thing worth waiting for. It is a public, cached request, and painting the
      // sign-in screen in English and then swapping every label a moment later looks like a
      // bug rather than like a translation arriving. The manifest is still not waited for —
      // that one needs a session and is bound to 401 for a visitor.
      await Promise.all([loadPanelLocales(), loadDictionary(i18n.state.locale)]).catch(() => {
        // A server that cannot answer these cannot run a panel either, and the built-in
        // English is a better thing to fail with than a blank page.
      })

      app.mount(options.el ?? '#webx-app')

      await context.reload()
    },
  }

  async function loadPanelLocales(): Promise<void> {
    const body = await http.get<{
      data: { panel: LocaleDescriptor[]; content: LocaleDescriptor[] }
    }>(`${apiPath}/locales`)

    i18n.state.panelLocales = body.data.panel
    i18n.state.contentLocales = body.data.content
  }

  // Plugins go on before the router does, because installing the router is what starts the
  // first navigation. A route added after that is a route the visit already failed to match:
  // opening /login directly would land on nothing while /cms worked, because / matched and the
  // redirect to /login happened later, by which time the route existed.
  for (const plugin of options.plugins ?? []) {
    plugin.install(admin)
  }

  app.use(router)

  return admin
}

function rootComponent(options: CreateAdminOptions): Component {
  const slots: Record<string, (props: { collapsed?: boolean }) => unknown> = {
    // The menu is the panel's own: it is the manifest, drawn.
    nav: (props) => h(AdminNav, { collapsed: props.collapsed === true }),
  }

  if (options.brand !== undefined) {
    slots.brand = () => h(options.brand as Component)
  }

  if (options.userMenu !== undefined) {
    slots.user = () => h(options.userMenu as Component)
  }

  return { render: () => h(AdminShell, null, slots) }
}

/**
 * The language to ask for first. A guess, and treated as one — the server answers with the
 * language it actually has, and that is what the panel adopts.
 */
function preferredLocale(): string {
  const remembered = read(STORED_LOCALE)

  if (remembered !== null) {
    return remembered
  }

  if (typeof document !== 'undefined' && document.documentElement.lang !== '') {
    return document.documentElement.lang
  }

  return typeof navigator === 'undefined' ? 'en' : navigator.language
}

function rememberLocale(locale: string): void {
  // A per-browser convenience, so a signed-out reload of the sign-in screen keeps the
  // language. The choice that lasts is the one stored against the administrator.
  try {
    localStorage.setItem(STORED_LOCALE, locale)
  } catch {
    // Private windows, blocked site data. Nothing here is worth an error.
  }
}

function read(key: string): string | null {
  try {
    return localStorage.getItem(key)
  } catch {
    return null
  }
}

/** So the browser hyphenates, spell-checks and reads the page aloud in the right language. */
function markDocumentLanguage(locale: string, locales: LocaleDescriptor[]): void {
  if (typeof document === 'undefined') {
    return
  }

  document.documentElement.lang = locale
  document.documentElement.dir =
    locales.find((candidate) => candidate.code === locale)?.direction ?? 'ltr'
}

function readManifestUrl(): string | null {
  if (typeof document === 'undefined') {
    return null
  }

  const meta = document.querySelector('meta[name="webx-manifest"]')

  return meta?.getAttribute('content') ?? null
}
