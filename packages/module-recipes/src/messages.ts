import type { Messages } from '@webx-ui/module-admin'

/**
 * The English this package speaks on its own, before the server's dictionary arrives, or without
 * one. The server ships the same lines in ten languages under `webx-recipes::` and overrides
 * these; `messages.test.ts` keeps the two sets of keys equal.
 */
export const recipesMessages: Record<string, Messages> = {
  module: {
    group: 'Recipes',
    recipes: 'Recipes',
    categories: 'Categories',
    nutrients: 'Rich in',
  },
  panel: {
    new: 'New recipe',
    'new-title': 'New recipe',
    'field-title': 'Title',
    create: 'Create',
    cancel: 'Cancel',
    search: 'Search by title or address',
    empty: 'No recipes yet.',
    'empty-help':
      'A recipe is a page of the site with a gallery, ingredients, a method and nutrition, filed under categories.',
    'empty-search': 'Nothing matches that.',
    'empty-bin': 'The bin is empty.',

    'status-draft': 'Draft',
    'status-published': 'Live',
    'status-modified': 'Live',
    'status-unpublished': 'Off the site',
    edits: 'edits',
    'no-address': 'No address in this language',
    'main-category': 'The main category: it goes in the breadcrumbs',
    minutes: ':count min',
    'hours-minutes': ':hours h :minutes min',
    hours: ':hours h',

    'view-all': 'All',
    'view-published': 'Live',
    'view-draft': 'Drafts',
    'view-unpublished': 'Off the site',
    bin: 'Bin',

    'filter-category': 'Category',
    'any-category': 'Any category',
    'filter-nutrient': 'Rich in',
    'any-nutrient': 'Rich in anything',
    'filter-service': 'Service',
    'any-service': 'Any service',

    'order-all': 'Drag to change the order on the site. It is the one order recipes have.',
    'order-locked':
      'Recipes have one order, the same in every category and on every page of the site. Clear the search and the filters to drag it.',

    open: 'Open',
    'open-on-site': 'Open on the site',
    'copy-address': 'Copy the address',
    'address-copied': 'The address is on the clipboard.',
    publish: 'Publish',
    unpublish: 'Take off the site',
    delete: 'Delete',
    restore: 'Restore',
    'delete-title': 'Delete “:title”?',
    'delete-text': 'It goes to the bin and comes off the site, and its address is free again.',
    deleted: 'The recipe is in the bin.',
    restored: 'The recipe is back.',
    published: 'The recipe is on the site.',
    'unpublished-done': 'The recipe is off the site.',
    'reorder-failed': 'The new order was not saved.',
  },
  recipe: {
    trail: 'Where this recipe sits',
    untitled: 'Untitled',
    save: 'Save',
    'save-failed': 'The recipe was not saved.',
    'publish-title': 'Put “:title” on the site?',
    'publish-text':
      'It answers at :address from the moment you do, for everyone — with its categories, services and similar recipes as they are chosen now.',
    'publish-nowhere': 'It has no address in this language yet, so nothing will answer.',
    preview: 'Preview',
    discard: 'Discard changes',
    'discard-title': 'Discard what is waiting?',
    'discard-text':
      'The recipe goes back to what the site is showing. What was written since is not listed anywhere.',
    discarded: 'The recipe is back to what is published.',
    'conflict-title': 'The recipe changed while you were editing',
    'conflict-mine': 'Keep mine',
    'conflict-theirs': 'Take the newer version',
    'conflict-theirs-title': 'Give up what you wrote?',
    'conflict-theirs-text':
      'The recipe is read again as it now is, and what you have typed since goes.',
    leave: 'Leave',
    'leave-title': 'Leave without saving?',
    'leave-text': 'The recipe could not be saved, and what you wrote is not on the server.',
    'live-since': 'On the site since :date',
    'address-moving':
      'The address is changing. The old one keeps working and leads to the new one.',
    'history-empty': 'This recipe has never been published.',
    version: '#:number',
    'version-live': 'On the site',
    'source-panel': 'From the panel',
    'source-mcp': 'By an agent',
    'source-import': 'Imported',
    'restore-title': 'Restore version :number?',
    'restore-text':
      'It becomes the draft. The site keeps showing what is published until you publish this.',
    'restore-version': 'Restore',
    'restored-version': 'Version :number is now the draft.',
  },
  category: {
    new: 'New category',
    empty: 'No categories yet.',
    'empty-help': 'A category is a section of the recipes with a page of its own.',
    order: 'Drag to change the order of the categories on the site.',
    hidden: 'Hidden from the site',
    'no-address': 'No address in this language',
    recipes: 'Recipes: :count',
    'show-recipes': 'Show its recipes',
    'delete-blocked': 'This category still holds recipes. Move them first.',
    'delete-text': 'It goes to the bin and its page comes off the site. Its recipes stay.',
    deleted: 'The category is in the bin.',
    saved: 'The category is saved.',
    'field-title': 'Title',
    'field-slug': 'Address',
    'address-moving':
      'The address is changing. The old one keeps working and leads to the new one.',
  },
  nutrient: {
    new: 'New nutrient',
    empty: 'Nothing here yet.',
    'empty-help':
      'What a recipe is rich in — iron, fibre, protein. A chip on the recipe and a filter of the catalogue, with no page of its own.',
    order: 'Drag to change the order of the chips and of the filter on the site.',
    hidden: 'Hidden from the site',
    recipes: 'Recipes: :count',
    'show-recipes': 'Show its recipes',
    'delete-blocked': 'Recipes are still marked with this. Take it off them first.',
    'delete-text': 'It goes to the bin and drops out of the filter. The recipes stay.',
    deleted: 'It is in the bin.',
    saved: 'Saved.',
    'field-title': 'Title',
  },
}
