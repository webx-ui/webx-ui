import type { Messages } from './i18n'

/**
 * The panel's own words, in English.
 *
 * The same keys `webx-ui/admin` ships as `lang/en/*.php`, kept here so the package works with
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
    language: 'Language',
  },
}
