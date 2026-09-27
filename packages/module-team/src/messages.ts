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
    empty: 'Nobody on the team yet.',
    'empty-help': 'A person reaches the site in a team block, on any page.',
    'empty-search': 'Nothing matches that.',
    'empty-bin': 'The bin is empty.',
    all: 'All',
    trashed: 'Bin',
    'not-published': 'Not published',
    'no-job-title': 'No job title',
    order: 'Drag to change the order on the site.',
    choose: 'Choose a person',
    'choose-help': 'Or add a new one: the first line of the list.',
    save: 'Save',
    saved: 'Saved.',
    'save-failed': 'Not saved.',
    cancel: 'Cancel',
    delete: 'Delete',
    'delete-title': 'Delete “:name” from the team?',
    'delete-text':
      'They go to the bin and leave every block on the site. Restored, they come back to their place.',
    deleted: 'Moved to the bin.',
    restore: 'Restore',
    restored: 'Restored.',
    'reorder-failed': 'The new order was not saved.',
    'leave-title': 'Leave without saving?',
    'leave-text': 'What you wrote about this person is not on the server.',
    leave: 'Leave',
    back: 'Back to the list',
  },
  screen: {
    person: 'Person',
    photo: 'Photo',
    'photo-help': 'Without one, the site shows the initials of the name.',
    name: 'Name',
    'name-help':
      'Required in the default language. Not written in another, the default one is shown.',
    'job-title': 'Job title',
    'job-title-help':
      'Printed under the name. Not written in a language, the default one is shown.',
    about: 'About',
    text: 'Text',
    'text-help': 'Printed only in the language it is written in. The person is shown either way.',
    links: 'Links',
    socials: 'Social networks',
    'socials-help': 'In the order the site prints them. A row left empty is dropped when you save.',
    'socials-add': 'Add a link',
    'socials-empty': 'No links.',
    network: 'Network',
    'network-placeholder': 'Choose a network',
    url: 'Address',
    services: 'Services',
    'services-help':
      'What the person provides: a team block on a service’s page can show only them.',
    settings: 'Settings',
    published: 'Published',
    'published-help': 'On the site in every team block, from the moment it is saved.',
  },
}
