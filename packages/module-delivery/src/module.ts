import type { AdminModule } from '@webx-ui/module-admin'
import CountryField from './fields/CountryField.vue'
import LocalitiesField from './fields/LocalitiesField.vue'
import LocalityField from './fields/LocalityField.vue'
import RegionField from './fields/RegionField.vue'
import ScheduleField from './fields/ScheduleField.vue'

export interface DeliveryOptions {
  /** Where the section lives inside the panel; its screens sit under it. */
  path?: string
}

/**
 * «Delivery» as sections of the panel (spec §14.2): the methods, the shop's own points, the
 * carriers, the geography, the popular towns and the binding queue.
 *
 * Six modules rather than one, because the navigation is one entry per module; the server puts
 * them in the `delivery` group, which draws them under one heading. The panel includes them as
 * `...delivery()` — the line `extra.webx.panel.register` of the composer package names. A section
 * whose server half is not installed never appears: the entry is built from the manifest.
 *
 * The fields only this section's screens have — a locality, the courier's localities, a region
 * of the country, a country of the site, the opening hours — come with the first module.
 */
export function delivery(options: DeliveryOptions = {}): AdminModule[] {
  const path = options.path ?? '/delivery'
  const section = (id: string, slug: string, name: string, page: () => Promise<unknown>) => ({
    id,
    path: slug === '' ? path : `${path}/${slug}`,
    routes: [
      {
        path: slug === '' ? path : `${path}/${slug}`,
        name,
        component: page as never,
      },
    ],
  })

  return [
    {
      ...section('delivery', '', 'webx.delivery', () => import('./MethodsPage.vue')),
      types: {
        'wx-delivery-locality': { component: LocalityField, kind: 'field' },
        'wx-delivery-localities': { component: LocalitiesField, kind: 'field', wide: true },
        'wx-delivery-region': { component: RegionField, kind: 'field', nested: true },
        'wx-delivery-country': { component: CountryField, kind: 'field' },
        'wx-delivery-schedule': { component: ScheduleField, kind: 'field', wide: true },
      },
    },
    section('delivery-points', 'points', 'webx.delivery.points', () => import('./PointsPage.vue')),
    section(
      'delivery-carriers',
      'carriers',
      'webx.delivery.carriers',
      () => import('./CarriersPage.vue'),
    ),
    section(
      'delivery-geography',
      'geography',
      'webx.delivery.geography',
      () => import('./GeographyPage.vue'),
    ),
    section(
      'delivery-popular',
      'popular',
      'webx.delivery.popular',
      () => import('./PopularPage.vue'),
    ),
    section(
      'delivery-bindings',
      'bindings',
      'webx.delivery.bindings',
      () => import('./BindingsPage.vue'),
    ),
  ]
}
