import { readdirSync, readFileSync, statSync } from 'node:fs'
import { dirname, join, relative, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import type { ScreenNode } from '@webx-ui/schema'
import { takesShortcodes } from './shortcodes'

const packages = resolve(dirname(fileURLToPath(import.meta.url)), '../../../php/packages')

/** Every screen a composer package ships, as `<package>:<screen>` → its tree. */
function screens(): Map<string, unknown> {
  const found = new Map<string, unknown>()

  const walk = (dir: string, pkg: string): void => {
    for (const name of readdirSync(dir)) {
      const path = join(dir, name)

      if (statSync(path).isDirectory()) walk(path, pkg)
      else if (name.endsWith('.json')) {
        const screen = relative(join(packages, pkg, 'resources/screens'), path).replace(
          /\.json$/,
          '',
        )
        found.set(`${pkg}:${screen}`, JSON.parse(readFileSync(path, 'utf8')))
      }
    }
  }

  for (const pkg of readdirSync(packages)) {
    const dir = join(packages, pkg, 'resources/screens')

    try {
      walk(dir, pkg)
    } catch {
      // A package with no screens.
    }
  }

  return found
}

/** The text fields of a tree that take shortcodes, a repeater's as `<list>[].<field>`. */
function taking(tree: unknown, prefix = ''): string[] {
  if (Array.isArray(tree)) return tree.flatMap((one) => taking(one, prefix))
  if (!tree || typeof tree !== 'object') return []

  const node = tree as ScreenNode
  const inner = node.type === 'wx-repeater' && node.name ? `${prefix}${node.name}[].` : prefix
  const own =
    ['wx-input', 'wx-textarea', 'wx-rich-text'].includes(node.type) &&
    node.name !== undefined &&
    takesShortcodes(node.type, node.props?.shortcodes)
      ? [`${prefix}${node.name}`]
      : []

  return [
    ...own,
    ...Object.values(node).flatMap((value) =>
      typeof value === 'object' ? taking(value, inner) : [],
    ),
  ]
}

/*
 * Every field of a module's screen that offers the site's shortcodes. A field here is printed on
 * the site through `PageText` — a page, a card, a feed and the markup of one record alike — and
 * has a test of the site's that says so. Adding one to a screen without the site half shows a
 * visitor a bare `[phone]`, which is why the list is spelled out rather than worked out: the
 * diff that grows it is the one that has to bring the other half.
 */
const EXPECTED = [
  'module-banners:form text',
  'module-blog:article-form lead',
  'module-blog:author-form bio',
  'module-blog:category-form lead',
  'module-catalog-brands:brand-form description',
  'module-catalog-landings:landing-form text_above',
  'module-catalog-landings:landing-form text_below',
  'module-catalog:category-form description',
  'module-catalog:product-form summary',
  'module-catalog:product-form description',
  'module-events:category-form lead',
  'module-events:form description',
  'module-events:form highlights[].text',
  'module-events:form lead',
  'module-faq:form answer',
  'module-press:outlet-form summary',
  'module-press:outlet-form articles[].excerpt',
  'module-recipes:category-form lead',
  'module-recipes:form ingredients',
  'module-recipes:form method',
  'module-recipes:form lead',
  'module-reviews:form text',
  'module-services:category-form lead',
  'module-services:form lead',
  'module-tariffs:form features[].text',
  'module-tariffs:form description',
  'module-team:form text',
  'module-vacancies:form description',
  'module-vacancies:form duties[].text',
  'module-vacancies:form requirements[].text',
  'module-vacancies:form benefits[].text',
  'module-vacancies:form lead',
]

describe('the screens that offer shortcodes', () => {
  it('are the fields the site prints through them', () => {
    const found = [...screens()].flatMap(([screen, tree]) =>
      taking(tree).map((field) => `${screen} ${field}`),
    )

    expect(found.sort()).toEqual([...EXPECTED].sort())
  })
})
