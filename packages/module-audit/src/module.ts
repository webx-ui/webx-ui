import type { AdminModule } from '@webx-ui/module-admin'
import AuditIssuesPage from './AuditIssuesPage.vue'
import AuditOverviewPage from './AuditOverviewPage.vue'
import AuditPagesPage from './AuditPagesPage.vue'

export interface AuditOptions {
  /** Where the section lives inside the panel. */
  path?: string
  /** Where the site's settings are — the audit's own live on their «Audit» tab. */
  settingsPath?: string
}

/**
 * «System → Audit» as a section of the panel: the overview of the last run, its findings and the
 * pages the last full run crawled.
 *
 * The id matches the module the server reports, which is what makes the entry appear in the
 * navigation: the section shows up when both halves are installed.
 */
export function audit(options: AuditOptions = {}): AdminModule {
  const path = options.path ?? '/audit'
  const settingsPath = options.settingsPath ?? '/settings'

  return {
    id: 'audit',
    path,
    routes: [
      {
        path,
        name: 'webx.audit',
        component: AuditOverviewPage,
        props: { base: path, settingsPath },
      },
      {
        path: `${path}/issues`,
        name: 'webx.audit.issues',
        component: AuditIssuesPage,
        props: { base: path, settingsPath },
      },
      {
        path: `${path}/pages`,
        name: 'webx.audit.pages',
        component: AuditPagesPage,
        props: { base: path, settingsPath },
      },
    ],
  }
}
