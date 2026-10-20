import { defineAsyncComponent } from 'vue'
import type { AdminModule } from '@webx-ui/module-admin'

export interface SnippetsOptions {
  /** Where the codes live inside the panel. */
  path?: string
}

/**
 * The codes of outside services as a section of the panel (§7 of the spec).
 *
 * One route: the open code is in the address (`?snippet=`), beside the list it was opened from,
 * so a link to it is a link to both. A section whose server half is not installed — or that the
 * person may not open, which is everybody but a super administrator until a role is given
 * `snippets.manage` — never appears: the entry is built from the manifest.
 */
export function snippets(options: SnippetsOptions = {}): AdminModule {
  const path = options.path ?? '/snippets'

  return {
    id: 'snippets',
    path,
    routes: [
      {
        path,
        name: 'webx.snippets',
        component: () => import('./SnippetsPage.vue'),
        props: { base: path },
      },
    ],
    types: {
      // "Enabled" with the mark of a code this copy of the site does not print.
      // Loaded with the form, not with the panel: its words are the section's.
      'wx-snippets-enabled': {
        component: defineAsyncComponent(() => import('./EnabledField.vue')),
        kind: 'field',
      },
    },
  }
}
