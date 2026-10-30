import type { AdminModule } from '@webx-ui/module-admin'
import OrderItemsNode from './OrderItemsNode.vue'
import NumberSampleNode from './NumberSampleNode.vue'
import OrderLayoutNode from './OrderLayoutNode.vue'
import OrderStatusNode from './OrderStatusNode.vue'
import OrderSummaryNode from './OrderSummaryNode.vue'
import OrderTimelineNode from './OrderTimelineNode.vue'
import OrderVisitorNode from './OrderVisitorNode.vue'
import VisitorCountNode from './VisitorCountNode.vue'
import VisitorOrdersNode from './VisitorOrdersNode.vue'

export interface OrdersOptions {
  /** Where the orders live inside the panel. */
  path?: string
}

/**
 * «Orders» as a section of the panel (spec §11): the list and the card of an order, and under them
 * the statuses and the settings of the section (§11.4), which the list links to for whoever holds
 * `orders.statuses`, and «New order» (§29.2) for whoever holds `orders.manage`. A section whose
 * server half is not installed never appears — the entry is built from the manifest.
 *
 * The node types are the parts of `orders.show` a field cannot be: the two columns of the card, the strip of
 * the status and the marks (`header-actions`, a container a neighbour adds its buttons to), the lines, the
 * timeline and the totals. They read the open order from the page, not from the model. The trial
 * number of `orders.settings` reads the settings page the same way.
 *
 * With the visitors installed the server patches three more onto screens (§13.2, §13.3): the account
 * of the order on its tab «Buyer» (`wx-orders-visitor`), and on the visitor's card `users.form` the
 * tab «Orders» (`wx-orders-list`) and the count in its side card (`wx-orders-count`).
 */
export function orders(options: OrdersOptions = {}): AdminModule {
  const path = options.path ?? '/orders'
  const props = { base: path }

  return {
    id: 'orders',
    path,
    routes: [
      { path, name: 'webx.orders', component: () => import('./OrdersPage.vue'), props },
      {
        path: `${path}/statuses`,
        name: 'webx.orders.statuses',
        component: () => import('./StatusesPage.vue'),
        props,
      },
      {
        path: `${path}/settings`,
        name: 'webx.orders.settings',
        component: () => import('./SettingsPage.vue'),
        props,
      },
      {
        path: `${path}/new`,
        name: 'webx.orders.new',
        component: () => import('./NewOrderPage.vue'),
        props,
      },
      {
        path: `${path}/:id(\\d+)`,
        name: 'webx.orders.show',
        component: () => import('./OrderPage.vue'),
        props,
      },
    ],
    types: {
      'wx-orders-layout': { component: OrderLayoutNode, kind: 'layout' },
      'wx-orders-status': { component: OrderStatusNode, kind: 'layout' },
      'wx-orders-items': { component: OrderItemsNode, kind: 'display' },
      'wx-orders-timeline': { component: OrderTimelineNode, kind: 'display' },
      'wx-orders-summary': { component: OrderSummaryNode, kind: 'display' },
      'wx-orders-number-sample': { component: NumberSampleNode, kind: 'display' },
      'wx-orders-visitor': { component: OrderVisitorNode, kind: 'display' },
      'wx-orders-list': { component: VisitorOrdersNode, kind: 'display' },
      'wx-orders-count': { component: VisitorCountNode, kind: 'display' },
    },
  }
}
