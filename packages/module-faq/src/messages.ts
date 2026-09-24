import type { Messages } from '@webx-ui/module-admin'

/**
 * The English this package speaks on its own, before the server's dictionary arrives, or without
 * one. The server ships the same lines in ten languages under `webx-faq::` and overrides these;
 * `messages.test.ts` keeps the two sets of keys equal.
 */
export const faqMessages: Record<string, Messages> = {
  module: {
    group: 'FAQ',
    questions: 'Questions',
    categories: 'Categories',
  },
  question: {
    new: 'New question',
    untitled: 'Untitled',
    search: 'Search the questions',
    empty: 'No questions yet.',
    'empty-help': 'A question reaches the site in a FAQ block, on any page.',
    'empty-search': 'Nothing matches that.',
    'empty-bin': 'The bin is empty.',
    all: 'All',
    bin: 'Bin',
    'filter-category': 'Category',
    'any-category': 'Any category',
    'not-published': 'Not published',
    'seen-nowhere': 'Not seen in any language: it needs a question and an answer in one.',
    choose: 'Choose a question',
    'choose-help': 'Or write a new one: the first line of the list.',
    save: 'Save',
    saved: 'The question is saved.',
    'save-failed': 'The question was not saved.',
    cancel: 'Cancel',
    delete: 'Delete',
    'delete-title': 'Delete “:title”?',
    'delete-text':
      'It goes to the bin and leaves every block on the site. Restored, it comes back with the same link.',
    deleted: 'The question is in the bin.',
    restore: 'Restore',
    restored: 'The question is back.',
    'copy-link': 'Copy the link',
    'link-copied': 'The link is on the clipboard.',
    'anchor-later': 'Made from the question when it is first saved.',
    'reorder-failed': 'The new order was not saved.',
    'leave-title': 'Leave without saving?',
    'leave-text': 'What you wrote in this question is not on the server.',
    leave: 'Leave',
    back: 'Back to the list',
  },
  screen: {
    question: 'Question',
    answer: 'Answer',
    'answer-help':
      'A question is shown in a language only when it has both the question and the answer in it.',
    settings: 'Settings',
    published: 'Published',
    'published-help':
      'On the site in every block that shows its categories, from the moment it is saved.',
    categories: 'Categories',
    'categories-help': 'What a block picks the question by, and the buttons of its filter.',
    anchor: 'Link',
    'anchor-help': 'Made once from the question and never changed, so links to it keep working.',
  },
  category: {
    'field-title': 'Title',
    visible: 'Shown on the site',
    'visible-help':
      'Hidden, it drops out of every filter; its questions stay in the blocks that show them.',
    new: 'New category',
    empty: 'No categories yet.',
    'empty-help':
      'A category groups questions: a block shows the ones you pick, and its filter has a button for each.',
    order: 'Drag to change the order of the filter buttons on the site.',
    hidden: 'Hidden from the site',
    questions: 'Questions: :count',
    'show-questions': 'Show its questions',
    'delete-blocked': 'This category still holds questions. Move them first.',
    'delete-text': 'It goes to the bin and its button leaves every filter. Its questions stay.',
    deleted: 'The category is in the bin.',
    saved: 'The category is saved.',
  },
}
