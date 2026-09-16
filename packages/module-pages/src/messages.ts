import type { Messages } from '@webx-ui/module-admin'

/**
 * The English this package speaks on its own, before the server's dictionary arrives, or
 * without one. The server ships the same lines in ten languages under `webx-pages::` and
 * overrides these; `messages.test.ts` keeps the two sets of keys equal.
 */
export const pagesMessages: Record<string, Messages> = {
  module: {
    title: 'Pages',
  },
  pages: {
    home: 'Home',
  },
  page: {
    new: 'New page',
    search: 'Search by title or address',
    empty: 'No pages yet.',
    'empty-help': 'A site is its pages. The first one goes inside the home page.',
    'empty-search': 'Nothing matches that.',
    'empty-bin': 'The bin is empty.',

    'column-title': 'Title',
    'column-address': 'Address',
    'column-status': 'Status',
    'column-updated': 'Updated',
    'column-deleted': 'Deleted',

    'status-draft': 'Draft',
    'status-published': 'Published',
    'status-modified': 'Edited',
    'no-address': 'No address in this language',

    'filter-all': 'All',
    'filter-draft': 'Drafts',
    'filter-published': 'Published',
    'filter-modified': 'Edited',
    bin: 'Bin',

    open: 'Open',
    'add-child': 'Add a page inside',
    duplicate: 'Duplicate',
    move: 'Move…',
    'copy-address': 'Copy the address',
    'open-on-site': 'Open on the site',
    delete: 'Delete',
    restore: 'Restore',
    cancel: 'Cancel',

    'new-title': 'New page',
    'field-title': 'Title',
    'field-slug': 'Address',
    'slug-help': 'The last part of the address. Left empty, it is made from the title.',
    'field-parent': 'Inside',
    create: 'Create and open',

    'move-title': 'Move “:title”',
    'move-help':
      'Pick the page it goes inside. Everything under it moves too, and their addresses change with it.',
    'move-confirm': 'Move',
    moved: 'Moved. :count addresses changed; the old ones now redirect to the new ones.',
    'moved-one': 'Moved. The address changed; the old one now redirects to it.',

    'delete-title': 'Delete “:title”?',
    'delete-text': 'The page goes to the bin and stops answering on the site.',
    'delete-branch': 'The :count pages inside it go with it.',
    deleted: 'The page is in the bin.',
    restored: 'The page is back.',

    'copy-of': ':title (copy)',
    duplicated: 'The copy is ready, and it is not on the site.',
    'address-copied': 'The address is on the clipboard.',
  },
}
