import type { Messages } from '@webx-ui/module-admin'

/**
 * The English this package speaks on its own, before the server's dictionary arrives, or without
 * one. The server ships the same lines in ten languages under `webx-press::` and overrides these;
 * `messages.test.ts` keeps the two sets of keys equal.
 *
 * `kinds` are the four of the default config (decision 3). A kind a site adds brings its word in
 * `lang/vendor/webx-press` — the server labels the options of the field itself, so a kind missing
 * here is only missing from a panel that runs with no server at all.
 */
export const pressMessages: Record<string, Messages> = {
  module: {
    title: 'Press',
  },
  outlet: {
    new: 'New outlet',
    untitled: 'Untitled',
    search: 'Search the outlets',
    empty: 'No outlets yet.',
    'empty-help':
      'An outlet is a magazine, a paper or a site that wrote about you; its articles are added on its form.',
    'empty-search': 'Nothing matches that.',
    'empty-bin': 'The bin is empty.',
    all: 'All',
    trashed: 'Bin',
    'not-published': 'Not published',
    'visible-nowhere': 'Published, but seen in no language: none of its articles has a title yet.',
    'articles-count': 'Articles: :count',
    'no-articles': 'No articles yet',
    featured: 'In the logo strip',
    'seen-in': 'Seen in: :locales',
    choose: 'Choose an outlet',
    'choose-help': 'Or add a new one: the first line of the list.',
    save: 'Save',
    saved: 'The outlet is saved.',
    'save-failed': 'The outlet was not saved.',
    cancel: 'Cancel',
    delete: 'Delete',
    'delete-title': 'Delete “:name”?',
    'delete-text':
      'It goes to the bin with its articles and leaves the site and every block. Restored, it comes back with them.',
    deleted: 'The outlet is in the bin.',
    restore: 'Restore',
    restored: 'The outlet is back.',
    'reorder-failed': 'The new order was not saved.',
    'leave-title': 'Leave without saving?',
    'leave-text': 'What you wrote in this outlet is not on the server.',
    leave: 'Leave',
    back: 'Back to the list',
    open: 'Open on the site',
    'address-moving': 'The page of the outlet moves to the new address when you save.',
  },
  screen: {
    general: 'General',
    articles: 'Articles',
    seo: 'SEO',
    'seo-empty': 'The SEO of the outlet page is set here when the SEO module is installed.',
    outlet: 'Outlet',
    logo: 'Logo',
    'logo-help': 'Without one, the site prints the name as text.',
    title: 'Name',
    'title-help': 'Not written in a language, it is shown as written in another one: it is a name.',
    slug: 'Address',
    'website-url': 'Website',
    'website-url-help':
      'The address of the outlet itself, starting with http:// or https://. The page of the outlet links to it.',
    summary: 'Description',
    'summary-help': 'A sentence or two for the page of the outlet.',
    settings: 'Settings',
    published: 'Published',
    'published-help':
      'On the site from the moment it is saved — in the languages its articles have a title in.',
    featured: 'In the logo strip',
    'featured-help': 'A logo strip set to “featured only” shows the outlets marked here.',
    'articles-list': 'Articles',
    'articles-help':
      'In the order they are shown on the page of the outlet. Drag a row by its grip to move it.',
    'article-title': 'Title',
    'article-title-help': 'An article is shown in a language only when it has a title in it.',
    excerpt: 'Summary',
    kind: 'Kind',
    'kind-none': 'No kind',
    'published-on': 'Date',
    'date-precision': 'Shown as',
    'date-precision-day': 'Day',
    'date-precision-month': 'Month',
    'date-precision-year': 'Year',
    url: 'Link',
    'url-help':
      'The article on the site of the outlet. A link, a PDF, or both: then the link leads and the PDF is offered beside it.',
    file: 'PDF',
    'file-help': 'A PDF from the library — a scan of the page, say.',
    hidden: 'Do not show',
    'hidden-help': 'Kept with the outlet, and left off the site.',
  },
  kinds: {
    mention: 'Mention',
    interview: 'Interview',
    expert_comment: 'Expert comment',
    authored: 'Authored article',
  },
}
