export type AuditSeverity = 'error' | 'warning' | 'notice'

export type AuditScope = 'full' | 'quick'

export type AuditRunStatus = 'queued' | 'running' | 'done' | 'failed' | 'cancelled'

export type AuditStage = 'probes' | 'database' | 'crawl' | 'analyse'

/** What a run counted, once it is done. */
export interface AuditCounts {
  severity: Record<AuditSeverity, number>
  groups: Record<string, Record<AuditSeverity, number>>
  /** Ids of the checks that ran. */
  checks: string[]
  /** The worst severity of every check that found something. */
  failed: Record<string, AuditSeverity>
  /** 0–100, each check weighed once by its severity. */
  health: number
  new: number
  fixed: number
  previous_id: number | null
  sources: { searched: string[]; missing: string[] }
}

export interface AuditRun {
  id: number
  status: AuditRunStatus
  scope: AuditScope
  base_url: string
  resolve_to: string | null
  progress: { stage: AuditStage | null; done: AuditStage[]; checks: number }
  counts: AuditCounts | null
  started_by: string | null
  created_at: string | null
  started_at: string | null
  finished_at: string | null
  error: string | null
}

/** The overview's one call: the run that is going, the last that finished, and the queue. */
export interface AuditLatest {
  active: AuditRun | null
  done: AuditRun | null
  /** A run that failed or was cancelled after the last good one. */
  last: AuditRun | null
  queue: { sync: boolean }
}

/** One check that found something, with its three texts in the reader's language. */
export interface AuditCheckRow {
  id: string
  group: string
  severity: AuditSeverity
  count: number
  new: number
  title: string
  found: string
  why: string
  fix: string
}

export type AuditCellType = 'url' | 'status' | 'bool' | 'text' | 'missing' | 'edit'

export interface AuditDetailsColumn {
  key: string
  label: string
  type: AuditCellType
}

/** The expansion of a finding: data, drawn by one component for every check. */
export interface AuditDetails {
  summary: string | null
  table: { columns: AuditDetailsColumn[]; rows: Record<string, unknown>[] } | null
}

export interface AuditIssue {
  id: number
  check: string
  severity: AuditSeverity
  url: string | null
  state: 'new' | 'persisting'
  ignored: boolean
  details: AuditDetails
}

export interface AuditIssueQuery {
  check?: string
  severity?: AuditSeverity | null
  group?: string | null
  state?: 'new' | 'persisting' | null
  page?: number
  per_page?: number
}

export interface AuditPage<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}
