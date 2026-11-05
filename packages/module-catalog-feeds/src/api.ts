import type { AdminContext } from '@webx-ui/module-admin'
import type { FeedsPage } from './types'

/** Where the feeds answer under the panel's API (§11 of the feeds spec). */
export const FEEDS_API = 'catalog/feeds'

export interface CatalogFeedsApi {
  list(): Promise<FeedsPage>
}

export function createFeedsApi(admin: AdminContext): CatalogFeedsApi {
  return {
    list: () => admin.http.get<FeedsPage>(`${admin.apiPath}/${FEEDS_API}`),
  }
}
