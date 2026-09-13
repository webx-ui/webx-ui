import type { Messages } from '@webx-ui/admin'

/**
 * What this package says, in English.
 *
 * The same keys `webx-ui/module-media` ships as `lang/en/*.php`, and the same ones every other
 * language there has. Kept here so a manager placed by hand, with no WebX UI server behind it,
 * still has words — and so a key never reaches the screen when a translation is missing.
 */
export const mediaMessages: Record<string, Messages> = {
  module: {
    title: 'Files',
  },
  manager: {
    root: 'Library',
    search: 'Search files',
    'new-folder': 'New folder',
    'folder-name': 'Folder name',
    rename: 'Rename',
    move: 'Move',
    'move-to': 'Move to…',
    delete: 'Delete',
    upload: 'Upload',
    'upload-hint': 'Drop files here or choose them',
    select: 'Select',
    selected: ':count selected',
    empty: 'This folder is empty',
    'empty-search': 'Nothing matches :query',
    'all-types': 'All types',
    image: 'Images',
    video: 'Video',
    audio: 'Audio',
    document: 'Documents',
    other: 'Other',
    'sort-newest': 'Newest first',
    'sort-oldest': 'Oldest first',
    'sort-name': 'By name',
    'sort-size': 'Largest first',
    'duplicate-added': 'That file was already in this folder',
    uploaded: ':count file(s) added',
    cancel: 'Cancel',
    folders: 'Folders',
    edit: 'Edit',
    'copy-link': 'Copy the link',
    'link-copied': 'Link copied',
  },
  dialogs: {
    'delete-files-title': 'Delete :count file(s)?',
    'delete-files-text': 'Anywhere they are already used, they will stop opening.',
    'delete-folder-title': 'Delete the folder :title?',
    'delete-folder-contents': 'It holds :files file(s) and :directories folder(s).',
    'delete-folder-warning':
      'Everything inside goes too, and pictures already placed in content will stop opening.',
    confirm: 'Delete',
  },
  errors: {
    'directory-not-empty': 'This folder is not empty.',
    'root-immutable': 'The library root cannot be moved or deleted.',
    'directory-into-itself': 'A folder cannot be moved into itself.',
    'not-an-image': 'This file is not an image.',
    'image-too-large': 'This image is larger than :megapixels megapixels.',
    upload: 'That file was not accepted.',
    copy: 'The clipboard is not available here. The address:',
  },
  files: {
    copy: ':name (copy)',
  },
  validation: {
    title: 'name',
    parent_id: 'parent folder',
    directory_id: 'folder',
    files: 'files',
  },
}
