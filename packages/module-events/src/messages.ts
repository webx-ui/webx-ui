import type { Messages } from '@webx-ui/module-admin'

/**
 * The English this package speaks on its own, before the server's dictionary arrives, or without
 * one. The server ships the same lines in ten languages under `webx-events::` and overrides
 * these; `messages.test.ts` keeps the two sets of keys equal.
 */
export const eventsMessages: Record<string, Messages> = {
  module: {
    group: 'Events',
    events: 'Events',
    categories: 'Categories',
  },
  panel: {
    new: 'New event',
    'new-title': 'New event',
    'field-title': 'Title',
    create: 'Create',
    cancel: 'Cancel',
    search: 'Search by title or address',
    empty: 'No events yet.',
    'empty-upcoming': 'Nothing ahead. The past events are on the next tab.',
    'empty-past': 'No event is over yet.',
    'empty-search': 'Nothing matches that.',
    'empty-bin': 'The bin is empty.',

    'status-draft': 'Draft',
    'status-published': 'Live',
    'status-modified': 'Live',
    'status-unpublished': 'Off the site',
    edits: 'edits',
    past: 'Over',
    'no-address': 'No address in this language',
    'no-date': 'No date',
    'main-category': 'The main category: it goes in the breadcrumbs',

    'view-upcoming': 'Upcoming',
    'view-past': 'Past',
    'view-all': 'All',
    bin: 'Bin',

    'column-title': 'Event',
    'column-when': 'When',
    'column-deleted': 'Deleted',
    'column-categories': 'Categories',
    'column-status': 'State',

    'filter-status': 'State',
    'any-status': 'Any state',
    'filter-category': 'Category',
    'any-category': 'Any category',
    'filter-service': 'Service',
    'any-service': 'Any service',

    open: 'Open',
    'open-on-site': 'Open on the site',
    duplicate: 'Duplicate',
    publish: 'Publish',
    unpublish: 'Take off the site',
    delete: 'Delete',
    restore: 'Restore',
    'delete-title': 'Delete “:title”?',
    'delete-text': 'It goes to the bin and comes off the site, and its address is free again.',
    deleted: 'The event is in the bin.',
    restored: 'The event is back.',
    published: 'The event is on the site.',
    'unpublished-done': 'The event is off the site.',
  },
  event: {
    trail: 'Where this event sits',
    untitled: 'Untitled',
    save: 'Save',
    'save-failed': 'The event was not saved.',
    duplicated: 'This is the copy: a draft. Set its date and publish it.',
    'publish-title': 'Put “:title” on the site?',
    'publish-text':
      'It answers at :address from the moment you do, for everyone — with its categories and services as they are chosen now.',
    'publish-nowhere': 'It has no address in this language yet, so nothing will answer.',
    preview: 'Preview',
    discard: 'Discard changes',
    'discard-title': 'Discard what is waiting?',
    'discard-text':
      'The event goes back to what the site is showing. What was written since is not listed anywhere.',
    discarded: 'The event is back to what is published.',
    'conflict-title': 'The event changed while you were editing',
    'conflict-mine': 'Keep mine',
    'conflict-theirs': 'Take the newer version',
    'conflict-theirs-title': 'Give up what you wrote?',
    'conflict-theirs-text':
      'The event is read again as it now is, and what you have typed since goes.',
    leave: 'Leave',
    'leave-title': 'Leave without saving?',
    'leave-text': 'The event could not be saved, and what you wrote is not on the server.',
    'live-since': 'On the site since :date',
    'address-moving':
      'The address is changing. The old one keeps working and leads to the new one.',
    'history-empty': 'This event has never been published.',
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
      'A category is a kind of event — breakfast meetings, cooking classes — with a page of its own listing what is ahead.',
    order: 'Drag to change the order of the categories on the site.',
    hidden: 'Hidden from the site',
    'no-address': 'No address in this language',
    events: 'Events: :count',
    'show-events': 'Show its events',
    'delete-blocked': 'This category still holds events. Move them first.',
    'delete-text': 'It goes to the bin and its page comes off the site. Its events stay.',
    deleted: 'The category is in the bin.',
    saved: 'The category is saved.',
    'field-title': 'Title',
    'field-slug': 'Address',
    'address-moving':
      'The address is changing. The old one keeps working and leads to the new one.',
  },
}
