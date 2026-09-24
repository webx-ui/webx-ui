import type { Messages } from '@webx-ui/module-admin'

/**
 * The English this package speaks on its own, before the server's dictionary arrives, or without
 * one. The server ships the same lines in ten languages under `webx-reviews::` and overrides
 * these; `messages.test.ts` keeps the two sets of keys equal.
 */
export const reviewsMessages: Record<string, Messages> = {
  module: {
    group: 'Reviews',
    reviews: 'Reviews',
    categories: 'Categories',
  },
  review: {
    new: 'New review',
    untitled: 'Untitled',
    search: 'Search the reviews',
    empty: 'No reviews yet.',
    'empty-help': 'A review reaches the site in a reviews block, on any page.',
    'empty-search': 'Nothing matches that.',
    'empty-bin': 'The bin is empty.',
    all: 'All',
    trashed: 'Bin',
    'filter-category': 'Category',
    'any-category': 'Any category',
    'not-published': 'Not published',
    'visible-nowhere': 'Published, but seen in no language: it needs a text in one.',
    'no-text': 'No text yet',
    'no-rating': 'No rating',
    'seen-in': 'Seen in: :locales',
    choose: 'Choose a review',
    'choose-help': 'Or write a new one: the first line of the list.',
    save: 'Save',
    saved: 'The review is saved.',
    'save-failed': 'The review was not saved.',
    cancel: 'Cancel',
    delete: 'Delete',
    'delete-title': 'Delete the review by “:name”?',
    'delete-text':
      'It goes to the bin and leaves every block on the site. Restored, it comes back to its places.',
    deleted: 'The review is in the bin.',
    restore: 'Restore',
    restored: 'The review is back.',
    'reorder-failed': 'The new order was not saved.',
    'leave-title': 'Leave without saving?',
    'leave-text': 'What you wrote in this review is not on the server.',
    leave: 'Leave',
    back: 'Back to the list',
  },
  screen: {
    author: 'Who wrote it',
    photo: 'Photo',
    'photo-help': 'Without one, the site shows the initials of the name.',
    name: 'Name',
    'name-help': 'Not written in a language, it is shown as written in the default one.',
    'job-title': 'Job title',
    'job-title-help': 'Or the company, or the city: whatever is printed under the name.',
    'profile-url': 'Link to a profile',
    'profile-url-help':
      'An address starting with http:// or https://. The name becomes a link to it.',
    review: 'Review',
    text: 'Text',
    'text-help': 'A review is shown in a language only when it has a text in it.',
    rating: 'Rating',
    'rating-help': 'From one star to five. No stars, no rating shown.',
    'reviewed-on': 'Date',
    'reviewed-on-help':
      'Shown if the block prints it; it changes neither the order nor who sees the review.',
    settings: 'Settings',
    published: 'Published',
    'published-help':
      'On the site in every block that shows its categories, from the moment it is saved.',
    categories: 'Categories',
    'categories-help': 'What a block picks the review by, and the buttons of its filter.',
  },
  category: {
    'field-title': 'Title',
    visible: 'Shown on the site',
    'visible-help':
      'Hidden, it drops out of every filter; its reviews stay in the blocks that show them.',
    new: 'New category',
    empty: 'No categories yet.',
    'empty-help':
      'A category groups reviews: a block shows the ones you pick, and its filter has a button for each.',
    order: 'Drag to change the order of the filter buttons on the site.',
    hidden: 'Hidden from the site',
    reviews: 'Reviews: :count',
    'show-reviews': 'Show its reviews',
    'delete-blocked': 'This category still holds reviews. Move them first.',
    'delete-text': 'It goes to the bin and its button leaves every filter. Its reviews stay.',
    deleted: 'The category is in the bin.',
    saved: 'The category is saved.',
  },
}
