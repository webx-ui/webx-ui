import type { LocationQuery } from 'vue-router'
import type { FacetChoice, FacetInfo } from './types'

/**
 * The list's facet filters live in the address, one parameter per facet — `f.<key>` — so that
 * coming back from a product lands on the same filter, and a filtered list can be linked to
 * (the tree's "Show its products" does exactly that):
 *
 *     f.category=12,14   values of a terms or tree facet
 *     f.price=100-500    a range; either end may be empty
 *     f.in-stock=1       a toggle
 *
 * The server's own format is another (`facets[key][]=…`, see `productSearch()`); this one is for
 * a person reading the address bar.
 */
export const FACET_PREFIX = 'f.'

export function readFacets(query: LocationQuery, facets: FacetInfo[]): Record<string, FacetChoice> {
  const chosen: Record<string, FacetChoice> = {}

  for (const facet of facets) {
    const raw = query[`${FACET_PREFIX}${facet.key}`]
    const text = typeof raw === 'string' ? raw.trim() : ''

    if (text === '') continue

    if (facet.kind === 'toggle') {
      if (text === '1') chosen[facet.key] = true
    } else if (facet.kind === 'range') {
      const [min, max] = text.split('-', 2).map((end) => (end === '' ? null : Number(end)))
      const range = {
        min: Number.isFinite(min) ? min : null,
        max: Number.isFinite(max) ? max : null,
      }

      if (range.min !== null || range.max !== null) chosen[facet.key] = range
    } else {
      const values = text.split(',').filter((value) => value !== '')

      if (values.length > 0) chosen[facet.key] = values
    }
  }

  return chosen
}

/** One facet's choice as its parameter; `undefined` takes the parameter away. */
export function writeFacet(choice: FacetChoice | null | undefined): string | undefined {
  if (choice === null || choice === undefined) return undefined
  if (choice === true) return '1'

  if (Array.isArray(choice)) return choice.length > 0 ? choice.join(',') : undefined

  const min = choice.min ?? null
  const max = choice.max ?? null

  return min === null && max === null ? undefined : `${min ?? ''}-${max ?? ''}`
}
