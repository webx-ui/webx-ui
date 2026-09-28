import type { Messages } from '@webx-ui/module-admin'

/**
 * The English this package speaks on its own, before the server's dictionary arrives, or without
 * one. The server ships the same lines in ten languages under `webx-tariffs::` and overrides
 * these; `messages.test.ts` keeps the two sets of keys equal.
 *
 * Categories in the code, groups in the words (decision 14): the `category` group holds what the
 * shared category screens say, and every one of its lines says "group".
 */
export const tariffsMessages: Record<string, Messages> = {
  module: {
    group: 'Tariffs',
    tariffs: 'Tariffs',
    groups: 'Groups',
  },
  tariff: {
    new: 'New tariff',
    untitled: 'Untitled',
    search: 'Search the tariffs',
    empty: 'No tariffs yet.',
    'empty-help': 'A tariff reaches the site in a tariffs block, on any page.',
    'empty-search': 'Nothing matches that.',
    'empty-bin': 'The bin is empty.',
    all: 'All',
    trashed: 'Bin',
    'filter-category': 'Group',
    'any-category': 'Any group',
    'not-published': 'Not published',
    featured: 'Recommended',
    'no-price': 'No price',
    order: 'Drag to change the order of the cards on the site.',
    choose: 'Choose a tariff',
    'choose-help': 'Or add a new one: the first line of the list.',
    save: 'Save',
    saved: 'The tariff is saved.',
    'save-failed': 'The tariff was not saved.',
    cancel: 'Cancel',
    delete: 'Delete',
    'delete-title': 'Delete the tariff “:name”?',
    'delete-text':
      'It goes to the bin and leaves every block on the site. Restored, it comes back to its places.',
    deleted: 'The tariff is in the bin.',
    restore: 'Restore',
    restored: 'The tariff is back.',
    'reorder-failed': 'The new order was not saved.',
    'order-group':
      'Drag to change the order inside this group. The rest of the list keeps its own.',
    'leave-title': 'Leave without saving?',
    'leave-text': 'What you changed in this tariff is not on the server.',
    leave: 'Leave',
    back: 'Back to the list',
  },
  screen: {
    tariff: 'Tariff',
    name: 'Name',
    'name-help':
      'Required in the default language. Not written in another one, it is shown as written in the default one.',
    badge: 'Badge',
    'badge-help': 'A short line over the name: “30 HOURS / 25$”.',
    featured: 'Recommended',
    'featured-help': 'The card stands out among the others.',
    pricing: 'Price',
    price: 'Price',
    'price-help':
      'A number, up to two digits after the point. Empty — the words below are shown instead.',
    currency: 'Currency',
    'currency-help': 'The list comes from the site’s settings.',
    'currency-placeholder': 'Choose a currency',
    period: 'Period',
    'period-help': 'Printed after the price: “/mo”, “a year”.',
    'price-text': 'Price in words',
    'price-text-help': 'Printed when there is no price: “On request”, “Custom”.',
    features: 'What is included',
    'features-help': 'A line not written in a language is left out of the list in that language.',
    'features-add': 'Add a line',
    'features-empty': 'No lines yet.',
    feature: 'Line',
    about: 'Description',
    description: 'Description',
    'description-help': 'Shown only in the languages it is written in.',
    button: 'Button',
    'button-label': 'Label',
    'button-label-help': 'Without a label in a language, the card has no button in it.',
    'button-link': 'Link',
    'button-link-help':
      'A page of the site or an address. A draft or a page in the bin hides the button.',
    'button-variant': 'Look',
    'button-variant-placeholder': 'Choose a look',
    settings: 'Settings',
    categories: 'Groups',
    'categories-help': 'What a block picks the tariff by, and the tabs of a page of prices.',
    'categories-add': 'Add to a group',
    'categories-empty': 'In no group yet.',
    services: 'Services',
    'services-help':
      'The services this tariff is for: a tariffs block on a service’s page can show only its own.',
    published: 'Published',
    'published-help':
      'On the site in every block that shows its groups, from the moment it is saved.',
  },
  category: {
    'field-title': 'Title',
    visible: 'Shown on the site',
    'visible-help':
      'Hidden, it drops out of every page of prices; its tariffs stay in the blocks that show them.',
    new: 'New group',
    empty: 'No groups yet.',
    'empty-help':
      'A group gathers tariffs — for individuals, for business: a block shows the ones you pick, and a page of prices can show each group as a tab.',
    order: 'Drag to change the order of the groups on the site.',
    hidden: 'Hidden from the site',
    tariffs: 'Tariffs: :count',
    'show-tariffs': 'Show its tariffs',
    'delete-blocked': 'This group still holds tariffs. Move them first.',
    'delete-text': 'It goes to the bin and leaves every page of prices. Its tariffs stay.',
    deleted: 'The group is in the bin.',
    saved: 'The group is saved.',
  },
}
