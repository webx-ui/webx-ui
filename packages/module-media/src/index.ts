import type { AdminModule } from '@webx-ui/module-admin'
import FileField from './FileField.vue'
import FilesField from './FilesField.vue'
import GalleryField from './GalleryField.vue'
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
    // What a screen means by these four: the fields over the library. The server registers the
    // same names for the values it stores. Three of them are one component with a layout and a
    // limit — the names are what an author picks from a list, and nobody picks "media with
    // `multiple: true` and `accept: document`".
    types: {
      'wx-media': { component: MediaField, kind: 'field' },
      'wx-gallery': { component: GalleryField, kind: 'field' },
      'wx-file': { component: FileField, kind: 'field' },
      'wx-files': { component: FilesField, kind: 'field' },
    },
  }
}

export { mediaMessages }
export { createMediaApi, type MediaApi } from './api'
export {
  openMediaFiles,
  openMediaLibrary,
  openMediaPicker,
  type MediaFilesOptions,
  type MediaPickerOptions,
} from './openMediaPicker'
export { default as WxMediaManager } from './MediaManager.vue'
export { default as WxMediaPage } from './MediaPage.vue'
export { default as WxMediaPicker } from './MediaPicker.vue'
export { default as WxMediaField } from './MediaField.vue'
export { default as WxGalleryField } from './GalleryField.vue'
export { default as WxFileField } from './FileField.vue'
export { default as WxFilesField } from './FilesField.vue'
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
