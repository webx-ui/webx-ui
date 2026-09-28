import type { AdminModule } from '@webx-ui/module-admin'
import BannerEditorPage from './BannerEditorPage.vue'
import BannersPage from './BannersPage.vue'

export interface BannersOptions {
  /** Where the banners live inside the panel. */
  path?: string
}

/**
 * Banners as a section of the panel (§5.4): one module and one entry, without a group.
 *
 * Two screens. The places and the banners of one of them are a list and its detail, the way the
 * menus are; a banner is a page of its own, because its form is long — three pictures, the words
 * and up to three buttons — and a pane beside a list is no room for it. A section whose server
 * half is not installed never appears — the entry is built from the manifest.
 */
export function banners(options: BannersOptions = {}): AdminModule {
  const path = options.path ?? '/banners'

  return {
    id: 'banners',
    path,
    routes: [
      // The open place is in the address (`?place=`), so a link to it is a link to its banners.
      { path, name: 'webx.banners', component: BannersPage, props: { base: path } },
      // A banner not written yet: the place it goes to rides in the query (`?place=hero`).
      {
        path: `${path}/new`,
        name: 'webx.banners.new',
        component: BannerEditorPage,
        props: { base: path },
      },
      {
        path: `${path}/:id(\\d+)`,
        name: 'webx.banners.edit',
        component: BannerEditorPage,
        props: { base: path },
      },
    ],
  }
}
