import type { AdminModule } from '@webx-ui/module-admin'
import SettingsPage from './SettingsPage.vue'

export interface SettingsOptions {
  /** Where the section lives inside the panel. */
  path?: string
}

/**
 * The settings, as a section of the panel.
 *
 * The id matches the module the server reports, which is what makes the entry appear in the
 * navigation: a section shows up when both halves are installed.
 */
export function settings(options: SettingsOptions = {}): AdminModule {
  const path = options.path ?? '/settings'

  return {
    id: 'settings',
    path,
    routes: [{ path, name: 'webx.settings', component: SettingsPage }],
  }
}
