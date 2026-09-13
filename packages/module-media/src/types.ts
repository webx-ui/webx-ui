/** The shapes `webx-ui/module-media` answers with. */

export type MediaKind = 'image' | 'video' | 'audio' | 'document' | 'other'

export interface MediaDirectory {
  id: number
  parent_id: number | null
  title: string
  depth: number
  is_root: boolean
  files_count?: number
  children: MediaDirectory[]
}

export interface MediaFile {
  id: number
  directory_id: number
  name: string
  file_name: string
  extension: string
  mime: string
  type: MediaKind
  size: number
  width: number | null
  height: number | null
  /** What an entity stores. The address beside it is worked out on every read. */
  path: string
  url: string
  thumb: string | null
  /**
   * The picture served by the panel itself, for the one thing that needs the pixels back.
   *
   * `url` is the address to show a file at, and on a CDN it is another origin — which a canvas
   * may not be written out from. The editor reads from here instead.
   */
  source: string | null
  editable: boolean
  has_original: boolean
  duplicate: boolean
  created_at: string | null
}

export interface MediaPage {
  data: MediaFile[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
  /** Everything the current filters match, not just this page. */
  stats: { files: number; size: number }
}

export interface FileQuery {
  directory_id?: number | null
  q?: string
  type?: MediaKind | null
  sort?: string
  page?: number
  per_page?: number
}

export interface EditOperations {
  crop?: { x: number; y: number; width: number; height: number }
  rotate?: 0 | 90 | 180 | 270
  flip?: 'horizontal' | 'vertical'
  resize?: { width?: number; height?: number }
}

/** What a form keeps about a picture it uses: the file, and this entity's words for it. */
export interface MediaValue {
  path: string
  alt?: string
  title?: string
  /** Filled in when the value was picked in this session, so a field can draw a preview. */
  url?: string
}

export interface DirectoryNotEmpty {
  code: 'directory_not_empty'
  message: string
  counts: { files: number; directories: number }
}
