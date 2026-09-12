import type { RouteRecordRaw } from 'vue-router'

/**
 * What `GET /api/cms/manifest` answers with — the panel's own description of itself, and the
 * first thing the front end asks for. Mirrors `WebxUi\Admin\Manifest\ManifestBuilder`.
 */
export interface Manifest {
  title: string
  /** Where the panel is served, e.g. `/cms`. Becomes the router's base. */
  path: string
  /** Where its JSON lives, e.g. `/api/cms`. */
  apiPath: string
  modules: ManifestModule[]
}

export interface ManifestModule {
  id: string
  title: string
  icon: string | null
  order: number
  permissions: string[]
  /** Whatever the server-side module wanted to say, in its own room. */
  meta: Record<string, unknown>
}

/**
 * Whoever is signed in. Filled in by an auth module — this package defines the shape and
 * answers `can()` from it, but knows nothing about how anybody signs in.
 */
export interface AdminUser {
  id: number | string
  name: string
  email: string
  isSuper: boolean
  permissions: string[]
  [key: string]: unknown
}

/**
 * A section of the panel, on the front end.
 *
 * Pairs with a module on the server by `id`: the server says a module exists and what it is
 * called, this says what it looks like. A front-end module the server does not report is not
 * shown — the panel is whatever the installation actually has.
 */
export interface AdminModule {
  /** Same id the server-side module answers to. */
  id: string
  /** Routes mounted under the panel's base path. */
  routes?: RouteRecordRaw[]
  /** Where the navigation entry points. Defaults to the first route's path. */
  path?: string
  /**
   * Shown before the manifest has arrived, and for routes outside it — the sign-in screen is
   * the reason this exists.
   */
  public?: boolean
}

export type AdminStatus = 'loading' | 'ready' | 'unauthenticated' | 'error'

export interface NavEntry {
  id: string
  title: string
  icon: string | null
  path: string
}
