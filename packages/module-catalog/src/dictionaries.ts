import { defineComponent, h, type Component } from 'vue'
import type { RouteRecordRaw } from 'vue-router'
import {
  categoryRoutes,
  useAdmin,
  type AdminModule,
  type CategoriesOptions,
  type CategoryWord,
  type Messages,
} from '@webx-ui/module-admin'
import { FACET_PREFIX } from './filters'

/**
 * A reference book of the catalogue — labels, stock statuses, brands — as the satellite that owns
 * it describes it (§4 of the dictionaries spec). Everything else is the same for each.
 */
export interface DictionaryOptions {
  /** The module the server registers: `catalog-labels`. */
  id: string
  /** Its path under the catalogue's, and under the catalogue's API: `labels`. */
  slug: string
  /** The described screen of one record: `catalog.label-form`. */
  screen: string
  /** The facet of the products list that picks a record's products: `label`. */
  facet: string
  /** The satellite's dictionary: `webx-catalog-labels`. */
  namespace: string
  /** The group of it the list's words are in: `label`. */
  group: string
  /** Its English, under the server's until the server's arrives. */
  messages: Record<string, Messages>
  /** Where the catalogue lives in the panel — `catalog({ path })`'s. */
  catalog?: string
}

/** The words of the shared screens a reference book says in its own way; the rest are the panel's. */
const OWN: CategoryWord[] = [
  'new',
  'empty',
  'empty-help',
  'order',
  'hidden',
  'count',
  'show-items',
  'delete-blocked',
  'delete-text',
  'deleted',
  'saved',
]

/**
 * The panel's shared category screens for one reference book: the list with its order dragged
 * and the number of products on each row, and the page of one record, which is the satellite's
 * described screen. Rights are the catalogue's (decision 8), and "Show its products" is the list
 * of products narrowed by the satellite's own facet.
 */
export function dictionaryOptions(options: DictionaryOptions): CategoriesOptions {
  const catalog = options.catalog ?? '/catalog'

  return {
    api: `catalog/${options.slug}`,
    path: `${catalog}/${options.slug}`,
    name: `webx.${options.id}`,
    module: options.id,
    screen: options.screen,
    manage: 'catalog.manage',
    count: 'products_count',
    items: (id) => ({
      path: `${catalog}/products`,
      query: { [`${FACET_PREFIX}${options.facet}`]: String(id) },
    }),
    words: Object.fromEntries(
      OWN.map((word) => [word, `${options.namespace}::${options.group}.${word}`]),
    ),
  }
}

/** A reference book as a section of the panel: one entry, two routes. */
export function dictionarySection(options: DictionaryOptions): AdminModule {
  const described = dictionaryOptions(options)
  const seed = () => seedMessages(options.namespace, options.messages)

  return {
    id: options.id,
    path: described.path,
    routes: categoryRoutes(described).map((route) => seeded(route, seed)),
  }
}

const seededIn = new WeakMap<object, Set<string>>()

/**
 * The satellite's English under the panel's dictionary, once per panel. The screens are the
 * panel's, so nothing of the satellite's runs before them to do it — the route does.
 */
function seedMessages(namespace: string, messages: Record<string, Messages>): void {
  try {
    const { i18n } = useAdmin()
    const done = seededIn.get(i18n) ?? new Set<string>()

    if (!done.has(namespace)) {
      i18n.defaults(namespace, messages)
      done.add(namespace)
      seededIn.set(i18n, done)
    }
  } catch {
    // Outside a panel there is no dictionary to seed, and `useTranslate` falls back on its own.
  }
}

function seeded(route: RouteRecordRaw, seed: () => void): RouteRecordRaw {
  const page = (route as { component?: Component }).component

  if (page === undefined) return route

  return {
    ...route,
    component: defineComponent({
      name: 'WxCatalogDictionaryView',
      setup() {
        seed()

        return () => h(page)
      },
    }),
  } as RouteRecordRaw
}
