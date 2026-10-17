import type { AdminModule } from '@webx-ui/module-admin'

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
      { path, name: 'webx.audit', component: () => import('./AuditOverviewPage.vue'), props },
      {
        path: `${path}/issues`,
        name: 'webx.audit.issues',
        component: () => import('./AuditIssuesPage.vue'),
        props,
      },
      {
        path: `${path}/pages`,
        name: 'webx.audit.pages',
        component: () => import('./AuditPagesPage.vue'),
        props,
      },
      {
        path: `${path}/hosts`,
        name: 'webx.audit.hosts',
        component: () => import('./AuditHostsPage.vue'),
        props,
      },
      {
        path: `${path}/runs`,
        name: 'webx.audit.runs',
        component: () => import('./AuditRunsPage.vue'),
        props,
      },
      {
        path: `${path}/settings`,
        name: 'webx.audit.settings',
        component: () => import('./AuditSettingsPage.vue'),
        props,
      },
    ],
  }
}
