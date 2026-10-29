import type { Messages } from '@webx-ui/module-admin'

/**
 * The English this package speaks on its own, before the server's dictionary arrives, or without
 * one. The server ships the same lines in ten languages under `webx-orders::` and overrides these;
 * `messages.test.ts` keeps the two sets of keys equal.
 */
export const ordersMessages: Record<string, Messages> = {
  module: {
    orders: 'Orders',
    description: 'Orders placed on the site: what was bought, by whom, and where each one stands.',
    empty: 'No orders yet.',
    'empty-help': 'An order appears here as soon as it is placed on the site.',
    milestones: {
      placed: 'Placed',
      awaiting_payment: 'Awaiting payment',
      paid: 'Paid',
      received: 'Received',
      cancelled: 'Cancelled',
      refunded: 'Refunded',
    },
    colors: {
      neutral: 'Neutral',
      info: 'Info',
      success: 'Success',
      warning: 'Warning',
      danger: 'Danger',
      accent: 'Accent',
    },
  },
}
