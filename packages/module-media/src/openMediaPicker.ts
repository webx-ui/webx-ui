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
export const openMediaPicker = createModal<MediaFile, MediaPickerOptions>(MediaPicker, {
  resolveOn: 'pick',
})
