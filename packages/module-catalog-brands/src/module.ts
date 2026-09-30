import type { AdminModule } from '@webx-ui/module-admin'
import {
  dictionaryOptions,
  dictionarySection,
  type DictionaryOptions,
} from '@webx-ui/module-catalog'
import { catalogBrandsMessages } from './messages'

export interface CatalogBrandsOptions {
  /** Where the catalogue lives inside the panel — the same as `catalog({ path })`. */
  path?: string
}

const BRANDS: DictionaryOptions = {
  id: 'catalog-brands',
  slug: 'brands',
  screen: 'catalog.brand-form',
  facet: 'brand',
  namespace: 'webx-catalog-brands',
  group: 'brand',
  messages: catalogBrandsMessages,
}

/** The brands as the panel's shared category screens see them. */
export function catalogBrandsOptions(options: CatalogBrandsOptions = {}) {
  return dictionaryOptions({ ...BRANDS, catalog: options.path })
}

/**
 * Brands as a section of the panel (§2.3 of the dictionaries spec): the list in the order of the
 * site — the filter, the popular brands, the page of all of them — each with its address, and the
 * page of one, which is the server's `catalog.brand-form`: «Main» with the logo out of the media
 * library, «Description», «SEO» and «History». In the «Catalog» group after «Products».
 *
 * The brand of a product (`wx-select` over `catalog/brands`), its name in the list, the filter and
 * «Set the brand» come through the catalogue's registries. The logo field is `wx-media` — the
 * media module's, which a panel with brands has.
 */
export function catalogBrands(options: CatalogBrandsOptions = {}): AdminModule[] {
  return [dictionarySection({ ...BRANDS, catalog: options.path })]
}
