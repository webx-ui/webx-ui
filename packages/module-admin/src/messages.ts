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
  // The three words on the theme switch. They belong to the panel rather than to whoever
  // places the switch: a core component ships English prop defaults and knows nothing about
  // a dictionary, so the panel is the one that has to say them.
  theme: {
    label: 'Theme',
    light: 'Light',
    dark: 'Dark',
    system: 'Follow the system',
  },
  // What a screen that edits one record offers whatever the record is: the way out of it, and
  // the way to rename it. A module that opens an editor should not be inventing these.
  editor: {
    back: 'Back',
    // The head's ··· on a screen that has no name to give the menu.
    more: 'More',
    rename: 'Rename',
    save: 'Save',
    // Said out loud to a screen reader while the little wheel turns, and after it stops.
    saving: 'Saving…',
    saved: 'Saved',
  },
  // The page behind a '?'. Only the heading is the panel's: what the page says belongs to
  // whatever is being explained, and travels with that module's own words.
  help: {
    title: 'Help',
  },
  // When something happened, said the way a person would. The month names and the order of
  // the parts come from `Intl` — only the words that no formatter knows are here.
  // The two words every list needs the moment it has filters: what the funnel is called,
  // and the way out of all of them at once. The names of the filters themselves belong to
  // whatever is being filtered, and travel with that module's own words.
  filters: {
    title: 'Filters',
    reset: 'Reset all',
  },
  dates: {
    today: 'today at :time',
    yesterday: 'yesterday at :time',
    // Not an empty cell and not a dash: a column that says nothing leaves a reader wondering
    // whether the panel failed to load it.
    never: 'never',
  },
  // The one line the panel says about the nightly database dump. It lives at the foot of the
  // settings screen, and the warning is the whole point of it: a backup whose breakage is
  // discovered on the day it was needed is not a backup.
  backup: {
    title: 'Last database snapshot:',
    stale: 'No database snapshot since :date. Check the schedule.',
    never: 'The database has never been backed up. Check the schedule.',
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
  // The toolbar of `wx-rich-text`. The editor is a component of the design system and carries
  // English defaults; these are what the panel calls the same buttons.
  'rich-text': {
    bold: 'Bold',
    italic: 'Italic',
    strike: 'Strikethrough',
    code: 'Inline code',
    h2: 'Heading 2',
    h3: 'Heading 3',
    h4: 'Heading 4',
    'bullet-list': 'Bulleted list',
    'ordered-list': 'Numbered list',
    blockquote: 'Quote',
    hr: 'Divider',
    link: 'Link',
    table: 'Table',
    image: 'Image',
    youtube: 'YouTube video',
    undo: 'Undo',
    redo: 'Redo',
    'row-below': 'Row below',
    'row-above': 'Row above',
    'column-after': 'Column after',
    'column-before': 'Column before',
    'delete-row': 'Delete row',
    'delete-column': 'Delete column',
    'merge-cells': 'Merge or split cells',
    'delete-table': 'Delete table',
    toolbar: 'Text formatting',
    'link-address': 'Link address',
    'youtube-address': 'YouTube URL',
    apply: 'Apply',
    cancel: 'Cancel',
    uploading: 'Uploading…',
  },
  // What one administrator writes on a record for the next one. Not the property of any
  // section: the same feed hangs off a submission, an order and a client, so the words are
  // the panel's own.
  notes: {
    title: 'Notes',
    placeholder: 'A note for whoever picks this up next…',
    add: 'Add a note',
    empty: 'No notes yet.',
    edit: 'Edit',
    delete: 'Delete',
    save: 'Save',
    cancel: 'Cancel',
    saved: 'Saved.',
    deleted: 'Deleted.',
    'delete-title': 'Delete this note?',
    'delete-text': 'It goes for everybody. This cannot be undone.',
    'unknown-author': 'A deleted account',
    // The three the server says and the browser only ever repeats.
    'no-type': 'That kind of record does not carry notes.',
    missing: 'That record no longer exists.',
    forbidden: 'You may not read the notes of this record.',
    'not-yours': 'A note is edited by whoever wrote it.',
  },
  // Where a link goes. The sections of the picker are named by whichever modules registered them;
  // these are the words around them, plus the six the server says when a link will not do.
  links: {
    'target-entity': 'This site',
    'target-url': 'An address',
    'target-none': 'Nowhere',
    section: 'Section',
    search: 'Start typing a name',
    searching: 'Searching…',
    empty: 'Nothing found',
    clear: 'Clear the link',
    unavailable: 'Not on the site yet',
    missing: 'What this pointed at is gone',
    'url-label': 'Address',
    'url-placeholder': '/account or https://example.com',
    hash: 'Anchor',
    'hash-placeholder': 'section-on-the-page',
    'url-routes': 'Addresses of this site',
    'new-tab': 'Open in a new tab',
    rel: 'Relationship',
    'rel-nofollow': 'Pass no link weight',
    'rel-sponsored': 'Paid placement',
    'rel-ugc': 'Written by a visitor',
    shape: 'This field takes a link.',
    target: 'This field does not know that kind of target.',
    'no-source': 'Nothing here can link to that.',
    'no-entity': 'Choose what to link to.',
    'url-length': 'An address may be at most :max characters long.',
    'url-scheme': 'An address has to be a path or start with http, https, mailto or tel.',
    'hash-length': 'An anchor may be at most :max characters long.',
    'hash-shape': 'An anchor is a name on the page: no spaces in it.',
    'not-localized': 'A link is the same in every language and cannot be translated.',
  },
}
