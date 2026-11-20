import type { AdminContext } from './admin'
import type { LinkSourceInfo, LinksApi } from './links'

/*
 * One question per panel, not per field: a block form with eight buttons draws eight link rows,
 * and each needs only the names of the sections. Keyed by the context, so a test that builds a
 * fresh one — or a panel that signs in again — asks again. A failed answer is not kept.
 */
const asked = new WeakMap<AdminContext, Promise<LinkSourceInfo[]>>()

/** The sections a link may point into, asked of the server once for this panel. */
export function linkSources(admin: AdminContext, api: LinksApi): Promise<LinkSourceInfo[]> {
  const known = asked.get(admin)

  if (known !== undefined) return known

  const pending = api.sources().catch((error: unknown) => {
    asked.delete(admin)

    throw error
  })

  asked.set(admin, pending)

  return pending
}
