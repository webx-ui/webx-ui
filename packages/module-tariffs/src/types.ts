import type { ScreenModel } from '@webx-ui/schema'

/** A group as a row of the list names one: the filter, and the chips on the row. */
export interface TariffGroupRef {
  id: number
  title: string
}

/**
 * One tariff as the section lists it (§5.4).
 *
 * The words are in the language the panel is open in, in the default one where there is none —
 * the same fallback the site uses for them (decision 12), so the row shows the price the page
 * would print.
 */
export interface TariffRow {
  id: number
  /** The name, or `#id` when there is none in either language. */
  name: string
  /** The short plate over the name (“30 HOURS / 25$”); `''` or `null` when there is none. */
  badge: string | null
  /** A number, or `null` — then `price_text` is what the site prints. */
  price: number | null
  /** A key of `webx-tariffs.currencies`, or `null` when none was chosen. */
  currency: string | null
  /** The symbol the config gives the currency — the code itself for one the config dropped. */
  symbol: string | null
  /** “/mo”, “a year”; `''` or `null` when there is none. */
  period: string | null
  /** The words printed instead of a number (“On request”); `''` or `null` when there are none. */
  price_text: string | null
  /** Picked out among the others on the site. */
  featured: boolean
  published: boolean
  /** Its place in the whole list. */
  position: number
  categories: TariffGroupRef[]
  updated_at: string | null
  deleted_at: string | null
}

/** Every tariff — no pages: the list is where tariffs are put in order. */
export interface TariffsList {
  data: TariffRow[]
  filters: { categories: TariffGroupRef[] }
}

export interface TariffQuery {
  /** The name, the plate or the description, in any language the site has. */
  search?: string
  /** One group: the list comes in its own order, and a drag writes that order. */
  category?: number | null
  /** What was deleted. The only way back to a tariff in the bin is through this list. */
  trashed?: boolean
}

/** The record half of the form's answer — what the head of the form needs. */
export interface TariffSummary {
  id: number
  name: string
  published: boolean
  deleted_at: string | null
}

/** One tariff as its form opens it: the record, and the values of `tariffs.form` by field name. */
export interface TariffDetail {
  tariff: TariffSummary
  values: ScreenModel
}
