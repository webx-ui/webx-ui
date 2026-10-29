import type { AdminModule } from '@webx-ui/module-admin'

export interface OrdersOptions {
  /** Where the orders live inside the panel. */
  path?: string
}

/**
 * «Orders» as a section of the panel (spec §11). For now one route and an empty list: the list,
 * the card, the statuses and the settings arrive with the `Orders` service they wrap. A section
 * whose server half is not installed never appears — the entry is built from the manifest.
 */
export function orders(options: OrdersOptions = {}): AdminModule {
  const path = options.path ?? '/orders'

  return {
    id: 'orders',
    path,
    routes: [{ path, name: 'webx.orders', component: () => import('./OrdersPage.vue') }],
  }
}
