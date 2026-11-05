/** What the list says of a feed's last build (§7.6 of the feeds spec). */
export interface FeedBuildState {
  status: 'queued' | 'running' | 'done' | 'failed'
  written: number
  finished_at: string | null
}

export interface FeedRow {
  id: number
  name: string
  preset: string | null
  format: 'xml' | 'json' | 'csv' | 'text'
  driver: string | null
  locale: string
  currency: string
  schedule: string
  is_active: boolean
  /** With its key: `null` without `catalog.manage`. */
  address: string | null
  last_build: FeedBuildState | null
  updated_at: string | null
}

export interface FeedsPage {
  data: FeedRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}
