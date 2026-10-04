import type { Messages } from '@webx-ui/module-admin'

/**
 * What this package says, in English — the same keys `webx-ui/module-seo` ships as
 * `lang/en/*.php`, so a key never reaches the screen when a translation has not arrived yet.
 *
 * The server's dictionary wins over these. There is no second dictionary inside this package:
 * a module translated into ten languages with an English dialog in the middle of it is worse
 * than one that is not translated at all.
 *
 * Placeholders are Laravel's, `:like-this` — the same line is read from a PHP file and from
 * here, and only one of the two spellings can be right.
 */
export const seoMessages: Record<string, Messages> = {
  module: {
    title: 'SEO',
  },

  page: {
    rules: 'Rules',
    redirects: 'Redirects',
    test: 'Check an address',
    'filter-kind': 'Kind',

    'new-rule': 'New rule',
    'new-redirect': 'New redirect',
    'search-rules': 'Search by address',
    'search-redirects': 'Search by address or destination',
    empty: 'Nothing here yet.',

    address: 'Address',
    kind: 'Kind',
    priority: 'Priority',
    state: 'State',
    active: 'Active',
    inactive: 'Off',
    title: 'Title',
    target: 'Destination',
    status: 'Code',
    hits: 'Hits',
    'last-hit': 'Last used',
    loop: 'Points at itself',

    'any-kind': 'Any kind',
    exact: 'Exact',
    mask: 'Mask',
    regex: 'Regular expression',

    rule: 'Rule',
    redirect: 'Redirect',
    save: 'Save',
    cancel: 'Cancel',
    delete: 'Delete',
    saved: 'Saved.',
    deleted: 'Deleted.',
    failed: 'Some values were not accepted. Check the highlighted fields.',
    'delete-title': 'Delete :pattern?',
    'delete-text': 'This cannot be undone.',

    'address-help':
      'A path with its query string. In a mask, * is one segment and ** is any number of them; a regular expression is stored as written, delimiters and all.',
    'target-help':
      'A path on this site, or a full address elsewhere. $1 puts back what a mask caught.',
    'priority-help': 'Among rules of the same kind, the higher number wins.',

    'test-placeholder': '/catalog/shoes?page=2',
    'test-run': 'Check',
    'test-empty': 'Type an address to see what the site will say about it.',
    'test-matched': 'Matched rule',
    'test-none': 'No rule matches this address.',
    'test-redirected': 'This address is redirected to :target with a :status.',
    'test-result': 'What the page will say',
    'test-chain': 'Where each part came from',

    automatic: 'Automatic',
    'search-aliases': 'Search by address or destination',
    'aliases-empty': 'Nothing has moved yet.',
    'aliases-help':
      'The site writes these itself: renaming or moving a page leaves its old address behind answering 301, so nothing that linked to it dies. They cannot be edited here — a redirect of your own is tried first and wins.',
    language: 'Language',
    'moved-at': 'Moved',
    gone: 'Leads nowhere',
    occupied:
      'A page of the site answers at :path. A redirect is tried before it, so the page stops being reachable there.',
    'occupied-alias':
      'This address already leads to :target — what a move left behind. A redirect written here is tried first.',

    sitemap: 'Sitemap',
    'sitemap-built': 'Built',
    'sitemap-total': 'Addresses: :count',
    'sitemap-excluded': 'Left out — noindex: :noindex, another canonical: :canonical',
    'sitemap-empty': 'Nothing is in it: no address is published and open to the index.',
    'sitemap-off': 'The sitemap is turned off on this site.',
    'sitemap-rebuild': 'Rebuild',
    'sitemap-rebuilt': 'The sitemap has been rebuilt.',
    'test-sitemap': 'Sitemap',
    'test-sitemap-in': 'In the sitemap.',
    'test-sitemap-out': 'Not in the sitemap: :reason.',
    'sitemap-reason-disabled': 'the sitemap is turned off',
    'sitemap-reason-unknown': 'the site has no page of its own here',
    'sitemap-reason-alias': 'this is an old address that redirects',
    'sitemap-reason-hidden': 'the page is not published',
    'sitemap-reason-noindex': 'the page says noindex',
    'sitemap-reason-canonical': 'the page names another address as canonical',
  },

  card: {
    'section-page': 'The page',
    'section-share': 'Sharing',
    'section-advanced': 'Advanced',

    title: 'Title',
    h1: 'Heading',
    description: 'Description',
    keywords: 'Keywords',
    canonical: 'Canonical address',
    'canonical-help': 'The address this page should be indexed under, when it is not its own.',

    robots: 'Indexing',
    'robots-kept': 'Also kept, as written: :directives',
    noindex: 'Keep out of the index',
    nofollow: 'Do not follow the links',
    noarchive: 'No cached copy',
    nosnippet: 'No snippet',
    noimageindex: 'Do not index the images',

    'og-title': 'Share title',
    'og-description': 'Share description',
    'og-image': 'Share image',
    'og-help': 'Left empty, a share preview uses the title and description above.',

    'json-ld': 'Structured data',
    'json-ld-help':
      'A JSON-LD object, or a list of them. What the site says about itself comes from the settings; this is for a page that needs markup of its own.',
    'json-ld-invalid': 'This is not valid JSON.',

    preview: 'In search results',
    'share-preview': 'When shared',
    'share-auto': 'The site fills this in — the record’s own picture, or the default one',
  },
  links: {
    // What the server says about a row or a link; the panel shows it as it comes.
    empty: 'Donor, acceptor and anchor are all required.',
    'anchor-long': 'The anchor is longer than 255 characters.',
    'foreign-host': 'The address is on another site; interlinking is internal.',
    self: 'A page cannot link to itself.',
    duplicate: 'This page is already linked in the block.',
    'not-found': 'Nothing on the site answers this address.',
    redirected: ':from redirects; its target :to is used instead.',
    'donor-taken': 'This page already has an interlinking block.',
    unreadable: 'The file could not be read as CSV or XLSX.',

    tab: 'Interlinking',
    new: 'New donor',
    import: 'Import',
    'export-csv': 'Export as CSV',
    'export-xlsx': 'Export as XLSX',
    'heading-bulk': 'Set a heading',
    search: 'Search by donor address or anchor',
    'list-empty': 'No donors yet.',
    off: 'Interlinking is turned off on this site.',
    donor: 'Donor',
    'donor-help': 'The page the links are printed on — an address of this site.',
    heading: 'Heading',
    'heading-help': 'Left empty, the block takes the heading from the SEO settings.',
    'heading-default': 'From the settings',
    links: 'Links',
    broken: 'Broken',
    'with-broken': 'Only with broken links',
    gone: 'Leads nowhere',
    'broken-help': 'Nothing on the site answers this address; the link is not printed.',
    was: 'Written as :url',
    acceptor: 'Acceptor',
    anchor: 'Anchor',
    'add-link': 'Add a link',
    'remove-link': 'Remove the link',
    'no-links': 'No links yet.',
    drag: 'Move',
    block: 'Interlinking block',
    'saved-warnings': 'Saved. Some addresses were replaced on the way:',
    done: 'Done',
    'delete-title': 'Delete the block of :donor?',
    'import-title': 'Import interlinking',
    'import-help':
      'A CSV or XLSX file with the columns donor, acceptor, anchor and an optional heading. Rows are grouped by donor and keep the order of the file.',
    file: 'File',
    'choose-file': 'Choose a file',
    mode: 'Mode',
    'mode-replace': 'Replace the blocks of the donors in the file',
    'mode-append': 'Add to the blocks that exist',
    preview: 'Preview',
    apply: 'Apply',
    imported: 'Imported. Donors: :count',
    'result-donors': 'Donors',
    'result-links': 'Links',
    'result-created': 'New',
    'result-replaced': 'Replaced',
    'result-appended': 'Added to',
    'result-errors': 'Errors',
    problems: 'Rows to look at',
    line: 'Line :line',
    error: 'Error',
    warning: 'Warning',
    'no-problems': 'Every row can be imported.',
    nothing: 'Nothing in this file can be imported.',
    'heading-title': 'Heading for many donors',
    'scope-selected': 'Ticked donors: :count',
    prefix: 'Address starts with',
    'prefix-help':
      'Every donor at this address and under it: /catalog/tech/ takes /catalog/tech/phones but not /catalog/tech-2.',
    'heading-count': 'Donors that will get it: :count',
    'heading-none': 'No donor matches.',
    'heading-applied': 'Heading set. Donors: :count',
    more: 'More: :count',
    selected: 'Selected: :count',
  },
}
