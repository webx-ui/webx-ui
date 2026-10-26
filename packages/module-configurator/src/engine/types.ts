/**
 * The schema document of a configurator (§5): one JSON object, the same one the server stores,
 * prices and checks. The engine reads it defensively — a draft is saved with problems — so these
 * types say what a valid document holds, not what the engine survives.
 */

/** A text: a string in the site's default language, or one string per language (decision 6). */
export type Text = string | Record<string, string>

/** `{ step: [values] }` — "or" inside a list, "and" between steps (§5.6). */
export type Condition = Record<string, string[]>

export type StepType = 'cards' | 'swatches' | 'buttons' | 'dimensions'

export interface Price {
  fixed?: number | null
  perM2?: number | null
  percent?: number | null
}

export interface OptionDoc {
  value: string
  label?: Text
  subtitle?: Text
  /** A key of the media library — required for every option of a `cards` step. */
  image?: string | null
  /** `#rgb`, `#rrggbb` or a gradient of those (§5.5). */
  swatch?: string | null
  /** A key of the media library; wins over `swatch`. */
  swatchImage?: string | null
  price?: Price
  /** The area formula (§7.1): only the options of the main step have one. */
  area?: string
  /** Sizes put in when the option is chosen (§6.2). */
  defaults?: Record<string, number>
  /** `[min, max]` of size fields while the option is chosen. */
  limits?: Record<string, [number, number]>
  when?: Condition
  unless?: Condition
}

export interface FieldDoc {
  key: string
  label?: Text
  unit?: Text
  min: number
  max: number
  step?: number
  default?: number | null
  when?: Condition
  unless?: Condition
}

export interface StepDoc {
  key: string
  type: StepType
  label?: Text
  title?: Text
  hint?: Text
  when?: Condition
  unless?: Condition
  options?: OptionDoc[]
  fields?: FieldDoc[]
}

export interface Discount {
  /** Above 0 and below 100. */
  percent: number
  /** The last day it is given, `YYYY-MM-DD`; none — until it is taken off. */
  until?: string | null
  label?: Text
}

export interface PreviewRule {
  when: Condition
  image: string
}

export interface ConfiguratorSchema {
  version: 1
  /** ISO 4217, three capitals. */
  currency: string
  pricing?: { round?: number }
  /** The starting choice: `{ step: value, field: number }` (§6.3). */
  initial?: Record<string, string | number>
  discount?: Discount | null
  steps: StepDoc[]
  previews?: PreviewRule[]
}

/** A value of a step (an option's `value`) or of a size field (a number). */
export type ConfiguratorValue = string | number

export type ConfiguratorValues = Record<string, ConfiguratorValue>

/** A refused value: `values.h` is outside its limits, or not a number (§6.4). */
export interface ValueError {
  path: string
  code: 'values.range' | 'values.number'
}

/**
 * What a schema and a visitor's values resolve to (§6.1): the value of every visible step and
 * field in the order of the document, and what is hidden.
 */
export interface ConfiguratorState {
  values: ConfiguratorValues
  /** Keys of hidden steps and fields, in the order of the document. */
  hidden: string[]
  /** Visible choice step → its visible options. */
  options: Record<string, string[]>
  /** Visible field → `[min, max]` in force. */
  limits: Record<string, [number, number]>
  errors: ValueError[]
}

export type QuoteLineKind = 'fixed' | 'perM2' | 'percent' | 'discount'

/** One line of a breakdown (§7.4), in the order it was counted. */
export interface QuoteLine {
  step: string | null
  option: string | null
  kind: QuoteLineKind
  rate?: number
  area?: number
  amount: number
}

/** A price and how it was made (§7.4) — what both engines agree on, number for number. */
export interface Quote {
  currency: string
  normalized: ConfiguratorValues
  hidden: string[]
  area: number
  subtotal: number
  total_before_discount: number
  total: number
  round: number
  lines: QuoteLine[]
}

/** A visitor's action, as the parity cases and `configurators_quote` replay them. */
export type ConfiguratorAction = { select: [string, string] } | { set: [string, number] }
