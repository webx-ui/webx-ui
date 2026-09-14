import type { AdminModule } from '@webx-ui/module-admin'
import MediaField from './MediaField.vue'
import MediaPage from './MediaPage.vue'
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
        component: MediaPage,
      },
    ],
    // What a screen means by `wx-media`: the field over the library. The server registers
    // the same name for the value it stores.
    types: {
      'wx-media': { component: MediaField, kind: 'field' },
    },
  }
}

export { mediaMessages }
export { createMediaApi, type MediaApi } from './api'
export { openMediaLibrary, openMediaPicker, type MediaPickerOptions } from './openMediaPicker'
export { default as WxMediaManager } from './MediaManager.vue'
export { default as WxMediaPage } from './MediaPage.vue'
export { default as WxMediaPicker } from './MediaPicker.vue'
export { default as WxMediaField } from './MediaField.vue'
export type {
  DirectoryNotEmpty,
  EditOperations,
  FileQuery,
  MediaDirectory,
  MediaFile,
  MediaAspect,
  MediaKind,
  MediaPage,
  MediaValue,
} from './types'
