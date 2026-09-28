import type { AdminContext } from '@webx-ui/module-admin'
import type {
  BlockInput,
  BlockList,
  BlockType,
  BlockUsage,
  BlockVersion,
  BlockNode,
  BlockVersionMeta,
  DeclaredComponent,
  RegionAdopted,
  RegionDetail,
  RegionRow,
  RegionVersion,
  RenderInput,
  RenderResult,
} from './types'

export interface BlocksApi {
  /** Every type, without content: the section's list. */
  list(): Promise<BlockType[]>
  /** The same list with the places modules declared for components beside it. */
  index(): Promise<BlockList>
  /** The published types with their content: what the constructor and the picker read. */
  catalog(): Promise<BlockType[]>
  get(id: number): Promise<BlockType>
  create(input: BlockInput): Promise<BlockType>
  update(id: number, input: BlockInput): Promise<BlockType>
  remove(id: number): Promise<void>
  /** A declared place made into the site's own type: an unpublished draft of the module's view. */
  customise(slug: string): Promise<BlockType>
  publish(id: number): Promise<BlockType>
  render(id: number, input?: RenderInput): Promise<RenderResult>
  usage(id: number): Promise<BlockUsage[]>
  versions(id: number): Promise<BlockVersionMeta[]>
  version(id: number, number: number): Promise<BlockVersion>
  restore(id: number, number: number): Promise<BlockType>
  /** The new order of some types — a group — in the places they held; the rest stay put. */
  reorder(ids: number[]): Promise<void>
}

/** Everything under `/blocks`, below the panel's API path. */
export function createBlocksApi(admin: AdminContext): BlocksApi {
  const base = `${admin.apiPath}/blocks`
  const data = <T>(body: { data: T }): T => body.data

  return {
    list: () => admin.http.get<{ data: BlockType[] }>(base).then(data),
    // `declared` stands beside `data` rather than in it: it is not a type, and a list of types
    // with something else mixed in is a list every other reader would have to filter.
    index: () =>
      admin.http
        .get<{ data: BlockType[]; declared?: DeclaredComponent[] }>(base)
        .then((body) => ({ blocks: body.data, declared: body.declared ?? [] })),
    catalog: () => admin.http.get<{ data: BlockType[] }>(`${base}/catalog`).then(data),
    get: (id) => admin.http.get<{ data: BlockType }>(`${base}/${id}`).then(data),
    create: (input) => admin.http.post<{ data: BlockType }>(base, input).then(data),
    update: (id, input) => admin.http.put<{ data: BlockType }>(`${base}/${id}`, input).then(data),
    remove: (id) => admin.http.delete<void>(`${base}/${id}`),
    customise: (slug) =>
      admin.http
        .post<{
          data: BlockType
        }>(`${base}/components/${encodeURIComponent(slug)}/customise`, {})
        .then(data),
    publish: (id) => admin.http.post<{ data: BlockType }>(`${base}/${id}/publish`, {}).then(data),
    render: (id, input = {}) =>
      admin.http.post<{ data: RenderResult }>(`${base}/${id}/render`, input).then(data),
    usage: (id) => admin.http.get<{ data: BlockUsage[] }>(`${base}/${id}/usage`).then(data),
    versions: (id) =>
      admin.http.get<{ data: BlockVersionMeta[] }>(`${base}/${id}/versions`).then(data),
    version: (id, number) =>
      admin.http.get<{ data: BlockVersion }>(`${base}/${id}/versions/${number}`).then(data),
    restore: (id, number) =>
      admin.http
        .post<{ data: BlockType }>(`${base}/${id}/versions/${number}/restore`, {})
        .then(data),
    reorder: (ids) => admin.http.post<unknown>(`${base}/reorder`, { ids }).then(() => undefined),
  }
}

export interface RegionsApi {
  /** Every region the site's config declares, saved or not. */
  list(): Promise<RegionRow[]>
  get(name: string): Promise<RegionDetail>
  /**
   * Save the draft. Refused with a 409 when the revision is not the current one — the error's
   * body is a {@link RegionConflict} carrying the revision to write over.
   */
  save(name: string, input: { blocks: BlockNode[]; revision?: string }): Promise<RegionDetail>
  publish(name: string): Promise<RegionDetail>
  /** Off the site: the code's view comes back, the blocks stay as the draft. */
  unpublish(name: string): Promise<RegionDetail>
  discardDraft(name: string): Promise<RegionDetail>
  versions(name: string): Promise<RegionVersion[]>
  /** An old publication becomes the draft; putting it on the site is a separate step. */
  restoreVersion(name: string, number: number): Promise<RegionDetail>
  /** The fallback view made into a block type, and one of it put into the draft. */
  adopt(name: string): Promise<RegionAdopted>
}

/** Everything under `/regions`, below the panel's API path. */
export function createRegionsApi(admin: AdminContext): RegionsApi {
  const base = `${admin.apiPath}/regions`
  const data = <T>(body: { data: T }): T => body.data
  const at = (name: string) => `${base}/${encodeURIComponent(name)}`

  return {
    list: () => admin.http.get<{ data: RegionRow[] }>(base).then(data),
    get: (name) => admin.http.get<{ data: RegionDetail }>(at(name)).then(data),
    save: (name, input) => admin.http.put<{ data: RegionDetail }>(at(name), input).then(data),
    publish: (name) =>
      admin.http.post<{ data: RegionDetail }>(`${at(name)}/publish`, {}).then(data),
    unpublish: (name) =>
      admin.http.post<{ data: RegionDetail }>(`${at(name)}/unpublish`, {}).then(data),
    discardDraft: (name) =>
      admin.http.delete<{ data: RegionDetail }>(`${at(name)}/draft`).then(data),
    versions: (name) =>
      admin.http.get<{ data: RegionVersion[] }>(`${at(name)}/versions`).then(data),
    restoreVersion: (name, number) =>
      admin.http
        .post<{ data: RegionDetail }>(`${at(name)}/versions/${number}/restore`, {})
        .then(data),
    adopt: (name) =>
      admin.http
        .post<{ data: RegionDetail; block: { id: number; slug: string } }>(`${at(name)}/adopt`, {})
        .then((body) => ({ region: body.data, block: body.block })),
  }
}
