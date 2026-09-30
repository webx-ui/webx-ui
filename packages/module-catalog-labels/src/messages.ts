import type { Messages } from '@webx-ui/module-admin'

/**
 * The English this package speaks on its own, before the server's dictionary arrives, or without
 * one. The server ships the same lines under `webx-catalog-labels::` and overrides these;
 * `messages.test.ts` keeps the two sets of keys equal.
 */
export const catalogLabelsMessages: Record<string, Messages> = {
  module: {
    title: 'Labels',
  },
  label: {
    title: 'Name',
    code: 'Code',
    'code-help':
      'Latin letters, digits and hyphens: the label in the filter address (label_sale) and in templates. Made from the name when left empty.',
    color: 'Tone',
    'color-help': 'The site paints the badge in this tone; the panel shows a tag of the same tone.',
    tones: {
      neutral: 'Neutral',
      primary: 'Primary',
      success: 'Success',
      warning: 'Warning',
      danger: 'Danger',
      info: 'Info',
    },
    badge: 'Badge on the card',
    'badge-help':
      'Off for a service label that only picks products, such as one for the newsletter.',
    filterable: 'In the filter',
    'filterable-help': 'Off, the label is not a choice of the catalogue filter.',
    new: 'New label',
    empty: 'No labels yet.',
    'empty-help':
      'A label is a badge on the card — top, sale, new — and a choice of the catalogue filter.',
    order: 'The order here is the order of the badges and of the filter',
    hidden: 'Not in the filter',
    count: 'Products: :count',
    'show-items': 'Show its products',
    'delete-blocked': 'While it is on products it cannot go — take it off them first.',
    'delete-text': 'It goes to the bin and leaves the filter.',
    deleted: 'The label is in the bin.',
    saved: 'The label is saved.',
  },
  product: {
    labels: 'Labels',
    'labels-help':
      'Badges on the card and choices of the filter; the order is the order of the list of labels.',
    'labels-add': 'Add a label',
    'labels-remove': 'Take the label off',
    'labels-empty': 'No labels.',
    'labels-none-left': 'Every label is already on it.',
  },
  bulk: {
    'add-label': 'Add a label',
    'remove-label': 'Remove a label',
    label: 'Label',
  },
}
