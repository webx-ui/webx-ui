import type { Messages } from '@webx-ui/module-admin'

/**
 * The English this package speaks on its own, before the server's dictionary arrives, or without
 * one. The server ships the same lines under `webx-catalog-feeds::` and overrides these;
 * `messages.test.ts` keeps the two sets of keys equal.
 */
export const catalogFeedsMessages: Record<string, Messages> = {
  module: {
    title: 'Feeds',
    feed: 'Feed',
  },
  feed: {
    name: 'Name',
    format: 'Format',
    locale: 'Language',
    currency: 'Currency',
    head: 'Header',
    item: 'Product',
    foot: 'Footer',
    separator: 'Separator',
    filter: 'Selection',
    stock_map: 'Stock mapping',
    taxonomy: 'Category list',
    custom_labels: 'Custom labels',
    sources: 'Sources',
    options: 'Options',
    schedule: 'Schedule',
    schedule_at: 'Build time',
    rebuild_on_change: 'Rebuild after changes',
    is_active: 'Active',
  },
  panel: {
    products: 'Products',
    built: 'Built',
    status: 'Status',
    empty: 'No feeds yet.',
    'status-queued': 'Queued',
    'status-running': 'Building',
    'status-done': 'Done',
    'status-failed': 'Failed',
    'status-none': 'Not built',
  },
}
