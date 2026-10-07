import type { RouteRecordRaw } from 'vue-router'
import type { ButtonVariant, IconName } from '@webx-ui/core'
import type { TypeRegistry } from '@webx-ui/schema'
import type { ThemePreference } from '@webx-ui/tokens'
import type { AdminContext } from './admin'
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
  /**
   * The site's clock, an IANA zone such as `Asia/Hong_Kong`: every date picker of a moment shows
   * and takes it there, whatever zone the editor's machine is in. Absent from an older server.
   */
  timezone?: string
  /** Navigation groups, translated and in order; a module names one by id. */
  groups?: ManifestGroup[]
  modules: ManifestModule[]
  /** Names of the screens the server can hand out — the trees themselves travel on request. */
  screens?: string[]
  /**
   * When the database was last dumped. Absent or `null` on a site that has switched the
   * nightly backup off, and present with `at: null` on one that has it on and has never
   * produced a file — which is the case the panel most needs to say out loud.
   */
  backup?: ManifestBackup | null
}

/**
 * The nightly dump, as the server sees it: the newest file in the backup directory and how
 * big it is. There is no record of it anywhere else on purpose — a task that failed is the
 * file that is not there.
 */
export interface ManifestBackup {
  /** ISO-8601, UTC. `null` when the directory is empty. */
  at: string | null
  bytes: number | null
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
  /** A name from the icon set; `null` or absent leaves the branch with the default picture. */
  icon?: string | null
  order: number
  /** Captions inside the group, in order; a module stands under one by naming its id. */
  sections?: ManifestSection[]
}

/** A caption inside a navigation group: the catalogue's «Dictionaries». */
export interface ManifestSection {
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
  /** The caption inside the group it stands under, or null among the group's plain entries. */
  section?: string | null
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
  /**
   * The theme they chose, or null for the machine's — which is a choice too, and one that
   * has to travel with them. A server that has never heard of themes sends nothing at all,
   * and then this browser's own choice stands.
   */
  theme?: ThemePreference | null
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
  /**
   * Where library keys live now, by key; `null` for a key the library no longer has.
   *
   * The other half of {@link pickImage}. A document keeps the key of every picture beside an
   * address, and the address is only true on the day it was written: the site's domain, the
   * disk and the version stamp all move under it. The site works the address out again on
   * every read; the panel asks this before handing a document to an editor, so a paragraph
   * written on another host does not open with pictures pointing there.
   */
  assetUrls?: (paths: string[], admin: AdminContext) => Promise<Record<string, string | null>>
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
  /** Caption id inside the group, or null among its plain entries. */
  section: string | null
}

/** A group with the entries that sit under it, in navigation order. */
export interface NavGroup {
  id: string
  title: string
  icon: string | null
  /** The entries under no caption — they come first. */
  entries: NavEntry[]
  /** Captions that have entries, in the order the server declared them. */
  sections: NavSection[]
}

/** A caption inside a group with the entries under it. */
export interface NavSection {
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

/**
 * One thing a screen offers, in its head.
 *
 * The same reason a row's menu is data: the head draws the action twice over its life — as a
 * button while there is room for one, as a line of the `···` once there is not — and a vnode
 * cannot be mounted in two places (CLAUDE.md §4). Declared once, `WxScreenHead` decides which
 * it is at the width it happens to have.
 */
export interface ScreenAction extends RowAction {
  /**
   * The one action the screen exists for: filled, blue, with the word on it. It is the one
   * that stays a button when everything else folds into the menu. One per screen (§18.3) —
   * a screen with two of these has none.
   */
  primary?: boolean
  /**
   * Never a button: this one lives in the `···` at every width. A destructive action is there
   * whether it says so or not — red beside the name of the record is not where it belongs.
   */
  menu?: boolean
  /** Shows a spinner and blocks the button: saving, publishing. */
  loading?: boolean
  /** Weight of a button that is not the primary one. `outline` unless said otherwise. */
  variant?: ButtonVariant
}

/**
 * One filter that is on, said in the reader's words.
 *
 * A shut panel of filters says nothing about itself, and a list narrowed by something nobody
 * can see is a list that looks wrong. Sections build these — only a section knows that
 * `rubric=2` reads "Rubric: News" — and `WxFilterChips` draws them the same way everywhere.
 */
export interface AppliedFilter {
  /** Unique within the strip. */
  key: string
  /** What it says on the chip: the field and its value. */
  label: string
  /** Takes this one filter off. */
  clear: () => void
}
