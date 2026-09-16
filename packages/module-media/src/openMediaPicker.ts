import { createModal } from '@webx-ui/core'
import MediaPicker from './MediaPicker.vue'
import type { MediaFile, MediaKind } from './types'

export interface MediaPickerOptions {
  /** Narrow what can be chosen; `null` offers everything. */
  accept?: MediaKind | null
  title?: string
}

export interface MediaFilesOptions extends MediaPickerOptions {
  /**
   * The most that may be chosen at once.
   *
   * The dialog keeps to it, and so does the server: the limit lives in the field's schema, and
   * a request that never opened a dialog has to meet it too.
   */
  max?: number | null
}

/**
 * The library, opened from code, resolving with the file that was chosen.
 *
 * A form does not need to own a dialog for this — `const file = await openMediaPicker()` is the
 * whole integration, and `undefined` means the person closed it.
 */
export const openMediaPicker = createModal<MediaFile, MediaPickerOptions>(MediaPicker)

/**
 * The same library, resolving with everything that was chosen.
 *
 * A second function rather than a flag on the first one, because the answer is a different
 * shape: `MediaFile[]` here against `MediaFile` there, said in the signature instead of in a
 * conditional type nobody can read. `undefined` still means the dialog was closed.
 */
export const openMediaFiles = createModal<MediaFile[], MediaFilesOptions>(MediaPicker, {
  props: { multiple: true },
})

/**
 * The whole library as a dialog, from anywhere.
 *
 * The same manager, the same folders, the same uploads — for a screen that needs to put files in
 * order without navigating away from what it was doing. It resolves when a file is opened, and
 * with `undefined` when the dialog is simply closed.
 */
export const openMediaLibrary = createModal<MediaFile, Omit<MediaPickerOptions, 'accept'>>(
  MediaPicker,
  { props: { manage: true } },
)
