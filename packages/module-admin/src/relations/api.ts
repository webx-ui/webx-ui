import { inject, provide, type InjectionKey, type Ref } from 'vue'
import type { AdminContext } from '../admin'

/**
 * One record a relation can point at, as `GET /api/cms/relations/{target}` draws it (§3.7 of the
 * recipes spec) — already in the panel's language.
 */
export interface RelationCandidate {
  id: number
  title: string
  /** A second line: a category, an address. */
  subtitle: string | null
  /** A small picture's address, when the target has one. */
  thumb: string | null
  /** Whether the site shows it. A hidden one stays chosen and says so: the site skips it. */
  visible: boolean
  /** In the bin — a kind of hidden that says where to look. Only names of the chosen carry it. */
  trashed?: boolean
}

export interface RelationsApi {
  /** Candidates for the "Add" box, searched by the server. */
  search(query: string): Promise<RelationCandidate[]>
  /** The chosen ones by id: a form opens with numbers, and a row needs a name. */
  named(ids: number[]): Promise<RelationCandidate[]>
}

/**
 * The target's key is a path segment, and the ids go as `ids[]` — the panel's `query` option
 * holds one value per key, so the list is written into the path by hand.
 */
export function createRelationsApi(admin: AdminContext, target: string): RelationsApi {
  const path = `${admin.apiPath}/relations/${encodeURIComponent(target)}`

  return {
    search: (query) =>
      admin.http
        .get<{ data: RelationCandidate[] }>(path, { query: { q: query } })
        .then((body) => body.data),
    named: (ids) =>
      ids.length === 0
        ? Promise.resolve([])
        : admin.http
            .get<{
              data: RelationCandidate[]
            }>(`${path}?${ids.map((id) => `ids[]=${id}`).join('&')}`)
            .then((body) => body.data),
  }
}

/** A list of ids, in order, without repeats or anything that is not one — what the server keeps. */
export function normaliseRelations(value: unknown): number[] {
  if (!Array.isArray(value)) return []

  return [...new Set(value.map(Number).filter((id) => Number.isInteger(id) && id > 0))]
}

/**
 * The record whose form the field is on — so that "similar recipes" does not offer the recipe
 * itself. A described screen has no way to say which record it is editing, and the editor that
 * hosts it does, so the editor provides it the way it provides the address (`address.ts`).
 */
export interface RelationOwner {
  /** Its key in the registry of targets: `recipe`. */
  type: string
  /** `null` for a record not saved yet, which nothing can have chosen. */
  id: Ref<number | null>
}

export const relationOwnerKey: InjectionKey<RelationOwner> = Symbol('wx-relation-owner')

export function provideRelationOwner(owner: RelationOwner): void {
  provide(relationOwnerKey, owner)
}

export function useRelationOwner(): RelationOwner | null {
  return inject(relationOwnerKey, null)
}
