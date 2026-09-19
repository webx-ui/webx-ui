import { computed, createApp, h, ref, type App, type Component } from 'vue'
import { createRouter, createWebHistory, type Router, type RouteRecordRaw } from 'vue-router'
import { dateLocaleKey, localesKey, WebxUI, type LocaleOption } from '@webx-ui/core'
import AdminLanding from './AdminLanding.vue'
import AdminNav from './AdminNav.vue'
import AdminShell from './AdminShell.vue'
import { createAdminContext, provideAdmin, type AdminContext } from './admin'
import { createHttp, type Http } from './http'
import { createI18n, provideI18n, type Dictionary, type I18n, type LocaleDescriptor } from './i18n'
import { adminMessages } from './messages'
import type { Patch, TypeRegistry } from '@webx-ui/schema'
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
  /**
   * Screen node types the project adds — `{ map: { component: WxMapField, kind: 'field' } }` —
   * over the core's and the modules'.
   */
  types?: TypeRegistry
  /**
   * The project's patches over the screens modules ship, by screen name, applied on the
   * client on top of what the server hands out. What a patch cannot do from here is open a
   * key for writing: the server decides what is saved.
   */
  screens?: Record<string, Patch>
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

  // The root, unless the panel brought its own — a project with a dashboard has already
  // answered this question and should not be overruled.
  if (!routes.some((route) => route.path === '/')) {
    const landing = modules.find((module) => module.landing === true)

    routes.push({
      path: '/',
      name: 'webx.home',
      component: AdminLanding,
      props: { landing: landing?.path ?? landing?.routes?.[0]?.path ?? null },
    })
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
    types: options.types,
    screens: options.screens,
    loadManifest: async () => {
      const body = await http.get<{ data: Manifest }>(manifestUrl)

      return body.data
    },
  })

  const app = createApp(rootComponent(options))

  app.use(WebxUI)
  provideAdmin(app, context)
  provideI18n(app, i18n)

  /*
   * The languages a localized field offers are the site's *content* languages, not the ones the
   * panel can be drawn in: a panel in English routinely edits a site published in Ukrainian and
   * Russian. They arrive with the manifest, so this is a computed over what is already there
   * rather than a second request — and a form written before they arrive simply has nothing to
   * switch between yet.
   */
  const editing = ref('')

  /*
   * A calendar is drawn in the language of the panel, not of the browser: the picker
   * would otherwise head a Russian screen with "Sep 2026" while every other date on it
   * goes through `useDates()` and reads Russian.
   */
  app.provide(
    dateLocaleKey,
    computed(() => i18n.state.locale),
  )

  app.provide(localesKey, {
    list: computed<LocaleOption[]>(() =>
      i18n.state.contentLocales.map((locale) => ({
        code: locale.code,
        label: locale.code.toUpperCase(),
      })),
    ),
    active: computed({
      get: () => editing.value || (i18n.state.contentLocales[0]?.code ?? ''),
      set: (code: string) => {
        editing.value = code
      },
    }),
  })

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

/** What the shell tells the navigation slot about where it is drawing it. */
interface NavSlotProps {
  collapsed?: boolean
  select?: () => void
  /** Whether the corner it is drawing the account in has room for a name beside the face. */
  expanded?: boolean
}

function rootComponent(options: CreateAdminOptions): Component {
  const slots: Record<string, (props: NavSlotProps) => unknown> = {
    // The menu is the panel's own: it is the manifest, drawn.
    // The shell hands in what a choice means where it drew the menu — in the drawer it is what
    // closes it, and a drawer that stays open over the section it just opened is a drawer the
    // reader has to dismiss by hand.
    nav: (props) => h(AdminNav, { collapsed: props.collapsed === true, onSelect: props.select }),
  }

  if (options.brand !== undefined) {
    slots.brand = () => h(options.brand as Component)
  }

  // The shell draws the account in three corners of different widths and says which; the menu
  // decides what it can show there.
  if (options.userMenu !== undefined) {
    slots.user = (props) => h(options.userMenu as Component, { expanded: props.expanded === true })
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
