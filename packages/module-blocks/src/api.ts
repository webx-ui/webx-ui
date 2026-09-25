import type { AdminContext } from '@webx-ui/module-admin'
import type {
  BlockInput,
  BlockList,
  BlockType,
  BlockUsage,
  BlockVersion,
  BlockVersionMeta,
  DeclaredComponent,
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
  }
}
