import type { AdminContext } from '@webx-ui/module-admin'

/**
 * Which of the optional tools this site has (§18.3) — `meta` of the section in the manifest.
 *
 * Read on every call rather than once: the manifest arrives after the panel is drawn, and a
 * view decided before it would stay hidden on a site that has it.
 */
export function seoFeature(context: AdminContext, name: 'links' | 'faq' | 'og_fields'): boolean {
  const meta = context.state.manifest?.modules.find((module) => module.id === 'seo')?.meta

  return meta?.[name] === true
}

export const linksEnabled = (context: AdminContext): boolean => seoFeature(context, 'links')

export const faqEnabled = (context: AdminContext): boolean => seoFeature(context, 'faq')

/**
 * The share title, description and picture in the SEO card (`webx-seo.og.panel_fields`). Off by
 * default: the site fills Open Graph in from the page itself.
 */
export const ogFieldsEnabled = (context: AdminContext): boolean => seoFeature(context, 'og_fields')
