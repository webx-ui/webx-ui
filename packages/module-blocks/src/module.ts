import type { AdminModule } from '@webx-ui/module-admin'
import { WxTextarea } from '@webx-ui/core'
import BlockDataField from './BlockDataField.vue'
import BlockEditorPage from './BlockEditorPage.vue'
import BlocksField from './BlocksField.vue'
import BlocksPage from './BlocksPage.vue'
import RegionEditorPage from './RegionEditorPage.vue'
import RegionHistory from './RegionHistory.vue'
import RegionsPage from './RegionsPage.vue'

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
        wide: true,
        bind: () => ({ blocksPath: path }),
      },
      // The two inputs only a component's schema has (§3.3): a structure passed from code,
      // edited in the sample form as the JSON it is, and a piece of markup the caller puts
      // between the tags, as HTML. Neither is a field an editor fills on a page.
      'wx-data': { component: BlockDataField, kind: 'field', wide: true },
      'wx-slot': {
        component: WxTextarea,
        kind: 'field',
        bind: () => ({ rows: 3, placeholder: '<p>…</p>' }),
      },
    },
  }
}

export interface RegionsOptions {
  /** Where the section lives inside the panel. */
  path?: string
}

/**
 * The layout regions — the header and the footer made of blocks — as a section of their own.
 *
 * A second module rather than two more routes of `blocks()`: the navigation is one entry per
 * module, and this one stands in a different place for a different person. Blocks is the
 * developer's section, closed by `blocks.manage` because a template is code; the header is
 * edited by whoever edits the pages, under `blocks.regions`. The id matches the module the
 * server reports, which is what puts the entry in the menu.
 */
export function regions(options: RegionsOptions = {}): AdminModule {
  const path = options.path ?? '/regions'

  return {
    id: 'regions',
    path,
    routes: [
      { path, name: 'webx.regions', component: RegionsPage, props: { base: path } },
      {
        path: `${path}/:name`,
        name: 'webx.regions.edit',
        component: RegionEditorPage,
        props: { base: path },
      },
    ],
    // The part of `regions.form` that is not a field: it reads the region from the editor.
    types: {
      'wx-region-history': { component: RegionHistory, kind: 'display' },
    },
  }
}
