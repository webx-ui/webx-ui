import type { AdminContext } from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'
import type {
  BatchResult,
  BindingsPage,
  CarrierDetail,
  CarrierRow,
  Countries,
  LocalityBrief,
  LocalityDetail,
  LocalityRow,
  MethodBlank,
  MethodDetail,
  MethodRow,
  MethodType,
  Option,
  Page,
  PointDetail,
  PointsPage,
  RegionRow,
} from './types'

/** Only what was asked for: an empty filter is no filter. */
function query(params: Record<string, string | number | boolean | null | undefined>): string {
  const search = new URLSearchParams()

  for (const [key, value] of Object.entries(params)) {
    if (value === null || value === undefined || value === '' || value === false) continue
    search.set(key, String(value === true ? 1 : value))
  }

  return search.size > 0 ? `?${search}` : ''
}

/** Everything under `/delivery`, below the panel's API path (spec §14). */
export function createDeliveryApi(admin: AdminContext) {
  const base = `${admin.apiPath}/delivery`
  const http = admin.http
  const data = <T>(body: { data: T }): T => body.data

  return {
    methods: () =>
      http
        .get<{ data: { methods: MethodRow[]; types: MethodType[] } }>(`${base}/methods`)
        .then(data),
    method: (id: number) => http.get<{ data: MethodDetail }>(`${base}/methods/${id}`).then(data),
    blank: (type: string) =>
      http.get<{ data: MethodBlank }>(`${base}/methods/blank${query({ type })}`).then(data),
    createMethod: (type: string, values: ScreenModel) =>
      http.post<{ data: MethodDetail }>(`${base}/methods`, { type, values }).then(data),
    saveMethod: (id: number, values: ScreenModel) =>
      http.put<{ data: MethodDetail }>(`${base}/methods/${id}`, { values }).then(data),
    removeMethod: (id: number) => http.delete<void>(`${base}/methods/${id}`).then(() => undefined),
    orderMethods: (ids: number[]) =>
      http.put<void>(`${base}/methods/order`, { ids }).then(() => undefined),
    defineKind: (kind: string, names: Record<string, string>) =>
      http.post<{ data: Option[] }>(`${base}/methods/point-kinds`, { kind, names }).then(data),

    points: (params: {
      page?: number
      per_page?: number
      q?: string
      kind?: string
      state?: string
      /** `title`, `kind`, `locality` or `active`; a minus — the other way round. */
      sort?: string
    }) => http.get<PointsPage>(`${base}/points${query(params)}`),
    point: (id: number) => http.get<{ data: PointDetail }>(`${base}/points/${id}`).then(data),
    createPoint: (values: ScreenModel) =>
      http.post<{ data: PointDetail }>(`${base}/points`, { values }).then(data),
    savePoint: (id: number, values: ScreenModel) =>
      http.put<{ data: PointDetail }>(`${base}/points/${id}`, { values }).then(data),
    removePoint: (id: number) => http.delete<void>(`${base}/points/${id}`).then(() => undefined),

    carriers: () => http.get<{ data: CarrierRow[] }>(`${base}/carriers`).then(data),
    carrier: (key: string) =>
      http.get<{ data: CarrierDetail }>(`${base}/carriers/${key}`).then(data),
    saveCarrier: (
      key: string,
      body: {
        enabled?: boolean
        settings?: Record<string, unknown>
        credentials?: Record<string, string>
        clear?: string[]
      },
    ) => http.put<{ data: CarrierDetail }>(`${base}/carriers/${key}`, body).then(data),
    testCarrier: (key: string, credentials: Record<string, string>) =>
      http
        .post<{
          data: { ok: boolean; message: string | null }
        }>(`${base}/carriers/${key}/test`, { credentials })
        .then(data),
    runSync: (key: string, directory: string) =>
      http
        .post<{ data: { queued: string[] } }>(`${base}/carriers/${key}/sync/${directory}`, {})
        .then(data),
    confirmRemoval: (key: string, directory: string) =>
      http.post<{ data: unknown }>(`${base}/carriers/${key}/confirm/${directory}`, {}).then(data),

    countries: () => http.get<{ data: Countries }>(`${base}/geography/countries`).then(data),
    enableCountry: (code: string) =>
      http.post<void>(`${base}/geography/countries`, { code }).then(() => undefined),
    disableCountry: (code: string) =>
      http.delete<void>(`${base}/geography/countries/${code}`).then(() => undefined),
    /** Gone when nothing is in it, switched off otherwise — the answer says which. */
    removeCountry: (code: string) =>
      http
        .post<{ data: { removal: 'deleted' | 'deactivated' } }>(
          `${base}/geography/countries/${code}/remove`,
          {},
        )
        .then(data)
        .then((answer) => answer.removal),
    orderCountries: (codes: string[]) =>
      http.put<void>(`${base}/geography/countries/order`, { codes }).then(() => undefined),
    lead: (code: string, carrier: string | null) =>
      http
        .put<void>(`${base}/geography/countries/${code}/leader`, { carrier })
        .then(() => undefined),

    regions: (country: string) =>
      http.get<{ data: RegionRow[] }>(`${base}/geography/regions${query({ country })}`).then(data),
    createRegion: (country: string, names: Record<string, string>, code?: string | null) =>
      http
        .post<{ data: { id: number } }>(`${base}/geography/regions`, { country, names, code })
        .then(data),
    saveRegion: (id: number, changes: { names?: Record<string, string>; code?: string | null }) =>
      http.put<void>(`${base}/geography/regions/${id}`, changes).then(() => undefined),
    removeRegion: (id: number) =>
      http.delete<void>(`${base}/geography/regions/${id}`).then(() => undefined),

    localities: (params: {
      page?: number
      per_page?: number
      q?: string
      country?: string
      region?: number | null
      source?: string
      state?: string
    }) => http.get<Page<LocalityRow>>(`${base}/geography/localities${query(params)}`),
    suggest: (q: string, country?: string | null) =>
      http
        .get<{
          data: LocalityBrief[]
        }>(`${base}/geography/localities/suggest${query({ q, country })}`)
        .then(data),
    brief: (ids: number[]) => {
      const search = new URLSearchParams()

      for (const id of ids) search.append('ids[]', String(id))

      return http
        .get<{ data: LocalityBrief[] }>(`${base}/geography/localities/brief?${search}`)
        .then(data)
    },
    locality: (id: number) =>
      http.get<{ data: LocalityDetail }>(`${base}/geography/localities/${id}`).then(data),
    createLocality: (values: ScreenModel, force = false) =>
      http
        .post<{ data: LocalityDetail }>(`${base}/geography/localities`, { values, force })
        .then(data),
    saveLocality: (id: number, values: ScreenModel, force = false) =>
      http
        .put<{ data: LocalityDetail }>(`${base}/geography/localities/${id}`, { values, force })
        .then(data),
    removeLocality: (id: number) =>
      http
        .delete<{
          data: { removal: 'deleted' | 'deactivated' }
        }>(`${base}/geography/localities/${id}`)
        .then(data),
    activateLocality: (id: number) =>
      http.post<void>(`${base}/geography/localities/${id}/activate`, {}).then(() => undefined),

    popular: () => http.get<{ data: LocalityBrief[] }>(`${base}/popular`).then(data),
    setPopular: (ids: number[]) =>
      http.put<{ data: LocalityBrief[] }>(`${base}/popular`, { ids }).then(data),
    addPopular: (id: number) =>
      http.post<{ data: LocalityBrief[] }>(`${base}/popular`, { locality_id: id }).then(data),
    removePopular: (id: number) =>
      http.delete<{ data: LocalityBrief[] }>(`${base}/popular/${id}`).then(data),

    bindings: (params: { page?: number; per_page?: number; carrier?: string; reason?: string }) =>
      http.get<BindingsPage>(`${base}/bindings${query(params)}`),
    batch: (body: {
      action: 'bind' | 'create' | 'ignore'
      ids?: number[]
      all?: boolean
      carrier?: string
      reason?: string
      locality_id?: number
    }) => http.post<{ data: BatchResult }>(`${base}/bindings/batch`, body).then(data),
  }
}

export type DeliveryApi = ReturnType<typeof createDeliveryApi>

/** The body of a refusal, when the panel's HTTP client kept one. */
export function bodyOf<T>(error: unknown): T | null {
  return ((error as { body?: T } | null)?.body ?? null) as T | null
}

export function statusOf(error: unknown): number | null {
  const status = (error as { status?: number } | null)?.status

  return typeof status === 'number' ? status : null
}
