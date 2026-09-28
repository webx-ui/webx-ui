import type { AdminContext } from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'
import type { BannerDetail, BannerRow, PlaceInput, PlaceRow } from './types'

export interface BannersApi {
  /** Every place: the declared ones in the config's order, then the own ones by name. */
  places(): Promise<PlaceRow[]>
  createPlace(input: PlaceInput): Promise<PlaceRow>
  /** Only a place of somebody's own, and only its name: the key is what a template asks for. */
  renamePlace(key: string, title: PlaceInput['title']): Promise<PlaceRow>
  /** Only an own place with nothing in it, the bin included — a 422 with the count otherwise. */
  removePlace(key: string): Promise<void>

  /** The banners of a place in their order, or its bin. A declared place without a row: `[]`. */
  list(place: string, trashed?: boolean): Promise<BannerRow[]>
  get(id: number): Promise<BannerDetail>
  /**
   * A new banner out of the values of its form, in one request: the form is filled in first and
   * the record made on the first save, so nobody leaves an empty banner in a place.
   */
  create(place: string, values: ScreenModel): Promise<BannerDetail>
  /**
   * Refused with a 422 under the name of the field — `buttons.<n>.link` for a button's row. A
   * `place` other than the one it stands in moves it to the end of that one (decision 14).
   */
  save(id: number, values: ScreenModel, place?: string): Promise<BannerDetail>
  remove(id: number): Promise<void>
  restore(id: number): Promise<BannerRow>
  /** The order of one place, written whole. */
  reorder(place: string, ids: number[]): Promise<void>
}

/** Everything under `/banners`, below the panel's API path (§5.6). */
export function createBannersApi(admin: AdminContext): BannersApi {
  const base = `${admin.apiPath}/banners`
  const place = (key: string) => `${base}/places/${encodeURIComponent(key)}`
  const data = <T>(body: { data: T }): T => body.data

  return {
    places: () => admin.http.get<{ data: PlaceRow[] }>(`${base}/places`).then(data),
    createPlace: (input) => admin.http.post<{ data: PlaceRow }>(`${base}/places`, input).then(data),
    renamePlace: (key, title) =>
      admin.http.put<{ data: PlaceRow }>(place(key), { title }).then(data),
    removePlace: (key) => admin.http.delete<void>(place(key)).then(() => undefined),

    list: (key, trashed = false) =>
      admin.http
        .get<{ data: BannerRow[] }>(`${place(key)}/banners${trashed ? '?trashed=1' : ''}`)
        .then(data),
    get: (id) => admin.http.get<{ data: BannerDetail }>(`${base}/${id}`).then(data),
    create: (key, values) =>
      admin.http.post<{ data: BannerDetail }>(`${place(key)}/banners`, { values }).then(data),
    save: (id, values, key) =>
      admin.http
        .put<{ data: BannerDetail }>(
          `${base}/${id}`,
          key === undefined ? { values } : { values, place: key },
        )
        .then(data),
    remove: (id) => admin.http.delete<void>(`${base}/${id}`).then(() => undefined),
    // The resource of the list line, and not the form's pair: a restored banner stays closed.
    restore: (id) => admin.http.post<{ data: BannerRow }>(`${base}/${id}/restore`, {}).then(data),
    reorder: (key, ids) =>
      admin.http.post<void>(`${place(key)}/reorder`, { ids }).then(() => undefined),
  }
}
