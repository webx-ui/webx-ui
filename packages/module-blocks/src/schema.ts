import type { ScreenNode } from '@webx-ui/schema'
import type { BlockKind } from './types'

/**
 * A block's schema names a field by `id` alone — the id is the key in the values, and the
 * variable in the template. The renderer binds a field by `name`. So before a schema is
 * drawn as a form, every node without a name gets its id as one, layout nodes included: the
 * renderer only reads `name` on fields, and a name on a card is a name nobody looks at.
 */
export function formSchema(schema: ScreenNode[]): ScreenNode[] {
  return schema.map((node) => ({
    ...node,
    name: node.name ?? node.id,
    children: node.children ? formSchema(node.children) : node.children,
  }))
}

/**
 * How many entities a type stands on, in words.
 *
 * Three lines rather than one with a number in it: `on :count pages` reads as "on 1 pages"
 * exactly when a type has just been put somewhere for the first time, which is the moment an
 * editor is most likely to be looking at it. The panel has no plural forms and does not need
 * them — one is one, and the rest is a number.
 */
export function usageWords(
  count: number,
  t: (key: string, params?: Record<string, string | number>) => string,
): string {
  if (count === 0) return t('page.not-used')

  return count === 1 ? t('page.on-page') : t('page.on-pages', { count })
}

/**
 * Words for a group id. The dictionary may have one under `groups.<id>`; a site that added
 * a group of its own and no words for it sees the id, capitalised, which is honest.
 */
export function groupLabel(
  id: string,
  t: (key: string, params?: Record<string, string | number>) => string,
): string {
  const key = `groups.${id}`
  const words = t(key)

  return words === key || words === `webx-blocks::${key}`
    ? id.charAt(0).toUpperCase() + id.slice(1)
    : words
}

/** What a type is; a server older than components sends no `kind`, and everything was a block. */
export function kindOf(type: { kind?: BlockKind }): BlockKind {
  return type.kind ?? 'block'
}

/**
 * How many types call a component, in words — the component's "on 3 pages". Counted in types
 * rather than pages on purpose (§3.10): the pages behind every parent are expensive to count and
 * say little, while the parents are what a change to the component can break.
 */
export function callerWords(
  count: number,
  t: (key: string, params?: Record<string, string | number>) => string,
): string {
  if (count === 0) return t('components.not-called')

  return count === 1 ? t('components.in-block') : t('components.in-blocks', { count })
}

/** Layout nodes: they group fields and are never a value of their own. */
const LAYOUT = new Set(['wx-card', 'wx-tabs', 'wx-tab', 'wx-row', 'wx-col', 'wx-divider'])

/** The fields of a schema through its layout, flat — the variables a template gets. */
export function schemaFields(schema: ScreenNode[]): ScreenNode[] {
  const found: ScreenNode[] = []

  for (const node of schema) {
    if (!node || typeof node !== 'object') continue
    if (LAYOUT.has(node.type)) found.push(...schemaFields(node.children ?? []))
    else if (typeof node.id === 'string' && node.id !== '') found.push(node)
  }

  return found
}

/**
 * The tag that calls a type, with every input it takes — what the help under a component's
 * template offers to copy.
 *
 * `wx-data` is passed from code, so it is bound (`:card="$card"`); a plain field shows its sample
 * when that is a word (`tone="accent"`) and is bound otherwise. Declared slots stand in the body;
 * with none, the tag closes itself — the default `$slot` is there all the same, and a body shown
 * for it would read as something the component needs.
 */
export function callTag(
  slug: string,
  schema: ScreenNode[],
  sample: Record<string, unknown> = {},
  fallback: string | null = null,
): string {
  const fields = schemaFields(schema)
  const attributes = [`type="${slug}"`]

  for (const node of fields) {
    if (node.type === 'wx-slot' || node.type === 'wx-blocks') continue

    const value = sample[node.id]

    attributes.push(
      node.type !== 'wx-data' && (typeof value === 'string' || typeof value === 'number')
        ? `${node.id}="${String(value).replace(/"/g, '&quot;')}"`
        : `:${node.id}="$${variableOf(node.id)}"`,
    )
  }

  if (fallback) attributes.push(`fallback="${fallback}"`)

  const slots = fields.filter((node) => node.type === 'wx-slot')
  const head = `<x-webx-block ${attributes.join(' ')}`

  if (slots.length === 0) return `${head} />`

  return [
    `${head}>`,
    ...slots.map((node) => `    <x-slot:${node.id}>…</x-slot:${node.id}>`),
    '</x-webx-block>',
  ].join('\n')
}

/** `cta-label` has no variable of its own; the caller's is written as it would be named. */
function variableOf(id: string): string {
  return id.replace(/-(\w)/g, (_match, letter: string) => letter.toUpperCase())
}
