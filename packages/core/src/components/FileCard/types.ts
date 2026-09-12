import type { IconName } from '../Icon/types'

export type FileCardSize = 'sm' | 'md' | 'lg'

export interface FileCardProps {
  /** The file name, extension and all. It is what the card shows and what it renames. */
  name: string
  /**
   * Where the file is. The copy action puts this on the clipboard, and a picture with
   * no `thumbnail` of its own is drawn from it.
   */
  url?: string
  /** A smaller picture to draw instead of `url` — the one the backend already made. */
  thumbnail?: string
  /**
   * MIME type. It decides picture or glyph; without it the extension decides, which is
   * right often enough and wrong for a `.bin` that happens to be a JPEG.
   */
  type?: string
  /** Icon to draw instead of the one the extension picks. */
  icon?: IconName
  /** Draws the card as chosen. The choosing itself belongs to whatever holds the cards. */
  selected?: boolean
  size?: FileCardSize
  /** Nothing can be done to the file: every action goes, and the name cannot be edited. */
  disabled?: boolean

  /** Offers renaming. The card edits the name in place and reports the new one. */
  renamable?: boolean
  /**
   * Offers the edit action, which says "open this picture in an editor" and nothing
   * more — the editing itself is somebody else's component. Pictures only: there is
   * nothing to open for a `.zip`.
   */
  editable?: boolean
  removable?: boolean
  /** Offers copying `url` to the clipboard. */
  copyable?: boolean

  /*
   * The card draws its own buttons, so it has to know what to call them. Every label is
   * both the tooltip and the accessible name.
   */
  renameLabel?: string
  /** The two buttons under the field renaming opens. */
  saveLabel?: string
  cancelLabel?: string
  editLabel?: string
  removeLabel?: string
  copyLabel?: string
  /** Shown on the copy action for a moment after it worked. */
  copiedLabel?: string
}

export interface FileCardEmits {
  /** A new name was committed. The file is not renamed until the caller says so. */
  rename: [name: string]
  /** Open this picture in an editor. */
  edit: []
  remove: []
  /** The URL reached the clipboard. */
  copy: [url: string]
  /** It did not — no clipboard, or permission refused. */
  'copy-error': [error: unknown]
}
