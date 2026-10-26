import type { LocalizedValue } from '@webx-ui/core'
import type { EditingChange, LinkValue, RelationCandidate } from '@webx-ui/module-admin'
import type { ConfiguratorSchema, Quote, QuoteLine } from './engine/types'

/** Never on the site · on it · on it with edits waiting · taken off it. */
export type ConfiguratorStatus = 'draft' | 'published' | 'modified' | 'unpublished'

/** One configurator as the list shows it (`ConfiguratorForm::row()` on the server). */
export interface ConfiguratorRow {
  id: number
  /** What a block names it by (decision 11). */
  key: string
  /** In the language the panel is open in, falling back to the default one. */
  title: string
  status: ConfiguratorStatus
  /** The slug of the inbox form a request goes to. */
  form: string | null
  steps: number
  currency: string | null
  /** Once published, the key no longer changes. */
  key_locked: boolean
  published_at: string | null
  updated_at: string | null
  deleted_at?: string | null
}

/** What the editor edits: the drafted fields and the key beside them. */
export interface ConfiguratorValues {
  key: string
  title: LocalizedValue
  schema: ConfiguratorSchema
  form: string | null
  /** Where its block stands (`wx-link`): a product of the catalogue sends a visitor there (§22). */
  page?: LinkValue | null
  [field: string]: unknown
}

/** One problem of the schema, in the words of the request (§9). */
export interface SchemaProblem {
  path: string
  code: string
  message: string
}

/** The editor's record (§13.3). */
export interface ConfiguratorDetail {
  configurator: ConfiguratorRow
  values: ConfiguratorValues
  revision: string
  /** What a publication would refuse now. */
  errors: SchemaProblem[]
  /** Without the inbox a configurator sends no requests, and the form field is not drawn. */
  has_inbox: boolean
  /** With the catalogue an option may name a product of it, and the settings have the page. */
  has_catalog?: boolean
}

/** A save refused because somebody wrote in between: the record as it is now, and who. */
export interface ConfiguratorConflict {
  data: ConfiguratorDetail
  changed?: EditingChange | null
}

export interface ConfiguratorVersion {
  number: number
  created_at: string | null
  author: string | null
  source: string
  comment: string | null
  on_site: boolean
}

/** The server's price: the engine's numbers with each line in words. */
export interface ServerQuote extends Quote {
  lines: (QuoteLine & { line: string })[]
  selection: { step: string; label: string; value: string; option: string }[]
}

export interface ConfiguratorQuery {
  search?: string
  trashed?: boolean
}

/** A configurator as the relation target `configurator` describes it, with its key by name. */
export interface ConfiguratorCandidate extends RelationCandidate {
  key?: string
  /** A product of the catalogue (the target `configurator-product`): what an option keeps. */
  sku?: string
  /** What a block may start it from (`wx-configurator-preset`): the published steps and sizes. */
  choices?: ConfiguratorChoice[]
}

/** A step of options a preset picks a value of, in the language of the panel. */
export interface ConfiguratorStepChoice {
  key: string
  kind: 'choice'
  label: string
  /** The value the schema starts it with, or null for its first option. */
  initial: string | null
  options: { value: string; label: string }[]
}

/** A size a preset sets, inside the field's own limits. */
export interface ConfiguratorSizeChoice {
  key: string
  kind: 'size'
  label: string
  unit: string
  min: number
  max: number
  step: number
  initial: number | null
}

export type ConfiguratorChoice = ConfiguratorStepChoice | ConfiguratorSizeChoice

/** A block's start over the schema's `initial`: `{ step: value, field: number }`. */
export type ConfiguratorPreset = Record<string, string | number>
