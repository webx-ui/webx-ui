import { markRaw, type Component } from 'vue'
import type { AdminModule } from '@webx-ui/module-admin'
import SeoAliasesPage from './SeoAliasesPage.vue'
import SeoCard from './SeoCard.vue'
import SeoRedirectsPage from './SeoRedirectsPage.vue'
import SeoUrlsPage from './SeoUrlsPage.vue'

export interface SeoOptions {
  /** Where the section lives inside the panel. */
  path?: string
  /**
   * The field that picks a share image — `WxMediaField` from `@webx-ui/module-media`.
   *
   * Handed in rather than imported, so this package does not depend on the library being
   * installed. Without it every other SEO field still works; only the picture is missing.
   */
  mediaField?: Component
}

/**
 * SEO as a section of the panel, and `wx-seo` as a field any screen can use.
 *
 * The id matches the module the server reports, which is what makes the entry appear in the
 * navigation: a section shows up when both halves are installed, and stays out of the way when
 * only one is.
 */
export function seo(options: SeoOptions = {}): AdminModule {
  const path = options.path ?? '/seo'
  /* A component kept in an options object arrives as a reactive proxy; Vue warns about one and
     `h()` wants the raw thing anyway. */
  const mediaField = options.mediaField ? markRaw(options.mediaField) : undefined

  return {
    id: 'seo',
    path,
    routes: [
      // The section's own path travels as a prop, so a panel that mounted it somewhere else
      // still links between its two screens correctly.
      {
        path,
        name: 'webx.seo',
        component: SeoUrlsPage,
        props: { base: path, mediaField },
      },
      {
        path: `${path}/redirects`,
        name: 'webx.seo.redirects',
        component: SeoRedirectsPage,
        props: { base: path },
      },
      // The trail of renames, from `webx-ui/routing`. The screen is here whether or not any
      // content module has registered a type yet: without one the table is simply empty, and an
      // empty table is a better answer than a tab that appears one day without explanation.
      {
        path: `${path}/aliases`,
        name: 'webx.seo.aliases',
        component: SeoAliasesPage,
        props: { base: path },
      },
    ],
    // What a screen means by `wx-seo`: everything a page says about itself, as one value. The
    // server registers the same name for what it stores.
    types: {
      'wx-seo': {
        component: SeoCard,
        kind: 'field',
        wide: true,
        bind: () => ({ mediaField }),
      },
    },
  }
}
