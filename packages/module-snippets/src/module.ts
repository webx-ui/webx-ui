import type { AdminModule } from '@webx-ui/module-admin'
import SnippetsPage from './SnippetsPage.vue'

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
    routes: [{ path, name: 'webx.snippets', component: SnippetsPage, props: { base: path } }],
  }
}
