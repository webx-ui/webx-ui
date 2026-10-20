import type { Messages } from '@webx-ui/module-admin'

/**
 * The English this package speaks on its own, before the server's dictionary arrives, or without
 * one. The server ships the same lines in ten languages under `webx-snippets::` and overrides
 * these; `messages.test.ts` keeps the two sets of keys equal.
 */
export const snippetsMessages: Record<string, Messages> = {
  module: {
    title: 'Codes',
    snippet: 'Code',
    permission:
      "Lets a person run JavaScript on the site in the site's name: every visitor and every administrator who opens a page runs what they save.",
    places: {
      head: 'Head, as high as possible',
      body_start: 'Start of the body',
      body_end: 'End of the body',
    },
    consent: {
      necessary: 'Necessary',
      statistics: 'Analytics',
      marketing: 'Marketing',
      media: 'Media',
    },
  },
  screen: {
    name: 'Name',
    'name-help': 'For administrators only; never shown on the site.',
    place: 'Place',
    'place-help':
      'Where on the page the code is printed. Places of your own are declared in config/webx-snippets.php.',
    code: 'Code',
    'code-help':
      'Paste exactly what the service gave you, without <html>, <head> or <body>. document.write does not work in a code that waits for consent.',
    settings: 'Settings',
    enabled: 'Enabled',
    'enabled-help': 'An enabled code is on the site as soon as it is saved.',
    consent: 'Cookie consent',
    'consent-help':
      'Anything but "Necessary" is printed only after the visitor agrees to that category.',
    'on-stage': 'On dev and stage too',
    'on-stage-help':
      'Off: the code is printed only in production, so a copy of the site never counts visits.',
    coverage: 'Where it is printed',
    'only-paths': 'Only on',
    'only-paths-help':
      'Paths without the language, * for anything: /, /blog/*. Empty means the whole site.',
    'except-paths': 'Except',
    'except-paths-help': 'Paths where the code is never printed, like /blog/drafts/*.',
    locales: 'Languages',
    'locales-help': 'None ticked means every language.',
    history: 'History',
    position: 'Order',
  },
  panel: {
    new: 'New code',
    add: 'Add a code to “:place”',
    untitled: 'Untitled',
    all: 'Codes',
    bin: 'Bin',
    'empty-place': 'No codes in this place.',
    'empty-bin': 'The bin is empty.',
    choose: 'Choose a code',
    'choose-help': 'Or add one: the + beside the place it goes to.',
    environment:
      'The site runs as “:environment”: only the codes marked “On dev and stage too” are printed here.',
    undeclared: 'Not declared',
    orphaned:
      'This place is no longer in config/webx-snippets.php, so its codes are printed nowhere. Move them to another place or switch them off.',
    'orphaned-code':
      'The place “:place” is no longer declared, so this code is printed nowhere. Choose another place, or switch the code off to keep it.',
    'not-seen': 'Not printed on any page yet',
    'not-seen-help':
      'Open a page of the site after a deploy. If the place still is not seen, the layout has lost <x-webx-snippets place=":place" />.',
    'whole-site': 'Whole site',
    'only-paths': 'Only on paths: :count',
    'except-paths': 'Except paths: :count',
    'all-locales': 'All languages',
    'on-stage': 'Dev too',
    'not-here': 'Not printed on this copy',
    'not-here-help':
      'The site runs as “:environment” and prints only the codes marked “On dev and stage too”. Production prints this one.',
    off: 'Off',
    'switch-on': 'Switch on “:title”',
    'switched-on': 'The code is on the site.',
    'switched-off': 'The code is off the site.',
    'switch-failed': 'The code was not switched.',
    save: 'Save',
    saved: 'The code is saved.',
    'saved-warning': 'Saved. Look at the code, though: :warning',
    'save-failed': 'The code was not saved.',
    cancel: 'Cancel',
    delete: 'Delete',
    'delete-title': 'Delete “:title”?',
    'delete-text':
      'It goes to the bin and leaves the site at once. Restored, it comes back the way it was.',
    deleted: 'The code is in the bin.',
    restore: 'Restore',
    restored: 'The code is back.',
    purge: 'Delete for good',
    'purge-title': 'Delete “:title” for good?',
    'purge-text': 'The code and its history cannot be brought back.',
    purged: 'The code is deleted.',
    'deleted-at': 'Deleted',
    'reorder-hint':
      'Drag a code by its grip to change the order inside a place, or into another place to move it there.',
    'reorder-failed': 'The new order was not saved.',
    'leave-title': 'Leave without saving?',
    'leave-text': 'What you changed in this code is not on the server.',
    leave: 'Leave',
    back: 'Back to the list',
  },
}
