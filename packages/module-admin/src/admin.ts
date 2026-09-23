import { computed, inject, reactive, type App, type ComputedRef, type InjectionKey } from 'vue'
import type { Patch, ScreenNode, TypeRegistry } from '@webx-ui/schema'
import { adminTypes } from './screenTypes'
import type { Http } from './http'
import type { I18n } from './i18n'
import type { ThemeController } from './theme'
import type {
  AdminModule,
  AdminStatus,
  AdminUser,
  Manifest,
  NavEntry,
  NavGroup,
  PickedImage,
} from './types'

export interface AdminContext {
  /** The panel's own backend. */
  readonly http: Http
  /** Where the panel is served — the router's base. */
  readonly basePath: string
  /** Where its JSON lives, so a module does not have to be told twice. */
  readonly apiPath: string
  readonly state: AdminState
  /** The interface's own words, and the languages it can be shown in. */
  readonly i18n: I18n
  /** Modules registered on the front end, whether or not the server reports them. */
  readonly modules: readonly AdminModule[]
  /** Navigation, in the order the server gave, for the modules that exist on both sides. */
  readonly nav: ComputedRef<NavEntry[]>
  /** The same navigation, with the top-level entries first and then each group's. */
  readonly groups: ComputedRef<{ top: NavEntry[]; groups: NavGroup[] }>
  /** Node types every screen is drawn with: the modules' and the project's, over the core. */
  readonly types: TypeRegistry
  /**
   * How the panel picks a picture, when a module installed here has a library. `null` when
   * none does — and a field that needs one then does not offer to.
   */
  readonly pickImage: (() => Promise<PickedImage | null>) | null
  /** Ask the server what the panel is and who is signed in again. */
  reload(): Promise<void>
  /**
   * Fetch the manifest again and adopt it, without the panel going through `loading` — so the
   * chrome changes under whoever is looking at it instead of being replaced by a spinner.
   *
   * For a screen that has just saved something the manifest reports: the branding, the name of
   * the site, what a section is called. A failure is swallowed, because what it would cost is
   * a working panel in exchange for a stale logo.
   */
  refreshManifest(): Promise<void>
  /**
   * Draw the panel in another language: fetches that dictionary and remembers the choice for
   * the next visit. Storing it against the administrator is an auth module's business — this
   * only changes what is on screen.
   */
  setLocale(code: string): Promise<void>
  /** Filled in by an auth module; `null` means nobody is signed in. */
  setUser(user: AdminUser | null): void
  /**
   * How the panel finds out who is signed in, set by an auth module. Without one the panel
   * simply asks for the manifest and lets a 401 answer the question.
   */
  useSessionLoader(loader: () => Promise<AdminUser | null>): void
  can(permission: string): boolean
  /**
   * A screen by name, as the server hands it out — patched, cut to this administrator's
   * permissions, translated. Fetched once per name and language for the session.
   */
  loadScreen(name: string): Promise<ScreenNode[]>
  /** The project's own operations over a screen, from `createAdmin({ screens })`. */
  screenPatch(name: string): Patch
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
  i18n: I18n
  loadManifest: () => Promise<Manifest>
  loadDictionary?: (locale: string) => Promise<void>
  types?: TypeRegistry
  screens?: Record<string, Patch>
  /** Told who is signed in, so the theme they chose follows them to this machine. */
  theme?: ThemeController
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
        group: module.group ?? null,
      })
    }

    return entries
  })

  const groups = computed(() => {
    const declared = state.manifest?.groups ?? []
    const top: NavEntry[] = []
    const byGroup = new Map<string, NavEntry[]>()

    for (const entry of nav.value) {
      // A group the server never declared is not a group: the entry stays at the top rather
      // than vanishing under a heading nobody can name.
      if (entry.group !== null && declared.some((group) => group.id === entry.group)) {
        const list = byGroup.get(entry.group) ?? []
        list.push(entry)
        byGroup.set(entry.group, list)
      } else {
        top.push(entry)
      }
    }

    return {
      top,
      groups: declared
        .filter((group) => byGroup.has(group.id))
        .map((group) => ({
          id: group.id,
          title: group.title,
          icon: group.icon ?? null,
          entries: byGroup.get(group.id) ?? [],
        })),
    }
  })

  // The core types are the renderer's own default; what is merged here is only what the panel
  // adds — its own frame, then a module's, then the project's, which therefore wins.
  const types: TypeRegistry = { ...adminTypes }

  for (const module of options.modules) {
    Object.assign(types, module.types)
  }

  Object.assign(types, options.types)

  // The first module that has a library wins. Two of them is not a case worth a setting: a
  // panel with two file managers has a bigger question to answer than which one this opens.
  const pickImage = options.modules.find((module) => module.pickImage !== undefined)?.pickImage

  const screens = new Map<string, Promise<ScreenNode[]>>()

  async function loadScreen(name: string): Promise<ScreenNode[]> {
    // The tree is translated on the server, so a screen is one thing per language.
    const key = `${options.i18n.state.locale}:${name}`
    let pending = screens.get(key)

    if (pending === undefined) {
      pending = options.http
        .get<{ data: { screen: string; root: ScreenNode[] } }>(`${options.apiPath}/screens/${name}`)
        .then((body) => body.data.root)
        .catch((error: unknown) => {
          // A failed request is not worth remembering: the next page open asks again.
          screens.delete(key)
          throw error
        })
      screens.set(key, pending)
    }

    return pending
  }

  let loadSession: (() => Promise<AdminUser | null>) | null = null

  async function reload(): Promise<void> {
    state.status = 'loading'
    state.error = null

    try {
      if (loadSession !== null) {
        state.user = await loadSession()
        adoptTheme(state.user)

        // Asking for the manifest as a stranger would only produce the 401 we already know
        // about, and a spurious one in the network log for whoever is debugging.
        if (state.user === null) {
          state.manifest = null
          state.status = 'unauthenticated'

          return
        }
      }

      const manifest = await options.loadManifest()

      state.manifest = manifest
      options.i18n.state.contentLocales = manifest.locales ?? []
      options.i18n.state.panelLocales = manifest.panelLocales ?? options.i18n.state.panelLocales

      // The administrator's own choice, which the sign-in screen had no way of knowing: it
      // drew itself in whatever the browser asked for.
      if (manifest.locale !== undefined && manifest.locale !== options.i18n.state.locale) {
        await setLocale(manifest.locale)
      }

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

  async function refreshManifest(): Promise<void> {
    // Nothing to replace before the first load, and nothing worth asking for after a session
    // has ended: `reload()` owns both of those cases.
    if (state.manifest === null) {
      return
    }

    try {
      state.manifest = await options.loadManifest()
    } catch {
      // Deliberately silent — see the contract.
    }
  }

  /**
   * The theme is a property of the person, like the language: chosen once and found again on
   * the next machine they sign in on. Until then the panel is drawn in whatever this browser
   * remembers, which is what the sign-in screen had to go on.
   */
  function adoptTheme(user: AdminUser | null): void {
    if (user !== null) {
      options.theme?.adopt(user.theme)
    }
  }

  async function setLocale(code: string): Promise<void> {
    if (options.loadDictionary === undefined) {
      options.i18n.state.locale = code

      return
    }

    await options.loadDictionary(code)

    // The dictionary is not the whole of the interface. Section titles are translated on the
    // server and travel inside the manifest, which was fetched in the previous language — so
    // without this the panel switches everything except its own navigation, and the sidebar
    // goes on naming the section in the language nobody is reading any more until the page is
    // reloaded. Only worth doing once there is a manifest to replace: during the first load
    // the caller is `reload()` itself, which is about to fetch one. A manifest that will not
    // come back is `reload()`'s problem to report — the language did change, and a stale
    // section title is not worth throwing away a working panel for.
    await refreshManifest()
  }

  return {
    http: options.http,
    basePath: options.basePath,
    apiPath: options.apiPath,
    state,
    i18n: options.i18n,
    modules: options.modules,
    nav,
    groups,
    types,
    pickImage: pickImage ?? null,
    reload,
    refreshManifest,
    setLocale,
    setUser(user) {
      state.user = user
      adoptTheme(user)
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
    loadScreen,
    screenPatch(name) {
      return options.screens?.[name] ?? []
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
