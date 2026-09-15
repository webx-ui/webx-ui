import type { AdminModule } from '@webx-ui/module-admin'
import BlockEditorPage from './BlockEditorPage.vue'
import BlocksField from './BlocksField.vue'
import BlocksPage from './BlocksPage.vue'

export interface BlocksOptions {
  /** Where the section lives inside the panel. */
  path?: string
}

/**
 * The block constructor as a section of the panel, and `wx-blocks` as a field any screen can
 * put on an entity.
 *
 * The id matches the module the server reports, which is what makes the entry appear in the
 * navigation: the section shows up when both halves are installed.
 */
export function blocks(options: BlocksOptions = {}): AdminModule {
  const path = options.path ?? '/blocks'

  return {
    id: 'blocks',
    path,
    routes: [
      { path, name: 'webx.blocks', component: BlocksPage, props: { base: path } },
      {
        path: `${path}/:id(\\d+)`,
        name: 'webx.blocks.edit',
        component: BlockEditorPage,
        props: { base: path },
      },
    ],
    // What a screen means by `wx-blocks`: the content of an entity as a tree of blocks. A
    // nested field, so the renderer hands it the node and the way down — though it never
    // draws its children through the renderer; it needs the node for `props.allow` alone.
    types: {
      'wx-blocks': {
        component: BlocksField,
        kind: 'field',
        nested: true,
        bind: () => ({ blocksPath: path }),
      },
    },
  }
}
