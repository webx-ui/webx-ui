import type { AdminModule } from '@webx-ui/module-admin'
import OrderPaymentNode from './OrderPaymentNode.vue'

export interface PaymentsOptions {
  /** Where the section lives inside the panel; its screens sit under it. */
  path?: string
}

/**
 * «Payments» as sections of the panel (spec §13): the methods, the journal of attempts and the
 * journal of the exchange with gateways.
 *
 * Three modules rather than one, because the navigation is one entry per module; the server puts
 * them in the `payments` group, which draws them under one heading. The panel includes them as
 * `...payments()` — the line `extra.webx.panel.register` of the composer package names. A section
 * whose server half is not installed never appears: the entry is built from the manifest.
 *
 * The first module brings the node of the tab «Payment» the server patches onto the order's card.
 */
export function payments(options: PaymentsOptions = {}): AdminModule[] {
  const path = options.path ?? '/payments'
  const section = (id: string, slug: string, name: string, page: () => Promise<unknown>) => ({
    id,
    path: slug === '' ? path : `${path}/${slug}`,
    routes: [
      {
        path: slug === '' ? path : `${path}/${slug}`,
        name,
        meta: { help: slug === '' ? 'payments.index' : `payments.${slug}` },
        component: page as never,
      },
    ],
  })

  return [
    {
      ...section('payments', '', 'webx.payments', () => import('./MethodsPage.vue')),
      types: {
        // The tab «Payment» of the order's card, patched onto `orders.show` by the server.
        'wx-payments-order': { component: OrderPaymentNode, kind: 'display' },
      },
    },
    section(
      'payments-attempts',
      'attempts',
      'webx.payments.attempts',
      () => import('./AttemptsPage.vue'),
    ),
    section(
      'payments-exchange',
      'exchange',
      'webx.payments.exchange',
      () => import('./ExchangePage.vue'),
    ),
  ]
}
