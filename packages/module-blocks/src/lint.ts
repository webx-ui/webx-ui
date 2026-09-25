import type { ScreenNode } from '@webx-ui/schema'
import type { Lint } from './types'

/**
 * The checks under the editor, live (§15): the same four the server runs on saving, plus the
 * one that would refuse publication — a variable the schema does not declare. None locks a
 * save; every one is a habit that bites later, on another block or another page.
 *
 * Mirrors `Panel\Lints` in the composer package, message for message.
 */

type Translate = (key: string, params?: Record<string, string | number>) => string

/**
 * Variables Blade or the renderer hand a template on their own. `$slot` among them: any type can
 * be called with `<x-webx-block>`, and the default slot is there whether or not the tag had a
 * body — empty when it did not (§3.3 of the components spec).
 */
const GIVEN = ['block', 'entity', 'loop', 'slot', '__env', 'errors', 'app']

export function lintBlock(
  slug: string,
  content: { template: string; styles: string; schema: ScreenNode[] },
  t: Translate,
): Lint[] {
  const lints: Lint[] = []

  if (!/data-wx-block\s*=/.test(content.template)) {
    lints.push({ file: 'template', code: 'no-marker', line: null, message: t('checks.no-marker') })
  }

  const missing = undeclared(content.template, content.schema)

  if (missing.length > 0) {
    lints.push({
      file: 'template',
      code: 'variables-missing',
      line: null,
      message: t('checks.variables-missing', {
        variables: missing.map((name) => `$${name}`).join(', '),
      }),
    })
  }

  const stray: [string, number][] = []
  const bare: [string, number][] = []
  const prefix = `.b-${slug}`

  for (const [selector, line] of selectors(content.styles)) {
    if (selector.startsWith(prefix) || selector.startsWith('&')) continue

    if (/^[a-z][a-z0-9-]*/i.test(selector)) bare.push([selector, line])
    else stray.push([selector, line])
  }

  if (stray.length > 0) {
    lints.push({
      file: 'styles',
      code: 'stray-selectors',
      line: stray[0]![1],
      message: t('checks.stray-selectors', { slug, selectors: few(stray) }),
    })
  }

  if (bare.length > 0) {
    lints.push({
      file: 'styles',
      code: 'bare-selectors',
      line: bare[0]![1],
      message: t('checks.bare-selectors', { selectors: few(bare) }),
    })
  }

  const source = withoutComments(content.styles)
  const media = /@media\b/.exec(source)

  if (media) {
    lints.push({
      file: 'styles',
      code: 'media-query',
      line: lineAt(source, media.index),
      message: t('checks.media-query'),
    })
  }

  return lints
}

/** Every field id a schema declares, through its layout. */
export function fieldIds(schema: ScreenNode[]): string[] {
  const ids: string[] = []

  for (const node of schema) {
    if (node.id) ids.push(node.id)
    if (node.children) ids.push(...fieldIds(node.children))
  }

  return ids
}

/**
 * Variables the template reads that nothing declares: not the schema, not Blade's own, not
 * a `@foreach (… as $item)` or an `@php $x = …` in the template itself.
 */
export function undeclared(template: string, schema: ScreenNode[]): string[] {
  const declared = new Set([...fieldIds(schema), ...GIVEN])

  for (const match of template.matchAll(/\bas\s+\$([a-zA-Z_]\w*)(?:\s*=>\s*\$([a-zA-Z_]\w*))?/g)) {
    declared.add(match[1]!)
    if (match[2]) declared.add(match[2])
  }

  for (const match of template.matchAll(/\$([a-zA-Z_]\w*)\s*(?:=[^=]|\+\+|--|\.=|\+=|-=)/g)) {
    declared.add(match[1]!)
  }

  const used: string[] = []

  for (const match of template.matchAll(/\$([a-zA-Z_]\w*)/g)) {
    const name = match[1]!
    if (!declared.has(name) && !used.includes(name)) used.push(name)
  }

  return used
}

function selectors(styles: string): [string, number][] {
  const source = withoutComments(styles)
  const found: [string, number][] = []

  for (const match of source.matchAll(/([^{};]+)\{/g)) {
    const prelude = match[1]!
    const trimmed = prelude.trim()

    if (trimmed === '' || trimmed.startsWith('@')) continue

    for (const raw of prelude.split(',')) {
      const selector = raw.trim()

      if (selector === '' || selector === 'from' || selector === 'to' || selector.endsWith('%')) {
        continue
      }

      found.push([selector, lineAt(source, match.index + prelude.indexOf(selector))])
    }
  }

  return found
}

/** Comments replaced by spaces of the same length, so offsets and lines stay true. */
function withoutComments(styles: string): string {
  return styles.replace(/\/\*[\s\S]*?\*\//g, (comment) => comment.replace(/[^\n]/g, ' '))
}

function lineAt(source: string, offset: number): number {
  return source.slice(0, Math.max(0, offset)).split('\n').length
}

function few(selectors: [string, number][]): string {
  const names = [...new Set(selectors.map(([selector]) => selector))]

  return names.slice(0, 3).join(', ') + (names.length > 3 ? ', …' : '')
}
