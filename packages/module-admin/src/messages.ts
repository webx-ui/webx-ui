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
    'not-found-title': 'There is nothing at this address',
    'not-found-description':
      'The address may be mistyped, or the section it leads to is not installed on this site.',
    'not-found-home': 'Back to the start',
  },
  nav: {
    sections: 'Sections',
    menu: 'Menu',
    collapse: 'Collapse the menu',
    expand: 'Expand the menu',
    language: 'Language',
    // The one menu group the panel names itself; a module's own group is named by the module.
    system: 'System',
    'system-site': 'Site',
    'system-search': 'Search and checks',
    'system-access': 'Access',
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
    unpublish: 'Take off the site',
    'unpublish-title': 'Take it off the site?',
    'unpublish-text':
      'Visitors will no longer see it: its page will answer «not found» and leave the sitemap and search. Everything written stays in the panel, and «Publish» brings it back at the same address.',
    'keep-published': 'Keep it on the site',
    'discard-title': 'Discard changes?',
    'discard-text':
      'The published version comes back. Everything changed since publishing is lost and cannot be brought back.',
    'keep-changes': 'Keep',
    'publish-moves': 'The old address :old will lead to the new one.',
    unpublished: 'Taken off the site.',
  },
  // The page behind a '?'. Only the heading is the panel's: what the page says belongs to
  // whatever is being explained, and travels with that module's own words.
  // Two people — or a person and an agent — editing one record. The panel merges what does not
  // overlap without asking, and these are the words for the rest.
  editing: {
    merged: 'Merged with the changes by :who.',
    somebody: 'Somebody',
    'via-agent': ':name, through an agent',
    'via-import': ':name, through an import',
    'conflict-title': 'You and :who changed the same places',
    'conflict-text': 'Everything else was merged. Pick a version for each place below, then save.',
    base: 'Before',
    mine: 'Yours',
    theirs: 'Theirs',
    'take-theirs': 'Take theirs everywhere',
    removed: 'Removed',
    empty: 'Empty',
    'removed-mine': 'you removed this block, they edited it',
    'removed-theirs': 'they removed this block, you edited it',
    apply: 'Save my choices',
    'theirs-title': 'Take their version everywhere?',
    'theirs-text':
      'The form shows the record as it is now, and what you typed since your last save is gone.',
    cancel: 'Cancel',
    incoming: ':who changed :what · :when',
    pull: 'Pull in',
    others: 'Also open by :names',
    order: 'Order of blocks',
    block: 'Block',
    drafts: 'Drafts',
    'drafts-text':
      'Copies of the draft between publications. A draft somebody else’s save wrote over stays here too.',
    'drafts-empty': 'No copies of the draft yet.',
    autosave: 'Autosave',
    overwritten: 'Written over',
    restore: 'Restore',
    'restore-title': 'Put this draft back?',
    'restore-text':
      'It becomes the draft in the editor. The site does not change until you publish.',
    restored: 'The draft is back in the editor.',
    'source-panel': 'panel',
    'source-mcp': 'agent',
    'source-import': 'import',
    'and-more': ':what and more',
    and: 'and',
    event: ':who :what · :when',
    'event-published': 'published',
    'event-unpublished': 'took it off the site',
    'event-discarded': 'discarded the draft',
    'event-restored-version': 'restored version :number',
    'event-restored-draft': 'put an earlier draft back',
    'event-moved': 'moved it',
    'event-moved-to': 'moved it to :path',
    'event-trashed': 'moved it to the bin',
    'event-restored': 'took it out of the bin',
    'event-purged': 'deleted it for good',
    dismiss: 'Got it',
    'trashed-title': ':who moved this to the bin · :when',
    'trashed-anonymous': 'This is in the bin · :when',
    'trashed-text':
      'Nothing can be saved here until it is out of the bin. What you typed is still in the form.',
    'restore-bin': 'Restore',
    'restore-save': 'Restore and save my changes',
    'restore-failed': 'Could not take it out of the bin.',
    'out-of-bin': 'Out of the bin.',
    'purged-title': ':who deleted this for good · :when',
    'purged-anonymous': 'This was deleted for good',
    'purged-text':
      'There is nothing left to save it into. What you typed is still in the form — copy it before you leave.',
    'copy-text': 'Copy my text',
    copied: 'Copied. Paste it wherever you need it.',
    'copy-failed': 'Could not copy. Select the text in the form instead.',
    'purged-message': ':who deleted this for good at :when.',
    'blocked-trashed': 'This is in the bin',
    'blocked-purged': 'This was deleted for good',
    'publish-unseen-title': 'Publish changes you have not seen?',
    'publish-unseen-text': 'Publishing puts them on the site together with yours.',
    'publish-with': 'Publish with them',
    'review-first': 'Review first',
    'publish-moved':
      'Somebody changed the draft a moment ago. Look at their changes, then publish again.',
    'stale-publish':
      ':name changed the draft after you opened it. Look at the changes, then publish again.',
  },
  help: {
    title: 'Help',
  },
  // When something happened, said the way a person would. The month names and the order of
  // the parts come from `Intl` — only the words that no formatter knows are here.
  // The two words every list needs the moment it has filters: what the funnel is called,
  // and the way out of all of them at once. The names of the filters themselves belong to
  // whatever is being filtered, and travel with that module's own words. The checkboxes of a
  // list that can be selected say what they do to a screen reader, and do it here too.
  filters: {
    title: 'Filters',
    reset: 'Reset all',
    'select-row': 'Select row',
    'select-all': 'Select every row on this page',
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
    accent: 'Accent',
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
    source: 'HTML source',
    'source-loss': 'The editor does not keep this markup and will remove it:',
    'source-drop': 'Remove it',
    'source-keep': 'Keep editing',
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
  // Who changed a record and what (`wx-history`). The words of the fields are the modules' and
  // arrive with the rows; these are the frame's: the events, the doors, the run.
  history: {
    title: 'History',
    empty: 'Nothing has been changed here yet.',
    unsaved: 'The history begins with the first save.',
    'unknown-author': 'Nobody signed in',
    'event-created': 'Created',
    'event-updated': 'Changed',
    'event-deleted': 'Deleted',
    'event-restored': 'Restored',
    'event-published': 'Published',
    'event-unpublished': 'Unpublished',
    'event-run': 'Run',
    'source-panel': 'in the panel',
    'source-mcp': 'through an agent',
    'source-import': 'by an import',
    'source-bulk': 'by a bulk action',
    'source-api': 'through the API',
    'source-console': 'from the console',
    'empty-value': 'empty',
    long: 'changed, :from → :to characters',
    run: 'Part of a run',
    'run-title': 'Run',
    'run-rows': 'Rows: :count',
    'run-failed': 'Stopped: :message',
    search: 'Record id',
    more: 'Show more',
    // The three the server says and the browser only ever repeats.
    'no-type': 'That kind of record keeps no history.',
    missing: 'That run no longer exists.',
    forbidden: 'You may not read the history of this record.',
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
    'address-fallback':
      "No address in this language yet — this is the address in :locale, the site's main language.",
    'address-fallback-label': 'Address in the main language',
  },
  // The categories every module shares: the refusals the server says, and the words of the
  // shared screens under a module's own.
  categories: {
    'in-use': 'There are :count entries in it. Move them to another category first.',
    'slug-shape': 'Letters, digits and single hyphens between them.',
    new: 'New category',
    empty: 'No categories yet.',
    'empty-help': 'A category groups entries. One entry can be in several of them.',
    order: 'The order here is the order on the site',
    hidden: 'Hidden from the site',
    'no-address': 'No address in this language',
    count: 'Entries: :count',
    'show-items': 'Show its entries',
    edit: 'Edit',
    'open-on-site': 'Open on the site',
    delete: 'Delete',
    'delete-blocked': 'While it holds entries it cannot go — move them first.',
    'delete-title': 'Delete “:name”?',
    'delete-text': 'It goes to the bin and comes off the site, and its address is free again.',
    deleted: 'The category is in the bin.',
    cancel: 'Cancel',
    create: 'Create',
    save: 'Save',
    saved: 'Saved.',
    'save-failed': 'Not saved — look at the marked fields.',
    'reorder-failed': 'The new order was not saved.',
    'field-title': 'Name',
    'field-slug': 'Address',
    'address-moving':
      'The address is changing. The old one keeps working and leads to the new one.',
    untitled: 'Untitled',
    trail: 'Where you are',
    'leave-title': 'Leave without saving?',
    'leave-text': 'What was changed here since the last save will be lost.',
    leave: 'Leave',
    'field-main': 'Main',
    'field-add': 'Add a category',
    'field-remove': 'Take out of this category',
    'field-empty': 'In no category yet.',
    'field-none-left': 'Every category is already chosen.',
    'order-all': 'Drag to change the order on the site.',
    'order-category':
      'Drag to change the order inside this category. The rest of the list keeps its own.',
    'order-locked':
      'Clear the search and the filters to change the order — only a whole list, or one category, can be dragged.',
    unknown: 'One of the chosen categories is not there any more.',
  },
  // What a block shows from another section (`wx-collection`): the server's refusals, then the
  // words of the field that makes the choice. The name of the section is the server's, translated.
  collections: {
    'unknown-source': 'There is no “:source” on this site to show records from.',
    'unknown-category': 'One of the chosen categories no longer exists.',
    limit: 'How many to show: a whole number from 1 to :max, or empty for all.',
    flag: 'This switch takes yes or no.',
    'unknown-relation': 'Records of this section cannot be filtered by that.',
    'unknown-related': 'One of the chosen related records no longer exists.',
    'field-source': 'Shows records from “:source”.',
    'field-unavailable':
      'Records from “:source” cannot be chosen here: the section is not installed, or you have no access to it.',
    'field-categories': 'Categories',
    'field-all': 'All categories',
    'field-no-categories': 'No such category.',
    'field-limit': 'How many to show',
    'field-limit-all': 'All',
    'field-filter': 'A filter by category above the list',
    'field-markup': 'Markup for search engines',
    'field-markup-auto-on': 'By default it is on: the block shows every category.',
    'field-markup-auto-off':
      'By default it is off: search engines ask not to mark the same records up on several pages, and a chosen category usually stands on several.',
    'field-markup-reset': 'Back to the default',
  },
  // The records of another section a record points at (`wx-relations`), and the same choice as a
  // block's filter ("only related to" in `wx-collection`). Kept apart from `collections` because
  // the field is a field of its own, on any screen.
  relations: {
    'field-add': 'Add…',
    'field-searching': 'Searching…',
    'field-nothing': 'Nothing found.',
    'field-empty': 'Nothing chosen yet.',
    'field-remove': 'Remove',
    'field-drag': 'Drag to change the order',
    'field-hidden': 'Not on the site',
    'field-trashed': 'In the bin',
    'field-missing': 'Not found',
    'field-full': 'No more than :max can be chosen.',
    'field-forbidden': 'You cannot see these records, so the choice cannot be changed here.',
    'collection-related': 'Only related to',
    'collection-related-to': 'Only related to “:target”',
    'collection-related-type': 'Which section',
    'collection-related-any': 'Not narrowed: every record, related or not.',
    'collection-related-current': 'The record of the page it stands on',
    'collection-related-current-hint':
      'On the page of a record of “:target” the block shows what is related to it; on any other page it shows nothing.',
  },
}
