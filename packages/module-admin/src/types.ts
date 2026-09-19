import type { RouteRecordRaw } from 'vue-router'
import type { IconName } from '@webx-ui/core'
import type { TypeRegistry } from '@webx-ui/schema'
import type { LocaleDescriptor } from './i18n'

/**
 * What `GET /api/cms/manifest` answers with — the panel's own description of itself, and the
 * first thing the front end asks for. Mirrors `WebxUi\Admin\Manifest\ManifestBuilder`.
 */
export interface Manifest {
  /** What the panel is called: the corner's text when there is no logo, and the logo's alt. */
  title: string
  /** The client's own logo, when they have put one in the settings. */
  branding?: ManifestBranding
  /** Where the panel is served, e.g. `/cms`. Becomes the router's base. */
  path: string
  /** Where its JSON lives, e.g. `/api/cms`. */
  apiPath: string
  /** The language this administrator reads the panel in — their choice, not the site's. */
  locale: string
  /** The languages the site publishes content in. Editing screens are built around this. */
  locales: LocaleDescriptor[]
  /** The languages the interface itself can be switched to. */
  panelLocales: LocaleDescriptor[]
  /** Navigation groups, translated and in order; a module names one by id. */
  groups?: ManifestGroup[]
  modules: ManifestModule[]
  /** Names of the screens the server can hand out — the trees themselves travel on request. */
  screens?: string[]
}

/**
 * Two pictures rather than one and a cropping rule: a wordmark cut to a square is its first
 * two letters. A mark left unset means the rail keeps the shape it has always had.
 */
export interface ManifestBranding {
  logo: BrandingImage | null
  mark: BrandingImage | null
}

export interface BrandingImage {
  url: string
  /** The size the file was made at, when the server knows it — it reserves the room. */
  width: number | null
  height: number | null
}

export interface ManifestGroup {
  id: string
  title: string
  order: number
}

export interface ManifestModule {
  id: string
  title: string
  icon: string | null
  order: number
  /** The group the section sits under, or null for the top level. */
  group?: string | null
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
  /** The panel language they chose, or null if they never have. */
  locale?: string | null
  /** The key their photograph is stored under — not an address; the panel resolves it. */
  avatar?: string | null
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
  /**
   * The section the panel opens on.
   *
   * A panel is opened in the morning to see what happened overnight, and which section
   * answers that is a property of the installation rather than of the shell — so the module
   * says it, and the root route sends anybody who arrives at `/` there. Two modules claiming
   * it is not an error: the first one listed wins, and the other is still a section.
   */
  landing?: boolean
  /**
   * Screen node types this module brings — `wx-media` from the media module. Merged into the
   * registry every screen in the panel is drawn with.
   */
  types?: TypeRegistry
  /**
   * Opens a library and answers with the picture that was chosen, or `null` if nobody chose
   * one.
   *
   * The seam exists because the panel's own fields need a picture — `wx-rich-text` has an
   * image button — and the panel cannot depend on the module that has the files: it is the
   * other way round. A module supplies this, the panel asks for it, and a panel without one
   * simply does not offer the button.
   */
  pickImage?: () => Promise<PickedImage | null>
}

/**
 * A picture out of a library: where it is right now, and the key it is filed under.
 *
 * Both, because they answer different questions. The address is what draws the picture in this
 * browser this minute — it may be signed and about to expire, and it carries a version stamp
 * that changes the moment somebody crops the image. The key is what goes into the record, so
 * the address can be worked out again: a library that moves to another bucket, a site deployed
 * against a different CDN and an image edited in place all change the address and none of them
 * change the key.
 */
export interface PickedImage {
  url: string
  path?: string
}

export type AdminStatus = 'loading' | 'ready' | 'unauthenticated' | 'error'

export interface NavEntry {
  id: string
  title: string
  icon: string | null
  path: string
  /** Group id, or null at the top level. */
  group: string | null
}

/** A group with the entries that sit under it, in navigation order. */
export interface NavGroup {
  id: string
  title: string
  entries: NavEntry[]
}

/**
 * One line of a record's `···` menu.
 *
 * Written as data rather than as markup because the same list is read twice: the menu draws
 * it, and a section decides what belongs in it from what the server said the reader may do.
 * An action somebody has no right to is left out of the array, not passed with `disabled`.
 */
export interface RowAction {
  /** Unique within the menu. */
  key: string
  /** What it does, in words. Every line has them — that is the point of a menu. */
  label: string
  icon?: IconName
  /**
   * Destructive. Red, and moved to the bottom behind a rule wherever it was written.
   * It is never the thing a reader meant to hit.
   */
  danger?: boolean
  /** Offered but not possible right now — an address that does not exist yet. */
  disabled?: boolean
  /** Renders the line as a link. Opens in a new tab unless `target` says otherwise. */
  href?: string
  target?: string
  run?: () => void
}
