import type { AdminContext } from './admin'

/** What a link points at, said out loud rather than guessed from what is filled in. */
export type LinkTarget = 'entity' | 'url' | 'none'

/** The three `rel` values an editor may choose. `noopener` is added by whoever renders. */
export type LinkRel = 'nofollow' | 'sponsored' | 'ugc'

/**
 * A link, in the shape the server keeps it — the same keys the menu's columns use, so a menu item
 * and a field hold one value and go through one validation.
 */
export interface LinkValue {
  target: LinkTarget
  entity_type: string | null
  entity_id: number | null
  url: string | null
  /**
   * The fragment, without its `#` — kept apart from the address because a chosen page has
   * nowhere to write one otherwise, and because what the address is differs by target while the
   * anchor does not.
   */
  hash: string | null
  new_tab: boolean
  rel: LinkRel[]
}

/** One section of the picker: a kind of thing this administrator may link to. */
export interface LinkSourceInfo {
  type: string
  title: string
  icon: string | null
}

/** One thing that can be linked to, as the picker draws it. */
export interface LinkCandidate {
  id: number
  title: string
  url: string | null
  /**
   * Whether the site would show it right now. A draft is offered and drawn dimmed: a menu is
   * built before the pages in it are published.
   */
  available: boolean
  /** The line under the title: a path in the tree, a rubric, a date. */
  hint: string | null
}

/** The same, once the type it came from matters — what `resolve` answers with. */
export interface ResolvedLink extends LinkCandidate {
  type: string
}

export interface LinksApi {
  sources(): Promise<LinkSourceInfo[]>
  search(type: string, query: string, locale?: string): Promise<LinkCandidate[]>
  resolve(links: { type: string; id: number }[], locale?: string): Promise<ResolvedLink[]>
  /** Named addresses of the site itself, for the field where a path is typed. */
  routes(): Promise<{ name: string; path: string }[]>
}

/** An empty link — what a field holds before anybody has chosen anything. */
export function emptyLink(): LinkValue {
  return {
    target: 'entity',
    entity_type: null,
    entity_id: null,
    url: null,
    hash: null,
    new_tab: false,
    rel: [],
  }
}

/**
 * What the panel can be asked to link to.
 *
 * The panel's own addresses rather than a section's: the same picker opens in a menu, in a block
 * field and on a described screen, and a copy of it per module would be a copy of the permission
 * rules to get wrong.
 */
export function createLinksApi(admin: AdminContext): LinksApi {
  const base = `${admin.apiPath}/links`
  const data = <T>(body: { data: T }): T => body.data

  return {
    sources: () => admin.http.get<{ data: LinkSourceInfo[] }>(`${base}/sources`).then(data),
    search: (type, query, locale) =>
      admin.http
        .get<{ data: LinkCandidate[] }>(`${base}/search`, { query: { type, q: query, locale } })
        .then(data),
    resolve: (links, locale) =>
      admin.http.post<{ data: ResolvedLink[] }>(`${base}/resolve`, { links, locale }).then(data),
    routes: () =>
      admin.http.get<{ data: { name: string; path: string }[] }>(`${base}/routes`).then(data),
  }
}
