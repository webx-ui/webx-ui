import type { Messages } from '@webx-ui/module-admin'

/**
 * The English this package speaks on its own, before the server's dictionary arrives, or without
 * one. The server ships the same lines in ten languages under `webx-press::` and overrides these;
 * `messages.test.ts` keeps the two sets of keys equal.
 *
 * `screen` is what the described screen `press.outlet-form` is labelled with: the server sends the
 * labels as `trans::webx-press::screen.*`, and these are what they say when no dictionary came.
 *
 * `kinds` are the four of the default config (decision 3). A kind a site adds brings its word in
 * `lang/vendor/webx-press` — the server labels the options of the field itself, so a kind missing
 * here is only missing from a panel that runs with no server at all.
 */
export const pressMessages: Record<string, Messages> = {
  module: {
    press: 'Press',
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
    outlet: 'Outlet',
    logo: 'Logo',
    'logo-help': 'Without a logo the site prints the name.',
    title: 'Name',
    'title-help': 'Where it is not written in a language, the name from another one is shown.',
    slug: 'Address',
    'slug-help':
      "The outlet's page. A language with articles and no address of its own takes this one.",
    'website-url': 'Website',
    'website-url-help': 'An address that starts with http:// or https://.',
    summary: 'About the outlet',
    'summary-help':
      'A few words under the name on its page, and its description for search engines.',
    settings: 'Settings',
    published: 'Published',
    'published-help': 'On the site at once — in the languages it has articles in.',
    featured: 'In the strip of logos',
    'featured-help': 'Shown by the logos block set to the marked outlets only.',
    articles: 'Articles',
    'articles-help': "In the order they are shown on the outlet's page. Saved with the outlet.",
    'article-label': '#:number · :title',
    'article-untitled': 'Untitled',
    'article-title': 'Title',
    'article-title-help': 'An article is shown in a language only when it has a title in it.',
    excerpt: 'Excerpt',
    kind: 'Kind',
    'kind-none': 'No kind',
    'published-on': 'Date',
    'date-precision': 'Known to',
    'date-precision-help': 'How much of the date is printed: the day, the month, or the year only.',
    precision: {
      day: 'Day',
      month: 'Month',
      year: 'Year',
    },
    url: 'Link',
    'url-help': "The article on the outlet's site. Without it the title leads to the PDF.",
    file: 'PDF',
    'file-help': 'A PDF from the library — a scan of the page, say.',
    hidden: 'Do not show',
    'hidden-help': 'Kept here, and shown nowhere on the site.',
    seo: 'SEO',
    'seo-empty': 'The SEO card appears here when module-seo is installed.',
  },
  kinds: {
    mention: 'Mention',
    interview: 'Interview',
    expert_comment: 'Expert comment',
    authored: 'Authored article',
  },
}
