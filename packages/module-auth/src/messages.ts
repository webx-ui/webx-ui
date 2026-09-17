import type { Messages } from '@webx-ui/module-admin'

/**
 * What this package says, in English.
 *
 * The same keys `webx-ui/module-auth` ships as `lang/en/*.php`. Kept here so a card placed by
 * hand, with no WebX UI server behind it, still has words — and so a key never reaches the
 * screen when a translation is missing.
 */
export const authMessages: Record<string, Messages> = {
  card: {
    email: 'Email',
    password: 'Password',
    remember: 'Stay signed in',
    submit: 'Sign in',
    'caps-lock': 'Caps Lock is on.',
    reveal: 'Show the password',
    hide: 'Hide the password',
    throttled: 'Too many attempts. Try again in :seconds s.',
    failed: 'Those details do not match an account.',
  },
  admins: {
    title: 'Administrators',
    search: 'Search administrators',
    new: 'New administrator',
    edit: 'Edit administrator',
    // The same two, short enough for a button on a phone and for a dialog's heading.
    'new-short': 'Add',
    'edit-short': 'Edit',
    name: 'Name',
    email: 'Email',
    password: 'Password',
    'password-hint': 'At least 12 characters',
    'password-keep': 'Leave blank to keep the current one',
    avatar: 'Photo',
    roles: 'Roles',
    'no-roles': 'No roles',
    active: 'Active',
    super: 'Super administrator',
    'super-hint': 'Answers yes to everything, whatever the roles say',
    locale: 'Panel language',
    'locale-auto': 'Follow the browser',
    'last-login': 'Last signed in',
    save: 'Save',
    cancel: 'Cancel',
    delete: 'Delete',
    'delete-title': 'Delete :name?',
    'delete-text': 'They lose access at once. What they have written stays.',
    'all-roles': 'All roles',
    'any-state': 'Active and not',
    'only-active': 'Active only',
    'only-inactive': 'Switched off only',
    empty: 'Nobody matches that',
    'select-one': 'Choose an administrator',
    'select-many': 'Choose administrators',
    chosen: 'Chosen: :count',
    created: 'Administrator created',
    updated: 'Administrator saved',
    deleted: 'Administrator deleted',
  },
  errors: {
    unauthenticated: 'Unauthenticated.',
    inactive: 'This account is no longer active.',
    forbidden: 'This account may not do that.',
    'not-yourself': 'You cannot do that to your own account.',
    'last-super': 'There has to be one super administrator left.',
  },
  menu: {
    'sign-out': 'Sign out',
    language: 'Interface language',
  },
}
