import { inject, provide, type InjectionKey, type Ref } from 'vue'
import type { AdminContext } from './admin'

/** What happened to a record. `run` is the parent row of an import or a bulk action. */
export type HistoryEvent =
  'created' | 'updated' | 'deleted' | 'restored' | 'published' | 'unpublished' | 'run'

/** Which door the change came through. */
export type HistorySource = 'panel' | 'mcp' | 'import' | 'bulk' | 'api' | 'console'

/**
 * One field of one save. A long value (rich text, a repeater) comes without `from` and `to`:
 * the journal keeps that it changed and how long it was, and the versions keep the text.
 */
export interface HistoryChange {
  /** `price`, or `name.ru` for one language of a translated field. */
  field: string
  /** The module's word for it, in the reader's language. */
  label: string
  from: unknown
  to: unknown
  long?: boolean
  from_length?: number
  to_length?: number
}

export interface HistoryEntry {
  id: number
  event: HistoryEvent
  source: HistorySource
  subject: { type: string; id: number | null }
  /** The name as it was signed; `null` when nobody was signed in (a console command). */
  admin: { id: number | null; name: string | null } | null
  /** The agent's connection, when the source is `mcp`. */
  grant_id: number | null
  changes: HistoryChange[]
  /** The run this save was made in, with what the run was when the server had it at hand. */
  run: { id: number; summary: Record<string, unknown> | null } | null
  created_at: string | null
  /** A run's own: what was done, with which profile, how many rows. */
  summary?: Record<string, unknown>
  rows?: number
}

/** A page as `->paginate()` has it. */
export interface HistoryPage {
  data: HistoryEntry[]
  current_page: number
  last_page: number
  total: number
}

export interface HistoryRun {
  run: HistoryEntry
  rows: HistoryPage
}

export interface HistoryApi {
  list(page?: number): Promise<HistoryPage>
  /** A run and its rows, or only the rows about one record. */
  run(id: number, page?: number, search?: number | null): Promise<HistoryRun>
}

/**
 * The journal of one record.
 *
 * `type` is what the module registered (`catalog.product`) — never a class name — and the
 * address is the panel's own, like the notes': one feed for every section, and the server says
 * which permission each type is behind.
 */
export function createHistoryApi(
  admin: AdminContext,
  type: string,
  id: number | string,
): HistoryApi {
  return {
    list: (page = 1) =>
      admin.http.get<HistoryPage>(`${admin.apiPath}/history/${type}/${id}`, { query: { page } }),
    run: (runId, page = 1, search = null) =>
      admin.http.get<HistoryRun>(`${admin.apiPath}/history/runs/${runId}`, {
        query: { page, search },
      }),
  }
}

/**
 * The id of the record an editor is showing, for a `wx-history` node on its screen.
 *
 * A described screen is the same JSON for every record, so the id cannot be written into it:
 * the page that hosts the screen knows it, and provides it here — the way it provides the
 * address to `wx-slug`. `null` is a record not saved yet, which has no history.
 */
export interface HistorySubject {
  id: Ref<number | string | null | undefined>
}

export const historySubjectKey: InjectionKey<HistorySubject> = Symbol('wx-history-subject')

export function provideHistorySubject(subject: HistorySubject): void {
  provide(historySubjectKey, subject)
}

/** The editor above the node, or `null` outside one. */
export function useHistorySubject(): HistorySubject | null {
  return inject(historySubjectKey, null)
}

/**
 * A value as a line of text. Words for "nothing" are the caller's; a list or an object is shown
 * as JSON, cut, because a repeater row printed whole is a paragraph in a feed of one-liners.
 */
export function historyValue(value: unknown, empty: string, max = 120): string {
  if (value === null || value === undefined || value === '') return empty
  if (typeof value === 'boolean') return value ? '✓' : '✗'
  if (typeof value === 'string') return cut(value, max)
  if (typeof value === 'number') return String(value)

  return cut(JSON.stringify(value), max)
}

function cut(text: string, max: number): string {
  return text.length > max ? `${text.slice(0, max - 1)}…` : text
}
