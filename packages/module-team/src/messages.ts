import type { Messages } from '@webx-ui/module-admin'

/**
 * The English this package speaks on its own, before the server's dictionary arrives, or without
 * one. The server ships the same lines in ten languages under `webx-team::` and overrides these;
 * `messages.test.ts` keeps the two sets of keys equal.
 */
export const teamMessages: Record<string, Messages> = {
  module: {
    team: 'Team',
  },
  member: {
    new: 'New person',
    untitled: 'Untitled',
    search: 'Search the team',
    empty: 'Nobody here yet.',
    'empty-help': 'A person reaches the site in a team block, on any page.',
    'empty-search': 'Nothing matches that.',
    'empty-bin': 'The bin is empty.',
    all: 'All',
    trashed: 'Bin',
    'not-published': 'Not published',
    choose: 'Choose a person',
    'choose-help': 'Or add a new one: the first line of the list.',
    save: 'Save',
    saved: 'Saved.',
    'save-failed': 'Not saved.',
    cancel: 'Cancel',
    delete: 'Delete',
    'delete-title': 'Delete “:name”?',
    'delete-text':
      'They go to the bin and leave every block on the site. Restored, they come back to their places.',
    deleted: 'In the bin.',
    restore: 'Restore',
    restored: 'Back on the team.',
    'reorder-failed': 'The new order was not saved.',
    'leave-title': 'Leave without saving?',
    'leave-text': 'What you wrote here is not on the server.',
    leave: 'Leave',
    back: 'Back to the list',
  },
  screen: {
    person: 'Person',
    photo: 'Photo',
    'photo-help': 'Without one, the site shows the initials of the name.',
    name: 'Name',
    'name-help': 'Needed in the default language; where it is not translated, that one is shown.',
    'job-title': 'Job title',
    'job-title-help': 'Printed under the name.',
    about: 'About',
    text: 'Text',
    'text-help':
      'A few lines. Not written in a language, the person is still shown there, without it.',
    socials: 'Social links',
    'socials-help': 'In the order they are shown. Each is an address starting with https://.',
    network: 'Network',
    'network-placeholder': 'Choose a network',
    url: 'Address',
    'socials-add': 'Add a link',
    'socials-empty': 'No links.',
    services: 'Services',
    'services-help':
      'What this person does. A team block on the page of a service can show only its people.',
    settings: 'Settings',
    published: 'Published',
    'published-help': 'On the site in every team block, from the moment it is saved.',
  },
}
