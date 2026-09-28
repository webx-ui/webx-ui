import type { Messages } from '@webx-ui/module-admin'

/**
 * The English this package speaks on its own, before the server's dictionary arrives, or without
 * one. The server ships the same lines in ten languages under `webx-vacancies::` and overrides
 * these; `messages.test.ts` keeps the two sets of keys equal.
 *
 * `module`, `panel`, `editor` and `category` are the panel's own and match the server's files key
 * for key. `vacancy` is the site's group — the words of the page of a vacancy — and the panel
 * borrows the handful it prints in a row: where, the kind of employment. Only those are here.
 */
export const vacanciesMessages: Record<string, Messages> = {
  module: {
    group: 'Vacancies',
    vacancies: 'Vacancies',
    categories: 'Categories',
  },
  panel: {
    new: 'New vacancy',
    'new-title': 'New vacancy',
    'field-title': 'Position',
    create: 'Create',
    cancel: 'Cancel',
    search: 'Search by position or address',
    empty: 'No vacancies yet.',
    'empty-help':
      'A vacancy is who you are looking for: the position, where, the terms and the pay.',
    'empty-open': 'No open vacancies. The closed ones are on the next tab.',
    'empty-closed': 'No vacancy is closed.',
    'empty-search': 'Nothing matches that.',
    'empty-bin': 'The bin is empty.',

    'status-draft': 'Draft',
    'status-published': 'Live',
    'status-modified': 'Live',
    'status-unpublished': 'Off the site',
    edits: 'edits',
    'closed-manual': 'Closed',
    'closed-expired': 'Expired',
    'closed-manual-help': 'The hiring was closed by hand.',
    'closed-expired-help': 'Its last day is behind us.',
    'no-address': 'No address in this language',
    'no-city': 'No city',
    until: 'until :date',
    'until-help': 'Open to the end of :date',

    'view-open': 'Open',
    'view-closed': 'Closed',
    'view-all': 'All',
    bin: 'Bin',

    filters: 'Filters',
    'filters-reset': 'Reset the filters',
    'filter-status': 'State',
    'any-status': 'Any state',
    'filter-category': 'Category',
    'any-category': 'Any category',

    'order-all': 'Drag a vacancy by its handle to change the order the site lists them in.',
    'order-closed':
      'The order is set on the Open and All tabs: a closed vacancy is on no list of the site.',
    'order-locked':
      'The order can only be changed in the whole list — clear the search and the filters.',
    'reorder-failed': 'The new order was not saved.',

    open: 'Open',
    'open-on-site': 'Open on the site',
    'copy-address': 'Copy the address',
    'address-copied': 'The address is copied.',
    duplicate: 'Duplicate',
    duplicated: 'This is the copy: a draft. Change what differs and publish it.',
    close: 'Close the hiring',
    reopen: 'Reopen the hiring',
    'close-title': 'Close “:title”?',
    'close-text':
      'It leaves every list of the site. Its page stays, marked as closed, so links from job boards still lead somewhere.',
    'reopen-title': 'Reopen “:title”?',
    'reopen-text': 'It is back on the lists of the site, unless its last day is already behind us.',
    'reopen-expired':
      'Its last day is behind us, so it stays closed: change “Open until” in the editor to reopen it.',
    closed: 'The hiring is closed.',
    reopened: 'The hiring is open again.',
    'close-edits':
      'This vacancy has edits waiting. Publish or discard them first — closing would put them on the site too.',
    publish: 'Publish',
    unpublish: 'Take off the site',
    delete: 'Delete',
    restore: 'Restore',
    'delete-title': 'Delete “:title”?',
    'delete-text': 'It goes to the bin and comes off the site, and its address is free again.',
    deleted: 'The vacancy is in the bin.',
    restored: 'The vacancy is back.',
    published: 'The vacancy is on the site.',
    'unpublished-done': 'The vacancy is off the site.',
  },
  editor: {
    trail: 'Where this vacancy sits',
    untitled: 'Untitled',
    save: 'Save',
    'save-failed': 'The vacancy was not saved.',
    'publish-title': 'Put “:title” on the site?',
    'publish-text':
      'It answers at :address from the moment you do, for everyone — with its categories and form as they are chosen now.',
    'publish-nowhere': 'It has no address in this language yet, so nothing will answer.',
    preview: 'Preview',
    discard: 'Discard changes',
    'discard-title': 'Discard what is waiting?',
    'discard-text':
      'The vacancy goes back to what the site is showing. What was written since is not listed anywhere.',
    discarded: 'The vacancy is back to what is published.',
    'conflict-title': 'The vacancy changed while you were editing',
    'conflict-mine': 'Keep mine',
    'conflict-theirs': 'Take the newer version',
    'conflict-theirs-title': 'Give up what you wrote?',
    'conflict-theirs-text':
      'The vacancy is read again as it now is, and what you have typed since goes.',
    leave: 'Leave',
    'leave-title': 'Leave without saving?',
    'leave-text': 'The vacancy could not be saved, and what you wrote is not on the server.',
    'live-since': 'On the site since :date',
    'open-until': 'Open until :date',
    'address-moving':
      'The address is changing. The old one keeps working and leads to the new one.',
    'history-empty': 'This vacancy has never been published.',
    version: '#:number',
    'version-live': 'On the site',
    'source-panel': 'From the panel',
    'source-mcp': 'By an agent',
    'source-import': 'Imported',
    'restore-title': 'Restore version :number?',
    'restore-text':
      'It becomes the draft. The site keeps showing what is published until you publish this.',
    'restore-version': 'Restore',
    'restored-version': 'Version :number is now the draft.',
  },
  category: {
    new: 'New category',
    empty: 'No categories yet.',
    'empty-help':
      'A category is a group of vacancies — development, sales — and a filter of the list on the site. It has no page of its own.',
    order: 'Drag to change the order of the groups on the site.',
    hidden: 'Hidden from the site',
    'no-page': 'A group, with no page of its own',
    vacancies: 'Vacancies: :count',
    'show-vacancies': 'Show its vacancies',
    'delete-blocked': 'This category still holds vacancies. Move them first.',
    'delete-text': 'It goes to the bin. Its vacancies stay.',
    deleted: 'The category is in the bin.',
    saved: 'The category is saved.',
    'field-title': 'Title',
    'field-slug': 'Key',
    'field-slug-help':
      'In the address of the filter: ?category=… Latin letters, digits and hyphens.',
  },
  vacancy: {
    workplace: {
      onsite: 'On site',
      remote: 'Remote',
      hybrid: 'Hybrid',
    },
    employment: {
      FULL_TIME: 'Full-time',
      PART_TIME: 'Part-time',
      CONTRACTOR: 'Contract',
      TEMPORARY: 'Temporary',
      INTERN: 'Internship',
      VOLUNTEER: 'Volunteer',
      PER_DIEM: 'Per day',
      OTHER: 'Other',
    },
  },
}

/** The word of a schema.org employment type: `FULL_TIME` → `vacancy.employment.FULL_TIME`, as the server names it. */
export function employmentKey(code: string): string {
  return `vacancy.employment.${code}`
}
