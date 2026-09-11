/** Where a file has got to. */
export type UploadStatus = 'ready' | 'uploading' | 'done' | 'error'

export interface UploadFile {
  /** Stable across the file's whole life, including a retry. */
  id: string
  name: string
  /** In bytes. `0` for a file that arrived as a URL rather than from disk. */
  size: number
  type: string
  status: UploadStatus
  /** How far along, 0–100. Meaningful while `uploading`. */
  progress: number
  /** What went wrong, for a file that failed. */
  error?: string
  /** Where it ended up, once the caller knows. */
  url?: string
  /** The file itself, for anything picked from disk. */
  raw?: File
}

export interface UploadProps {
  /** What the file input accepts, e.g. `image/*,.pdf`. */
  accept?: string
  multiple?: boolean
  /** Largest file, in bytes. Anything over it is refused before it is added. */
  maxSize?: number
  /** Largest number of files. */
  max?: number
  /** Drops the drop zone and leaves a button. */
  buttonOnly?: boolean
  /** Label of the button. */
  buttonText?: string
  /** The line inside the drop zone. */
  hint?: string
  /** Shows each file as a thumbnail rather than a row. For pictures. */
  gallery?: boolean
  disabled?: boolean
  /** Whether a file can be taken off the list once it is there. */
  removable?: boolean
  /**
   * Asked about every file before it joins the list. Return `false` — or a rejected
   * promise — to refuse it. This is where a caller checks dimensions, or renames.
   */
  beforeAdd?: (file: File) => boolean | Promise<boolean>
}

export interface UploadEmits {
  /** Files that passed the checks and joined the list. */
  add: [files: UploadFile[]]
  /** A file was taken off the list. */
  remove: [file: UploadFile]
  /** A file was refused, and why. */
  reject: [file: File, reason: 'size' | 'count' | 'type' | 'rejected']
  /** A file in the list was chosen — to preview it, usually. */
  select: [file: UploadFile]
}
