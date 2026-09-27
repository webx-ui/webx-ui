import type { AdminModule } from '@webx-ui/module-admin'
import TeamPage from './TeamPage.vue'

export interface TeamOptions {
  /** Where the team lives inside the panel. */
  path?: string
}

/**
 * The team as a section of the panel (§5.6): one module and one entry, without a group of its
 * own — there are no categories to stand beside it (decision 1), and a group around a single
 * item is a heading over itself. A section whose server half is not installed never appears —
 * the entry is built from the manifest.
 */
export function team(options: TeamOptions = {}): AdminModule {
  const path = options.path ?? '/team'

  return {
    id: 'team',
    path,
    // One route: the open person is in the address (`?member=`), beside the list they were
    // opened from, so a link to them is a link to both.
    routes: [{ path, name: 'webx.team', component: TeamPage, props: { base: path } }],
  }
}
