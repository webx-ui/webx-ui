import type { AdminContext } from '@webx-ui/module-admin'
import type { EditOperations, FileQuery, MediaDirectory, MediaFile, MediaPage } from './types'

export interface MediaApi {
  directories(): Promise<MediaDirectory[]>
  createDirectory(parentId: number, title: string): Promise<MediaDirectory>
  renameDirectory(id: number, title: string): Promise<MediaDirectory>
  moveDirectory(id: number, parentId: number): Promise<MediaDirectory>
  /** Without `force` the server refuses a folder that holds anything, and says what it holds. */
  deleteDirectory(id: number, force?: boolean): Promise<void>

  files(query?: FileQuery): Promise<MediaPage>
  file(id: number): Promise<MediaFile>
  /**
   * The file behind a key.
   *
   * An entity stores the key and nothing else, so this is how a form draws the picture it
   * saved last time. `null` when the file is gone from the library.
   */
  fileByPath(path: string): Promise<MediaFile | null>
  upload(
    directoryId: number,
    files: File[],
    onProgress?: (percent: number) => void,
  ): Promise<MediaFile[]>
  rename(id: number, name: string): Promise<MediaFile>
  move(ids: number[], directoryId: number): Promise<number>
  remove(ids: number[]): Promise<number>
  removeOne(id: number): Promise<void>

  edit(id: number, operations: EditOperations): Promise<MediaFile>
  copy(id: number): Promise<MediaFile>
  restoreOriginal(id: number): Promise<MediaFile>

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
    remove: (ids) =>
      admin.http
        .post<{ data: { deleted: number } }>(`${base}/files/delete`, { ids })
        .then((body) => body.data.deleted),

    removeOne: (id) => admin.http.delete<void>(`${base}/files/${id}`),

    edit: (id, operations) =>
      admin.http.post<{ data: MediaFile }>(`${base}/files/${id}/edit`, operations).then(data),

    copy: (id) => admin.http.post<{ data: MediaFile }>(`${base}/files/${id}/copy`).then(data),

    restoreOriginal: (id) =>
      admin.http.post<{ data: MediaFile }>(`${base}/files/${id}/restore-original`).then(data),

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
