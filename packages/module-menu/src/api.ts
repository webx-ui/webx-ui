import type { AdminContext } from '@webx-ui/module-admin'
import type { MenuInput, MenuItemInput, MenuItemRow, MenuRow } from './types'

export interface MenusApi {
  /** Every menu: the ones a template asks for and the ones somebody made. */
  list(): Promise<MenuRow[]>
  create(input: MenuInput): Promise<MenuRow>
  /** The name, and for a menu of somebody's own the key as well. */
  update(key: string, input: Partial<MenuInput>): Promise<MenuRow>
  /** Only a menu of somebody's own, and its items go with it. */
  remove(key: string): Promise<void>

  /** The whole tree, nested. Menus are small enough that a level at a time would only cost. */
  items(key: string): Promise<MenuItemRow[]>
  addItem(key: string, input: MenuItemInput): Promise<MenuItemRow>
  saveItem(key: string, id: number, input: MenuItemInput): Promise<MenuItemRow>
  /** Under this parent, at this position among its children. */
  moveItem(key: string, id: number, parentId: number | null, index: number): Promise<void>
  /** With the subtree under it. Answers how many went. */
  removeItem(key: string, id: number): Promise<number>

  /** Forget one menu's cache, in every language. */
  flush(key: string): Promise<void>
  /** The same for every menu at once. */
  flushAll(): Promise<void>
}

/** Everything under `/menus`, below the panel's API path. */
export function createMenusApi(admin: AdminContext): MenusApi {
  const base = `${admin.apiPath}/menus`
  const data = <T>(body: { data: T }): T => body.data

  return {
    list: () => admin.http.get<{ data: MenuRow[] }>(base).then(data),
    create: (input) => admin.http.post<{ data: MenuRow }>(base, input).then(data),
    update: (key, input) =>
      admin.http.patch<{ data: MenuRow }>(`${base}/${encodeURIComponent(key)}`, input).then(data),
    remove: (key) => admin.http.delete(`${base}/${encodeURIComponent(key)}`).then(() => undefined),

    items: (key) =>
      admin.http
        .get<{ data: MenuItemRow[] }>(`${base}/${encodeURIComponent(key)}/items`)
        .then(data),
    addItem: (key, input) =>
      admin.http
        .post<{ data: MenuItemRow }>(`${base}/${encodeURIComponent(key)}/items`, input)
        .then(data),
    saveItem: (key, id, input) =>
      admin.http
        .patch<{ data: MenuItemRow }>(`${base}/${encodeURIComponent(key)}/items/${id}`, input)
        .then(data),
    moveItem: (key, id, parentId, index) =>
      admin.http
        .post(`${base}/${encodeURIComponent(key)}/items/${id}/move`, {
          parent_id: parentId,
          index,
        })
        .then(() => undefined),
    removeItem: (key, id) =>
      admin.http
        .delete<{ data: { deleted: number } }>(`${base}/${encodeURIComponent(key)}/items/${id}`)
        .then((body) => body.data.deleted),

    flush: (key) =>
      admin.http.post(`${base}/${encodeURIComponent(key)}/cache/flush`, {}).then(() => undefined),
    flushAll: () => admin.http.post(`${base}/cache/flush`, {}).then(() => undefined),
  }
}
