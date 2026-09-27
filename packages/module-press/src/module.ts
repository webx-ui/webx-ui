import type { AdminModule } from '@webx-ui/module-admin'
import PressPage from './PressPage.vue'

export interface PressOptions {
  /** Where the outlets live inside the panel. */
  path?: string
}

/**
 * The press as a section of the panel (§4.9): one module and one entry, without a group of its
 * own — the reviews have one because they have two entries, and a group around a single item is
 * a heading over itself. A section whose server half is not installed never appears — the entry
 * is built from the manifest.
 */
export function press(options: PressOptions = {}): AdminModule {
  const path = options.path ?? '/press'

  return {
    id: 'press',
    path,
    // One route: the open outlet is in the address (`?outlet=`), beside the list it was opened
    // from, so a link to it is a link to both.
    routes: [{ path, name: 'webx.press', component: PressPage, props: { base: path } }],
  }
}
