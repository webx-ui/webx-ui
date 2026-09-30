import type { AdminModule } from '@webx-ui/module-admin'
import {
  dictionaryOptions,
  dictionarySection,
  type DictionaryOptions,
} from '@webx-ui/module-catalog'
import { catalogLabelsMessages } from './messages'

export interface CatalogLabelsOptions {
  /** Where the catalogue lives inside the panel — the same as `catalog({ path })`. */
  path?: string
}

const LABELS: DictionaryOptions = {
  id: 'catalog-labels',
  slug: 'labels',
  screen: 'catalog.label-form',
  facet: 'label',
  namespace: 'webx-catalog-labels',
  group: 'label',
  messages: catalogLabelsMessages,
}

/** The labels as the panel's shared category screens see them, for a panel mounting them itself. */
export function catalogLabelsOptions(options: CatalogLabelsOptions = {}) {
  return dictionaryOptions({ ...LABELS, catalog: options.path })
}

/**
 * Labels as a section of the panel (§2.1 of the dictionaries spec): the list of them, ordered by
 * hand — the order of the badges and of the filter — and the page of one, which is the server's
 * `catalog.label-form`. The server puts the entry in the «Catalog» group under «Dictionaries».
 *
 * Everything else the labels do in the panel arrives through the catalogue's registries and needs
 * nothing here: the field of the product form (`wx-categories`), the column of badges and the
 * filter of the list, the two bulk actions.
 */
export function catalogLabels(options: CatalogLabelsOptions = {}): AdminModule[] {
  return [dictionarySection({ ...LABELS, catalog: options.path })]
}
