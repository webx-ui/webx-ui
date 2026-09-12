import { computed, inject, reactive, type App, type ComputedRef, type InjectionKey } from 'vue'
import type { Http } from './http'
import type { AdminModule, AdminStatus, AdminUser, Manifest, NavEntry } from './types'

export interface AdminContext {
  /** The panel's own backend. */
  readonly http: Http
  /** Where the panel is served — the router's base. */
  readonly basePath: string
  /** Where its JSON lives, so a module does not have to be told twice. */
  readonly apiPath: string
  readonly state: AdminState
  /** Modules registered on the front end, whether or not the server reports them. */
  readonly modules: readonly AdminModule[]
  /** Navigation, in the order the server gave, for the modules that exist on both sides. */
  readonly nav: ComputedRef<NavEntry[]>
  /** Ask the server what the panel is and who is signed in again. */
  reload(): Promise<void>
  /** Filled in by an auth module; `null` means nobody is signed in. */
  setUser(user: AdminUser | null): void
  /**
   * How the panel finds out who is signed in, set by an auth module. Without one the panel
   * simply asks for the manifest and lets a 401 answer the question.
   */
  useSessionLoader(loader: () => Promise<AdminUser | null>): void
  can(permission: string): boolean
}

export interface AdminState {
  status: AdminStatus
  manifest: Manifest | null
  user: AdminUser | null
  error: string | null
}

export const adminKey: InjectionKey<AdminContext> = Symbol('webx-admin')

export function useAdmin(): AdminContext {
  const admin = inject(adminKey, null)

  if (admin === null) {
    throw new Error('useAdmin() was called outside a panel created by createAdmin().')
  }

  return admin
}

/**
 * Permissions are flattened by the server, so a check is a lookup. A super administrator
 * carries no permissions and passes everything — the same rule as on the server, in the one
 * place the front end asks the question.
 */
export function createAdminContext(options: {
  http: Http
  basePath: string
  apiPath: string
  modules: AdminModule[]
  loadManifest: () => Promise<Manifest>
}): AdminContext {
  const state = reactive<AdminState>({
    status: 'loading',
    manifest: null,
    user: null,
    error: null,
  })

  const nav = computed<NavEntry[]>(() => {
    const manifest = state.manifest

    if (manifest === null) {
      return []
    }

    const entries: NavEntry[] = []

    for (const module of manifest.modules) {
      const registered = options.modules.find((candidate) => candidate.id === module.id)

      // A module the server has and the front end does not is not a bug worth shouting
      // about — the panel is assembled from two halves and they are deployed separately —
      // but it has nowhere to send anybody, so it stays out of the menu.
      if (registered === undefined) {
        continue
      }

      const path = registered.path ?? registered.routes?.[0]?.path

      if (path === undefined) {
        continue
      }

      entries.push({
        id: module.id,
        title: module.title,
        icon: module.icon,
        path,
      })
    }

    return entries
  })

  let loadSession: (() => Promise<AdminUser | null>) | null = null

  async function reload(): Promise<void> {
    state.status = 'loading'
    state.error = null

    try {
      if (loadSession !== null) {
        state.user = await loadSession()

        // Asking for the manifest as a stranger would only produce the 401 we already know
        // about, and a spurious one in the network log for whoever is debugging.
        if (state.user === null) {
          state.manifest = null
          state.status = 'unauthenticated'

          return
        }
      }

      state.manifest = await options.loadManifest()
      state.status = 'ready'
    } catch (error) {
      // 401 is not a failure: it is the panel finding out nobody is signed in, which is the
      // normal way a visit starts.
      if (isUnauthenticated(error)) {
        state.manifest = null
        state.user = null
        state.status = 'unauthenticated'

        return
      }

      state.error = error instanceof Error ? error.message : String(error)
      state.status = 'error'
    }
  }

  return {
    http: options.http,
    basePath: options.basePath,
    apiPath: options.apiPath,
    state,
    modules: options.modules,
    nav,
    reload,
    setUser(user) {
      state.user = user
    },
    useSessionLoader(loader) {
      loadSession = loader
    },
    can(permission) {
      const user = state.user

      if (user === null) {
        return false
      }

      return user.isSuper || user.permissions.includes(permission)
    },
  }
}

export function provideAdmin(app: App, admin: AdminContext): void {
  app.provide(adminKey, admin)
}

function isUnauthenticated(error: unknown): boolean {
  return (
    typeof error === 'object' &&
    error !== null &&
    'status' in error &&
    (error as { status: unknown }).status === 401
  )
}
