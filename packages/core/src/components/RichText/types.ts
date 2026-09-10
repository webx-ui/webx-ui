import type { ControlSize, ControlStatus } from '../../composables/useFormField'

export type RichTextTool =
  | 'bold'
  | 'italic'
  | 'strike'
  | 'code'
  | 'h2'
  | 'h3'
  | 'h4'
  | 'bulletList'
  | 'orderedList'
  | 'blockquote'
  | 'hr'
  | 'link'
  | 'table'
  | 'image'
  | 'youtube'
  | 'undo'
  | 'redo'
  /** A vertical rule between groups. */
  | 'divider'

export interface RichTextUploadResult {
  url: string
  alt?: string
}

/** Uploads one file and resolves with the URL it ended up at. */
export type RichTextUpload = (file: File) => Promise<RichTextUploadResult>

/** Opens a media library and resolves with the chosen URL, or `null` if cancelled. */
export type RichTextImagePicker = () => Promise<string | null>

export interface RichTextProps {
  placeholder?: string
  disabled?: boolean
  readonly?: boolean
  size?: ControlSize
  status?: ControlStatus
  id?: string
  /** Accessible label used when there is no visible `<label>`. */
  ariaLabel?: string
  /** Height of the editing area before it starts growing. */
  minHeight?: string
  /** Which buttons the toolbar shows, in order. */
  tools?: RichTextTool[]
  /**
   * Called for pasted and dropped files. Without it the editor accepts no files
   * at all, and the image button only offers the media library.
   */
  upload?: RichTextUpload
  /** Called by the image button — this is where a media library plugs in. */
  pickImage?: RichTextImagePicker
  /** MIME types accepted for upload. */
  accept?: string[]
}

export interface RichTextEmits {
  change: [html: string]
  focus: []
  blur: []
  /** An upload rejected. The editor stays untouched. */
  uploadError: [error: unknown, file: File]
}
