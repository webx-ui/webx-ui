import type { AdminModule } from '@webx-ui/module-admin'
import {
  dictionaryOptions,
  dictionarySection,
  type DictionaryOptions,
} from '@webx-ui/module-catalog'
import { catalogStockMessages } from './messages'

export interface CatalogStockOptions {
  /** Where the catalogue lives inside the panel — the same as `catalog({ path })`. */
  path?: string
}

const STOCK: DictionaryOptions = {
  id: 'catalog-stock',
  slug: 'stock',
  screen: 'catalog.stock-status-form',
  facet: 'stock',
  namespace: 'webx-catalog-stock',
  group: 'status',
  messages: catalogStockMessages,
}

/** The stock statuses as the panel's shared category screens see them. */
export function catalogStockOptions(options: CatalogStockOptions = {}) {
  return dictionaryOptions({ ...STOCK, catalog: options.path })
}

/**
 * Stock statuses as a section of the panel (§2.2 of the dictionaries spec): in stock, out of
 * stock, on order — the list ordered by hand, which is the order of the filter, and the page of
 * one, which is the server's `catalog.stock-status-form`. Under «Dictionaries» of the «Catalog»
 * group, after the labels.
 *
 * The status of a product (`wx-select` over `catalog/stock`), its tag in the list, the filter and
 * «Set the stock status» all come through the catalogue's registries.
 */
export function catalogStock(options: CatalogStockOptions = {}): AdminModule[] {
  return [dictionarySection({ ...STOCK, catalog: options.path })]
}
