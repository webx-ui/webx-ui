import type { AdminModule } from '@webx-ui/module-admin'
import FileField from './FileField.vue'
import FilesField from './FilesField.vue'
import GalleryField from './GalleryField.vue'
import MediaField from './MediaField.vue'
import MediaPage from './MediaPage.vue'
import { mediaMessages } from './messages'
import { openMediaPicker } from './openMediaPicker'

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
      'wx-gallery': { component: GalleryField, kind: 'field', wide: true },
      'wx-file': { component: FileField, kind: 'field' },
      'wx-files': { component: FilesField, kind: 'field', wide: true },
    },
    /**
     * Where the panel's own fields get a picture from. `wx-rich-text` lives in
     * `module-admin`, which cannot reach the library — the dependency runs this way — so the
     * module that has the files hands the way in, and a panel without a file manager simply
     * has no image button in its editors.
     */
    pickImage: async () => {
      const file = await openMediaPicker({ accept: 'image' })

      // The key travels with the address, and it is the key that is kept: a document written
      // today has to still find its pictures after the library moves disks.
      return file ? { url: file.url, path: file.path } : null
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
