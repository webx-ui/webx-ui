import type { AdminContext } from '@webx-ui/module-admin'
import type {
  DirectoryContents,
  EditOperations,
  FileInUse,
  FileQuery,
  MediaDirectory,
  MediaFile,
  MediaPage,
  OptimizeResult,
} from './types'

export interface MediaApi {
  directories(): Promise<MediaDirectory[]>
  createDirectory(parentId: number, title: string): Promise<MediaDirectory>
  renameDirectory(id: number, title: string): Promise<MediaDirectory>
  moveDirectory(id: number, parentId: number): Promise<MediaDirectory>
  /** Without `force` the server refuses a folder that holds anything, and says what it holds. */
  deleteDirectory(id: number, force?: boolean): Promise<void>
  /** What deleting the folder would take — counts and the files the site still uses. */
  directoryContents(id: number): Promise<DirectoryContents>

  files(query?: FileQuery): Promise<MediaPage>
  file(id: number): Promise<MediaFile>
  /**
   * The file behind a key.
   *
   * An entity stores the key and nothing else, so this is how a form draws the picture it
   * saved last time. `null` when the file is gone from the library.
   */
  fileByPath(path: string): Promise<MediaFile | null>
  /**
   * Files in one multipart request. The library's own screens send a piece at a time instead
   * (`useMediaUploads`); this stays for a caller that wants one request and no session.
   */
  upload(
    directoryId: number,
    files: File[],
    onProgress?: (percent: number) => void,
  ): Promise<MediaFile[]>
  /** A file that arrived a piece at a time, by its session's id, into a folder. */
  finishUpload(directoryId: number, uploadId: string): Promise<MediaFile>
  rename(id: number, name: string): Promise<MediaFile>
  move(ids: number[], directoryId: number): Promise<number>
  /** Which of these files the site still uses, and where — asked before a delete. */
  usage(ids: number[]): Promise<FileInUse[]>
  /**
   * Without `force` the server refuses files the site still uses (409, `files_in_use`), the way
   * `media_delete_files` does for an agent; `force` is the answer to «delete anyway».
   */
  remove(ids: number[], force?: boolean): Promise<number>
  removeOne(id: number, force?: boolean): Promise<void>

  edit(id: number, operations: EditOperations): Promise<MediaFile>
  copy(id: number): Promise<MediaFile>
  restoreOriginal(id: number): Promise<MediaFile>

  /**
   * The pictures of a selection — or else of a folder — that the current optimize settings have
   * not been through, and what they weigh now.
   */
  optimizePending(query: {
    ids?: number[]
    directoryId?: number | null
    /** The JPEG, PNG and HEIC pictures «Convert to WebP» would take instead. */
    convert?: boolean
  }): Promise<{
    ids: number[]
    size: number
  }>
  /**
   * Up to ten of them through the pipeline again, each over its own key — or, with `convert`,
   * into WebP under a new key with every reference on the site rewritten.
   */
  optimize(ids: number[], convert?: boolean): Promise<OptimizeResult[]>

  /** The address of a preview at a size the server allows. */
  thumb(file: MediaFile, width: number, height?: number, fit?: 'cover' | 'contain'): string | null
}

export function createMediaApi(admin: AdminContext): MediaApi {
  const base = `${admin.apiPath}/media`
  const data = <T>(body: { data: T }): T => body.data

  return {
    directories: () => admin.http.get<{ data: MediaDirectory[] }>(`${base}/directories`).then(data),

    createDirectory: (parentId, title) =>
      admin.http
        .post<{ data: MediaDirectory }>(`${base}/directories`, { parent_id: parentId, title })
        .then(data),

    renameDirectory: (id, title) =>
      admin.http.patch<{ data: MediaDirectory }>(`${base}/directories/${id}`, { title }).then(data),

    moveDirectory: (id, parentId) =>
      admin.http
        .patch<{ data: MediaDirectory }>(`${base}/directories/${id}/move`, { parent_id: parentId })
        .then(data),

    deleteDirectory: (id, force = false) =>
      admin.http.delete<void>(`${base}/directories/${id}`, {
        query: { force: force ? 1 : undefined },
      }),

    directoryContents: (id) =>
      admin.http
        .get<{ data: DirectoryContents }>(`${base}/directories/${id}/contents`)
        .then(data),

    files: (query = {}) =>
      admin.http.get<MediaPage>(`${base}/files`, {
        query: {
          directory_id: query.directory_id ?? undefined,
          q: query.q || undefined,
          type: query.type ?? undefined,
          sort: query.sort,
          page: query.page,
          per_page: query.per_page,
        },
      }),

    file: (id) => admin.http.get<{ data: MediaFile }>(`${base}/files/${id}`).then(data),

    fileByPath: (path) =>
      admin.http
        .get<{ data: MediaFile }>(`${base}/files/by-path?path=${encodeURIComponent(path)}`)
        .then(data)
        .catch(() => null),

    finishUpload: (directoryId, uploadId) =>
      admin.http
        .post<{
          data: MediaFile
        }>(`${base}/files/chunked`, { directory_id: directoryId, upload: uploadId })
        .then(data),

    async upload(directoryId, files, onProgress) {
      const body = new FormData()
      body.append('directory_id', String(directoryId))
      files.forEach((file) => body.append('files[]', file))

      // XHR rather than fetch, for the one thing fetch still cannot do: say how far an upload
      // has got. A person watching a forty-megabyte video wants a bar, not a spinner.
      return new Promise<MediaFile[]>((resolve, reject) => {
        const request = new XMLHttpRequest()

        request.open('POST', `${base}/files`)
        request.responseType = 'json'
        request.withCredentials = true
        request.setRequestHeader('Accept', 'application/json')
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest')
        // Without this the refusal comes back in English while the panel is in Russian: the
        // panel's own client sends it on every request, and this one is not that client.
        request.setRequestHeader('X-Webx-Locale', admin.i18n.state.locale)

        const token = csrfToken()
        if (token) {
          request.setRequestHeader('X-XSRF-TOKEN', token)
        }

        request.upload.addEventListener('progress', (event) => {
          if (event.lengthComputable) {
            onProgress?.(Math.round((event.loaded / event.total) * 100))
          }
        })

        request.addEventListener('load', () => {
          const answer = request.response as { data?: MediaFile[]; message?: string } | null

          if (request.status >= 200 && request.status < 300 && answer?.data) {
            resolve(answer.data)

            return
          }

          reject(
            Object.assign(new Error(answer?.message ?? 'Upload failed'), {
              status: request.status,
              body: answer,
            }),
          )
        })

        request.addEventListener('error', () => reject(new Error('Upload failed')))
        request.send(body)
      })
    },

    rename: (id, name) =>
      admin.http.patch<{ data: MediaFile }>(`${base}/files/${id}`, { name }).then(data),

    move: (ids, directoryId) =>
      admin.http
        .post<{ data: { moved: number } }>(`${base}/files/move`, { ids, directory_id: directoryId })
        .then((body) => body.data.moved),

    // A POST for the batch, not a DELETE: the panel's own client sends no body on DELETE, and
    // a list of ids in a query string is a worse answer than an honest verb.
    usage: (ids) =>
      admin.http.post<{ data: FileInUse[] }>(`${base}/files/usage`, { ids }).then(data),

    remove: (ids, force = false) =>
      admin.http
        .post<{
          data: { deleted: number }
        }>(`${base}/files/delete`, { ids, force: force || undefined })
        .then((body) => body.data.deleted),

    removeOne: (id, force = false) =>
      admin.http.delete<void>(`${base}/files/${id}`, { query: { force: force ? 1 : undefined } }),

    edit: (id, operations) =>
      admin.http.post<{ data: MediaFile }>(`${base}/files/${id}/edit`, operations).then(data),

    copy: (id) => admin.http.post<{ data: MediaFile }>(`${base}/files/${id}/copy`).then(data),

    restoreOriginal: (id) =>
      admin.http.post<{ data: MediaFile }>(`${base}/files/${id}/restore-original`).then(data),

    optimizePending: ({ ids, directoryId, convert }) =>
      admin.http
        .post<{ data: { ids: number[]; size: number } }>(`${base}/files/optimize/pending`, {
          ids: ids?.length ? ids : undefined,
          directory_id: ids?.length ? undefined : (directoryId ?? undefined),
          convert: convert || undefined,
        })
        .then(data),

    optimize: (ids, convert = false) =>
      admin.http
        .post<{
          data: OptimizeResult[]
        }>(`${base}/files/optimize`, { ids, convert: convert || undefined })
        .then(data),

    thumb(file, width, height, fit = 'cover') {
      if (!file.thumb) {
        return null
      }

      const url = new URL(file.thumb, window.location.origin)
      url.searchParams.set('w', String(width))
      if (height) {
        url.searchParams.set('h', String(height))
      }
      url.searchParams.set('fit', fit)

      return url.pathname + url.search
    },
  }
}

function csrfToken(): string | null {
  const cookie = document.cookie.split('; ').find((part) => part.startsWith('XSRF-TOKEN='))

  return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : null
}
