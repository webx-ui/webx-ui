import type { AdminModule } from '@webx-ui/module-admin'
import FormEditorPage from './FormEditorPage.vue'
import InboxPage from './InboxPage.vue'
import StatusesPage from './StatusesPage.vue'
import SubmissionPage from './SubmissionPage.vue'

export interface InboxOptions {
  /** Where the section lives inside the panel. */
  path?: string
  /**
   * Whether the panel opens on this section.
   *
   * On by default, and that is the point of the module: a panel with an inbox is a panel
   * somebody opens in the morning to see what came in overnight, and the pages, the blocks
   * and the settings are what they do afterwards (§2.18). A panel whose front page is
   * something else says so here.
   */
  landing?: boolean
}

/**
 * Forms and submissions as a section of the panel.
 *
 * The id matches the module the server reports, which is what makes the entry appear in the
 * navigation: the section shows up when both halves are installed, and stays out of the way
 * when only one is.
 */
export function inbox(options: InboxOptions = {}): AdminModule {
  const path = options.path ?? '/inbox'

  return {
    id: 'inbox',
    path,
    landing: options.landing ?? true,
    routes: [
      // The section's own path travels as a prop, so a panel that mounted it somewhere else
      // still links between its screens correctly.
      { path, name: 'webx.inbox', component: InboxPage, props: { base: path } },
      {
        path: `${path}/statuses`,
        name: 'webx.inbox.statuses',
        component: StatusesPage,
        props: { base: path },
      },
      {
        path: `${path}/forms/:id(\\d+)`,
        name: 'webx.inbox.form',
        component: FormEditorPage,
        props: { base: path },
      },
      // A submission has an address of its own, not a dialog over the list (§11): it goes in
      // the mail to whoever deals with it, it gets pasted into a chat, an agent links to it.
      {
        path: `${path}/submissions/:id(\\d+)`,
        name: 'webx.inbox.submission',
        component: SubmissionPage,
        props: { base: path },
      },
    ],
  }
}
