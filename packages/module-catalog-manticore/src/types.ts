/** What a language's table is: of the schema the catalogue writes now, of an older one, or not there. */
export type TableState = 'ready' | 'stale' | 'missing'

/** Where the rebuild started from the panel stands. */
export type RebuildState = 'idle' | 'queued' | 'running' | 'done' | 'failed'

export interface IndexConnection {
  /** `host:port` of the server's HTTP API. */
  address: string
  /** Null when the site has none — the engine does not start without it. */
  prefix: string | null
  version: string | null
  available: boolean
  /** Why it does not answer, as the server or the client said it. */
  error: string | null
}

export interface IndexTable {
  locale: string
  table: string
  state: TableState
  /** Why the table is of another schema: a column missing, another morphology. */
  reason: string | null
  /** Products in the table; null when there is no table. */
  documents: number | null
  /** A rebuild is filling `{table}_next` beside it. */
  rebuilding: boolean
  /**
   * Products already in `{table}_next` — how far a rebuild has got, the console's too, which
   * writes no progress of its own. Null when nothing is beside.
   */
  filled?: number | null
}

export interface RebuildProgress {
  state: RebuildState
  done: number
  total: number
  queued_at: string | null
  started_at: string | null
  finished_at: string | null
  error: string | null
  /** Waiting or running, and not heard from for a quarter of an hour: the worker is gone. */
  stalled: boolean
}

/** `GET /search-index` — what «System → Search index» shows (decision 27 of the Manticore spec). */
export interface IndexReport {
  connection: IndexConnection
  /** Products in the database, the bin included: the bin is indexed too. */
  products: number
  tables: IndexTable[]
  queue: { waiting: number; oldest: string | null }
  rebuild: RebuildProgress
  /** Some table is missing or of an older schema. */
  outdated: boolean
}
