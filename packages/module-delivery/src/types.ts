import type { ScreenModel } from '@webx-ui/schema'

/** A page of a list, as Laravel's `->paginate()` answers. */
export interface Page<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export interface Option {
  value: string
  label: string
}

/** The short line of a locality: a picker's suggestion, a popular town, a candidate. */
export interface LocalityBrief {
  id: number
  name: string
  /** «Town A, District C, Region B» — what tells namesakes apart. */
  label: string
  kind: string
  /** «Town», «Village»: the type in the panel's language. */
  kind_label: string
  country: string
  active: boolean
}

export interface MethodType {
  key: string
  title: string
  /** One line of what the type is, under its title in «What kind of method?». */
  description: string
  carrier: string
  carrier_title: string
  asks: 'nothing' | 'address' | 'point'
}

export interface MethodRow {
  id: number
  key: string
  type: string
  type_title: string
  carrier: string | null
  carrier_title: string | null
  /** The type is not installed any more: the method stays and is never offered. */
  missing: boolean
  title: string
  price: number | null
  price_text: string | null
  countries: string[]
  enabled: boolean
  position: number
}

export interface MethodDetail {
  method: MethodRow
  values: ScreenModel
  /** Node ids of the form this type has no use for. */
  hidden: string[]
  point_kinds: Option[]
}

export interface MethodBlank {
  hidden: string[]
  point_kinds: Option[]
  type_title: string
}

export interface PointRow {
  id: number
  kind: string
  kind_label: string
  title: string
  address: string
  locality: LocalityBrief | null
  phone: string | null
  active: boolean
  today: string | null
}

export interface PointDetail {
  point: PointRow
  values: ScreenModel
}

export interface PointsPage extends Page<PointRow> {
  filters: { kinds: Option[] }
}

export interface SyncRun {
  id: number
  directory: string
  status: 'running' | 'succeeded' | 'failed' | 'held'
  trigger: string
  pages: number
  seen: number
  created: number
  updated: number
  removed: number
  queued: number
  held: number | null
  error: string | null
  started_at: string | null
  finished_at: string | null
}

export interface CarrierDirectory {
  directory: 'localities' | 'points' | 'streets'
  cron: string | null
  last_success: string | null
  latest: SyncRun | null
  running: boolean
  held: number | null
  stale: boolean
  records: number
  remarks: { key: string; count: number }[]
}

export interface CarrierRow {
  key: string
  title: string
  icon: string | null
  enabled: boolean
  countries: string[]
  syncs: boolean
  directories: CarrierDirectory[]
  queue: number
}

/** A field a carrier asks for (§13.2): the name it is stored by and how to draw it. */
export interface CarrierField {
  name: string
  type?: string
  label?: string | Record<string, string>
  options?: Option[]
  [key: string]: unknown
}

export interface CarrierDetail extends CarrierRow {
  settings: { fields: CarrierField[]; values: Record<string, unknown> }
  /** The keys: whether each is set, never what it is. */
  credentials: (CarrierField & { set: boolean })[]
  runs: SyncRun[]
}

export interface CountryRow {
  code: string
  name: string
  active: boolean
  position: number
  regions: number
  localities: number
  leader: string | null
  /** The config names the leader: the panel cannot change it. */
  leader_fixed: boolean
}

export interface Countries {
  countries: CountryRow[]
  choices: { code: string; name: string }[]
  /** The carriers that may lead a country, by their titles. */
  carriers: Option[]
}

export interface RegionRow {
  id: number
  name: string
  names: Record<string, string>
  code: string | null
  country: string
  /** Where it stands in the country's own order; alphabetical breaks a tie. */
  position: number
  localities: number
}

export interface LocalityRow extends LocalityBrief {
  region: { id: number; name: string } | null
  district: string | null
  weight: number
  created_by: string
  source: 'manual' | 'import' | 'carrier'
  bindings: { carrier: string; carrier_title: string; external_id: string; status: string }[]
}

export interface LocalityDetail {
  locality: LocalityBrief & {
    source: LocalityRow['source']
    created_by: string
    manual_fields: string[]
    held_by: string[]
  }
  values: ScreenModel
}

/** The answer to a locality like one that exists (409): the ones to pick instead. */
export interface DuplicateAnswer {
  message: string
  similar: (LocalityBrief & { source: LocalityRow['source'] })[]
}

export interface BindingRow {
  id: number
  carrier: string
  carrier_title: string
  external_id: string
  reason: string | null
  record: {
    name: string
    region: string | null
    district: string | null
    kind: string | null
    country: string | null
    lat: number | null
    lng: number | null
  }
  candidates: (LocalityBrief & { distance_km: number | null })[]
}

export interface BindingsPage extends Page<BindingRow> {
  filters: {
    carriers: { value: string; label: string; count: number }[]
    reasons: { value: string; count: number }[]
  }
}

export interface BatchResult {
  done: number
  failed: { id: number; message: string }[]
  left: number
}
