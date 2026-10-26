import type { AdminModule } from '@webx-ui/module-admin'
import ConfiguratorField from './ConfiguratorField.vue'
import PresetField from './PresetField.vue'
import RequestEnclosure from './RequestEnclosure.vue'

export interface ConfiguratorsOptions {
  /** Where the section lives inside the panel. */
  path?: string
}

/**
 * Configurators as a section of the panel (§13): the list, and the editor of one.
 *
 * An array, spread into the panel's modules — `...configurators()`, the line
 * `extra.webx.panel.register` of the composer package writes — so that a part of the section can
 * come later without changing how a site includes it. The id matches the module the server
 * reports: the entry appears when both halves are installed.
 */
export function configurators(options: ConfiguratorsOptions = {}): AdminModule[] {
  const path = options.path ?? '/configurators'

  return [
    {
      id: 'configurators',
      path,
      routes: [
        {
          path,
          name: 'webx.configurators',
          component: () => import('./ConfiguratorsPage.vue'),
          props: { base: path },
        },
        {
          path: `${path}/:id(\\d+)`,
          name: 'webx.configurators.editor',
          component: () => import('./ConfiguratorEditorPage.vue'),
          props: { base: path },
        },
      ],
      // A configurator as a field of any screen or block — the block `configurator` of the site
      // first (§12.1) — chosen by name and kept by key. Here and not in the panel: what it lists is
      // this section's.
      types: {
        'wx-configurator': { component: ConfiguratorField, kind: 'field' },
        // Where the block starts: nested, so it reads the configurator chosen in the field beside it.
        'wx-configurator-preset': {
          component: PresetField,
          kind: 'field',
          nested: true,
          wide: true,
        },
        // The configuration in a request, drawn in the inbox's card (inbox §2.20).
        'inbox-enclosure:configurator': { component: RequestEnclosure, kind: 'display' },
      },
    },
  ]
}
