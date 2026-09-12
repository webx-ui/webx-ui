import { createApp, h, type App, type Component } from 'vue'
import { createRouter, createWebHistory, type Router, type RouteRecordRaw } from 'vue-router'
import { WebxUI } from '@webx-ui/core'
import AdminNav from './AdminNav.vue'
import AdminShell from './AdminShell.vue'
import { createAdminContext, provideAdmin, type AdminContext } from './admin'
import { createHttp, type Http } from './http'
import type { AdminModule, Manifest } from './types'

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

  const http =
    options.http ??
    createHttp({
      baseUrl: '',
      onUnauthenticated: () => {
        context.setUser(null)
        context.state.status = 'unauthenticated'
      },
    })

  const context = createAdminContext({
    http,
    basePath,
    apiPath,
    modules,
    loadManifest: async () => {
      const body = await http.get<{ data: Manifest }>(manifestUrl)

      return body.data
    },
  })

  const app = createApp(rootComponent(options))

  app.use(WebxUI)
  provideAdmin(app, context)

  const admin: Admin = {
    app,
    router,
    context,
    async mount() {
      app.mount(options.el ?? '#webx-app')

      await context.reload()
    },
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

function readManifestUrl(): string | null {
  if (typeof document === 'undefined') {
    return null
  }

  const meta = document.querySelector('meta[name="webx-manifest"]')

  return meta?.getAttribute('content') ?? null
}
