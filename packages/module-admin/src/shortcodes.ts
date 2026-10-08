import { inject, shallowRef, type ShallowRef } from 'vue'
import { adminKey, type AdminContext } from './admin'

/** One shortcode of the site, as `GET <api>/shortcodes` answers with it. */
export interface Shortcode {
  name: string
  description: string | null
  /** What the site prints for it — trusted markup. */
  html: string
  /** The same as text: what a text field shows as its value. */
  plain: string
  /** Declared by a package in code, or added by hand in the settings. */
  origin: 'code' | 'settings'
}

/**
 * A shortcode as a text field offers it — the shape of `TokenOption` in `@webx-ui/core`, spelled
 * out here rather than imported so a panel built against an older core still compiles.
 */
export interface ShortcodeToken {
  name: string
  value?: string
  description?: string
}

export function shortcodeToken(shortcode: Shortcode): ShortcodeToken {
  return {
    name: shortcode.name,
    value: shortcode.plain,
    description: shortcode.description ?? undefined,
  }
}

/*
 * One request per panel, not per field: a page of blocks has dozens of text fields, and the
 * list changes when somebody edits the settings, not while a page is being written. Kept by the
 * context rather than in a module variable, so two panels in one tab — or two tests — do not
 * share one answer.
 */
const loaded = new WeakMap<AdminContext, Promise<ShortcodeToken[]>>()

export function loadShortcodes(admin: AdminContext): Promise<ShortcodeToken[]> {
  let pending = loaded.get(admin)

  if (!pending) {
    pending = admin.http
      .get<{ data: Shortcode[] }>(`${admin.apiPath}/shortcodes`)
      .then((body) => (Array.isArray(body.data) ? body.data.map(shortcodeToken) : []))
      .catch(() => {
        // A field works without suggestions; the next field opened asks again.
        loaded.delete(admin)
        return []
      })
    loaded.set(admin, pending)
  }

  return pending
}

/** Drops what was loaded, so the next field asks again — after the settings changed one. */
export function forgetShortcodes(admin: AdminContext): void {
  loaded.delete(admin)
}

/**
 * The site's shortcodes for a text field's `tokens`: empty at first and filled once the panel
 * has answered. Outside a panel — a demo, a test — it stays empty.
 */
export function useShortcodes(): ShallowRef<ShortcodeToken[]> {
  const admin = inject(adminKey, null)
  const tokens = shallowRef<ShortcodeToken[]>([])

  if (admin) void loadShortcodes(admin).then((list) => (tokens.value = list))

  return tokens
}
