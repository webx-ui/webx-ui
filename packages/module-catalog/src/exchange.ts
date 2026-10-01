import { onBeforeUnmount, ref, type Ref } from 'vue'
import type { AdminContext } from '@webx-ui/module-admin'
import type { Paginated } from '@webx-ui/core'
import { CATALOG_API } from './api'
import type { BulkSelection } from './types'

/**
 * The exchange of the catalogue as the panel sees it (§8 of the exchange spec): the columns a
 * file may carry, a look at a file before it goes in, the runs with their errors and files, and
 * the saved profiles. The server's shapes as they are — `ExchangeController`,
 * `ExchangeProfileController`, `ExchangeRun::toResponse()`.
 */

/** What a chunked upload is claimed for — the exchange's own, beside `catalog.video`. */
export const EXCHANGE_PURPOSE = 'catalog.exchange'

export type ExchangeDirection = 'import' | 'export'
export type ExchangeFormat = 'csv' | 'xlsx'
export type ExchangeStatus = 'queued' | 'running' | 'done' | 'stopped' | 'failed'

/** A column the caller may map or export; `locales` are the other languages of a translated one. */
export interface ExchangeColumnInfo {
  key: string
  label: string
  field: string
  localized: boolean
  locales: string[]
}

/** How an import writes (§1). Encoding and separator only when somebody knows better than a guess. */
export interface ImportOptions {
  key: 'sku' | 'external_id' | 'barcode' | 'id'
  mode: 'upsert' | 'update' | 'create'
  empty_clears: boolean
  absent: 'keep' | 'unpublish'
  absent_scope: 'categories' | 'all'
  create_missing: boolean
  images: 'append' | 'replace'
  encoding?: string
  delimiter?: string
  format?: ExchangeFormat
}

export const IMPORT_DEFAULTS: ImportOptions = {
  key: 'sku',
  mode: 'upsert',
  empty_clears: false,
  absent: 'keep',
  absent_scope: 'categories',
  create_missing: false,
  images: 'append',
}

/** A file as the server read it: how, its header, five rows, and what each header seems to be. */
export interface ExchangeInspection {
  format: ExchangeFormat
  options: { encoding?: string; delimiter?: string }
  header: string[]
  sample: string[][]
  mapping: Record<string, string | null>
}

export type ExchangeSource = { upload_id: string } | { url: string }

export interface ExchangeRun {
  id: number
  profile_id: number | null
  direction: ExchangeDirection
  format: ExchangeFormat
  options: Partial<ImportOptions> & Record<string, unknown>
  /** Import: header → column code. Export: the codes in their order. */
  mapping: Record<string, string> | string[]
  dry_run: boolean
  status: ExchangeStatus
  source: string | null
  rows_total: number
  rows_done: number
  created: number
  updated: number
  skipped: number
  failed: number
  absent: number
  history_id: number | null
  admin_id: number | null
  admin_name: string | null
  /** The finished export by a signed address, while the file is kept; null otherwise. */
  file_url: string | null
  started_at: string | null
  finished_at: string | null
  created_at: string | null
}

export interface ExchangeRunError {
  row: number
  /** Null — the row as a whole. */
  column: string | null
  value: string | null
  message: string
}

export interface ExchangeProfile {
  id: number
  name: string
  direction: ExchangeDirection
  format: ExchangeFormat
  options: Partial<ImportOptions> & Record<string, unknown>
  mapping: Record<string, string> | string[]
  last_run_id: number | null
  created_at: string | null
  updated_at: string | null
}

export type ExchangeProfileInput = Partial<
  Pick<ExchangeProfile, 'name' | 'direction' | 'format' | 'options' | 'mapping'>
>

export interface ExchangeApi {
  columns(): Promise<ExchangeColumnInfo[]>
  inspect(
    source: ExchangeSource,
    read?: { format?: string; encoding?: string; delimiter?: string },
  ): Promise<ExchangeInspection>
  /** A small file is done inside the request; a large one comes back queued, to be polled. */
  startImport(
    source: ExchangeSource,
    plan: { profile_id?: number; mapping?: Record<string, string | null>; options?: object },
    dryRun: boolean,
  ): Promise<ExchangeRun>
  startExport(
    selection: BulkSelection & { trashed?: boolean },
    plan: { profile_id: number } | { columns: string[]; format: ExchangeFormat },
  ): Promise<ExchangeRun>
  runs(query?: { page?: number; per_page?: number }): Promise<Paginated<ExchangeRun>>
  run(id: number): Promise<ExchangeRun>
  errors(id: number, page?: number): Promise<Paginated<ExchangeRunError>>
  /** Where the browser downloads every error of a run as CSV, under the panel's session. */
  errorsFile(id: number): string
  /** Where the browser downloads a finished export under the panel's session. */
  file(id: number): string
  profiles(direction?: ExchangeDirection): Promise<ExchangeProfile[]>
  profile(id: number): Promise<ExchangeProfile>
  createProfile(values: ExchangeProfileInput): Promise<ExchangeProfile>
  saveProfile(id: number, values: ExchangeProfileInput): Promise<ExchangeProfile>
  removeProfile(id: number): Promise<void>
}

export function createExchangeApi(admin: AdminContext): ExchangeApi {
  const base = `${admin.apiPath}/${CATALOG_API}/exchange`
  const data = <T>(body: { data: T }) => body.data

  return {
    columns: () => admin.http.get<{ data: ExchangeColumnInfo[] }>(`${base}/columns`).then(data),
    inspect: (source, read = {}) =>
      admin.http
        .post<{ data: ExchangeInspection }>(`${base}/inspect`, { ...source, ...read })
        .then(data),
    startImport: (source, plan, dryRun) =>
      admin.http
        .post<{ data: ExchangeRun }>(`${base}/import`, { ...source, ...plan, dry_run: dryRun })
        .then(data),
    startExport: (selection, plan) =>
      admin.http.post<{ data: ExchangeRun }>(`${base}/export`, { selection, ...plan }).then(data),
    runs: (query = {}) =>
      admin.http.get<Paginated<ExchangeRun>>(`${base}/runs`, {
        query: { page: query.page ?? 1, per_page: query.per_page ?? 20 },
      }),
    run: (id) => admin.http.get<{ data: ExchangeRun }>(`${base}/runs/${id}`).then(data),
    errors: (id, page = 1) =>
      admin.http.get<Paginated<ExchangeRunError>>(`${base}/runs/${id}/errors`, {
        query: { page, per_page: 100 },
      }),
    errorsFile: (id) => `${base}/runs/${id}/errors?format=csv`,
    file: (id) => `${base}/runs/${id}/file`,
    profiles: (direction) =>
      admin.http
        .get<{ data: ExchangeProfile[] }>(`${base}/profiles`, {
          query: direction ? { direction } : {},
        })
        .then(data),
    profile: (id) => admin.http.get<{ data: ExchangeProfile }>(`${base}/profiles/${id}`).then(data),
    createProfile: (values) =>
      admin.http.post<{ data: ExchangeProfile }>(`${base}/profiles`, values).then(data),
    saveProfile: (id, values) =>
      admin.http.put<{ data: ExchangeProfile }>(`${base}/profiles/${id}`, values).then(data),
    removeProfile: (id) => admin.http.delete<void>(`${base}/profiles/${id}`).then(() => undefined),
  }
}

export function isRunning(run: Pick<ExchangeRun, 'status'>): boolean {
  return run.status === 'queued' || run.status === 'running'
}

/** As the server spells one column of an export: `name`, or `name@de` for a translation. */
export function columnCode(key: string, locale?: string | null): string {
  return locale ? `${key}@${locale}` : key
}

/** As often as the bulk actions ask (§8.1): a run of eighty thousand rows is not a request storm. */
export const EXCHANGE_POLL = 2000

/**
 * Runs that are still going, asked after every two seconds until they end. `watch` puts a run
 * under watch; `onUpdate` hears every fresh answer, the last one included. A missed answer is
 * not the end of a run — it is asked again on the next turn.
 */
export function useRunPolling(
  api: Pick<ExchangeApi, 'run'>,
  onUpdate: (run: ExchangeRun) => void,
): { watch: (id: number) => void; watching: Ref<number[]> } {
  const watching = ref<number[]>([])
  let timer: ReturnType<typeof setTimeout> | undefined

  async function tick(): Promise<void> {
    timer = undefined

    const answers = await Promise.allSettled(watching.value.map((id) => api.run(id)))

    for (const answer of answers) {
      if (answer.status !== 'fulfilled') continue

      onUpdate(answer.value)

      if (!isRunning(answer.value)) {
        watching.value = watching.value.filter((id) => id !== answer.value.id)
      }
    }

    schedule()
  }

  function schedule(): void {
    if (timer === undefined && watching.value.length > 0) timer = setTimeout(tick, EXCHANGE_POLL)
  }

  onBeforeUnmount(() => {
    clearTimeout(timer)
    watching.value = []
  })

  return {
    watching,
    watch(id) {
      if (!watching.value.includes(id)) watching.value = [...watching.value, id]
      schedule()
    },
  }
}
