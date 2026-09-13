import { createModal } from '@webx-ui/core'
import MediaPicker from './MediaPicker.vue'
import type { MediaFile, MediaKind } from './types'

export interface MediaPickerOptions {
  /** Narrow what can be chosen; `null` offers everything. */
  accept?: MediaKind | null
  title?: string
}

/**
 * The library, opened from code, resolving with the file that was chosen.
 *
 * A form does not need to own a dialog for this — `const file = await openMediaPicker()` is the
 * whole integration, and `undefined` means the person closed it.
 */
export const openMediaPicker = createModal<MediaFile, MediaPickerOptions>(MediaPicker)

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
