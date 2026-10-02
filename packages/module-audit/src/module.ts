import type { AdminModule } from '@webx-ui/module-admin'
import AuditHostsPage from './AuditHostsPage.vue'
import AuditIssuesPage from './AuditIssuesPage.vue'
import AuditOverviewPage from './AuditOverviewPage.vue'
import AuditPagesPage from './AuditPagesPage.vue'
import AuditRunsPage from './AuditRunsPage.vue'
import AuditSettingsPage from './AuditSettingsPage.vue'

export interface AuditOptions {
  /** Where the section lives inside the panel. */
  path?: string
  /**
   * @deprecated The audit's settings are a view of the section itself (`<path>/settings`); the
   * option is read by nothing and stays only so that a panel which passed it still compiles.
   */
  settingsPath?: string
}

/**
 * «System → Audit» as a section of the panel: the overview of the last run, its findings, the
 * pages the last full run crawled, the hosts the site points at, the history of runs with two of
 * them compared, and the section's settings.
 *
 * The id matches the module the server reports, which is what makes the entry appear in the
 * navigation: the section shows up when both halves are installed.
 */
export function audit(options: AuditOptions = {}): AdminModule {
  const path = options.path ?? '/audit'
  const props = { base: path }

  return {
    id: 'audit',
    path,
    routes: [
      { path, name: 'webx.audit', component: AuditOverviewPage, props },
      { path: `${path}/issues`, name: 'webx.audit.issues', component: AuditIssuesPage, props },
      { path: `${path}/pages`, name: 'webx.audit.pages', component: AuditPagesPage, props },
      { path: `${path}/hosts`, name: 'webx.audit.hosts', component: AuditHostsPage, props },
      { path: `${path}/runs`, name: 'webx.audit.runs', component: AuditRunsPage, props },
      {
        path: `${path}/settings`,
        name: 'webx.audit.settings',
        component: AuditSettingsPage,
        props,
      },
    ],
  }
}
