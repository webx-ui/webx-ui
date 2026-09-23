import type { ControlSize, ControlStatus } from '../../composables/useFormField'
import type { LocalizedFieldProps, LocalizedValue } from '../../composables/useLocalized'

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

/** The keys of the second toolbar, the one that appears inside a table. */
export type RichTextTableTool =
  | 'addRowAfter'
  | 'addRowBefore'
  | 'addColumnAfter'
  | 'addColumnBefore'
  | 'deleteRow'
  | 'deleteColumn'
  | 'mergeOrSplit'
  | 'deleteTable'

/**
 * Everything this editor says out loud: a tooltip on every button, the two lines of the link
 * bar, and the word shown while a file is going up.
 */
export type RichTextLabelKey =
  | Exclude<RichTextTool, 'divider'>
  | RichTextTableTool
  /** The accessible name of the toolbar itself. */
  | 'toolbar'
  | 'linkAddress'
  | 'youtubeAddress'
  | 'apply'
  | 'cancel'
  | 'uploading'

/**
 * What the editor calls its own buttons. It knows nothing about the panel it is opened in, so
 * whoever opens it translates it; a key left out stays English.
 */
export type RichTextLabels = Partial<Record<RichTextLabelKey, string>>

/**
 * A picture going into the document: where it is right now, and what it is filed under.
 *
 * `path` is the library's own key, written into the document as `data-wx-path` and kept there.
 * The address is not the picture — a bucket that moves, a signed link that expires, an image
 * edited in place all change the URL and none of them change the key — so whoever stores the
 * document can work the address out again from the key rather than serving last month's.
 * Without a `path` the picture is simply an address, which is what an external one is.
 */
export interface RichTextImage {
  url: string
  path?: string
  alt?: string
}

/** What an upload answers with. */
export type RichTextUploadResult = RichTextImage

/** Uploads one file and resolves with where it ended up. */
export type RichTextUpload = (file: File) => Promise<RichTextImage>

/**
 * Opens a media library and resolves with what was chosen, or `null` if cancelled. A bare
 * string is an address and nothing else — fine for a picker with no library behind it.
 */
export type RichTextImagePicker = () => Promise<RichTextImage | string | null>

/** One language's HTML, or every language's when the field is localized. */
export type RichTextModelValue = string | LocalizedValue

export interface RichTextProps extends LocalizedFieldProps {
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
  /** What the buttons and the link bar are called, for a panel that is not in English. */
  labels?: RichTextLabels
}

export interface RichTextEmits {
  change: [html: string]
  focus: []
  blur: []
  /** An upload rejected. The editor stays untouched. */
  uploadError: [error: unknown, file: File]
}
