import type { AdminModule } from '@webx-ui/admin'
import MediaManager from './MediaManager.vue'
import { mediaMessages } from './messages'

export interface MediaOptions {
  /** Where the section lives inside the panel. */
  path?: string
}

/**
 * The file manager as a section of the panel.
 *
 * The id matches the module the server reports, which is what makes the entry appear in the
 * navigation: a section shows up when both halves are installed, and stays out of the way when
 * only one is.
 */
export function media(options: MediaOptions = {}): AdminModule {
  const path = options.path ?? '/media'

  return {
    id: 'media',
    path,
    routes: [
      {
        path,
        name: 'webx.media',
        component: MediaManager,
      },
    ],
  }
}

export { mediaMessages }
export { createMediaApi, type MediaApi } from './api'
export { openMediaPicker, type MediaPickerOptions } from './openMediaPicker'
export { default as WxMediaManager } from './MediaManager.vue'
export { default as WxMediaPicker } from './MediaPicker.vue'
export { default as WxMediaField } from './MediaField.vue'
export type {
  DirectoryNotEmpty,
  EditOperations,
  FileQuery,
  MediaDirectory,
  MediaFile,
  MediaKind,
  MediaPage,
  MediaValue,
} from './types'
