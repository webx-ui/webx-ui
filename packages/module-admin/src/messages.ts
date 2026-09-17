import type { Messages } from './i18n'

/**
 * The panel's own words, in English.
 *
 * The same keys `webx-ui/module-admin` ships as `lang/en/*.php`, kept here so the package works with
 * no server behind it. Anything the server sends wins; this is the floor, not the source of
 * truth. Translations belong in the Composer package, where one file serves both halves.
 */
export const adminMessages: Record<string, Messages> = {
  shell: {
    loading: 'Loading the panel…',
    'error-title': 'The panel could not start',
    retry: 'Try again',
    'empty-title': 'Nothing is installed yet',
    'empty-description': 'This panel has no modules. Install one and it will appear here.',
  },
  nav: {
    sections: 'Sections',
    menu: 'Menu',
    collapse: 'Collapse the menu',
    expand: 'Expand the menu',
    language: 'Language',
    // The one menu group the panel names itself; a module's own group is named by the module.
    system: 'System',
  },
  // When something happened, said the way a person would. The month names and the order of
  // the parts come from `Intl` — only the words that no formatter knows are here.
  dates: {
    today: 'today at :time',
    yesterday: 'yesterday at :time',
    // Not an empty cell and not a dash: a column that says nothing leaves a reader wondering
    // whether the panel failed to load it.
    never: 'never',
  },
  // How a request fails, in the panel's words rather than the server's (§13.3). `errors.ts`
  // decides which of these a status gets.
  errors: {
    'signed-out': 'You are signed out. Sign in again and try once more.',
    forbidden: 'You are not allowed to do that.',
    gone: 'It is not there any more — somebody may have deleted it.',
    conflict: 'Somebody changed this while you were working on it.',
    throttled: 'Too many attempts. Try again in a moment.',
    'throttled-in': 'Too many attempts. Try again in :seconds seconds.',
    server: 'The server could not do that. Try again in a moment.',
    offline: 'The server did not answer. Check the connection and try again.',
    unknown: 'That did not work.',
  },
}
