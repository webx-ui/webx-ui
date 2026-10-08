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

/**
 * Fields whose sample is words somebody wrote — a heading, a caption, a picture. A block added to
 * a page starts without them: the sample is the author's example, and copied in as the block's own
 * content it went onto the site under the next autosave ("Quote text sample", "John Doe"). What
 * the other fields hold — a number, a switch, a choice from a list — is a setting, and the sample's
 * setting is as good a start as any.
 */
const WORDS = new Set([
  'wx-input',
  'wx-textarea',
  'wx-rich-text',
  'wx-tags-input',
  'wx-autocomplete',
  'wx-code-editor',
  'wx-media',
  'wx-gallery',
  'wx-file',
  'wx-files',
  'wx-link',
  'wx-repeater',
  'wx-relations',
  'wx-blocks',
])

/**
 * What a block added from the picker starts with: the sample's settings, none of its words.
 *
 * @see WORDS
 */
export function startValues(
  schema: ScreenNode[],
  sample: Record<string, unknown>,
): Record<string, unknown> {
  const values: Record<string, unknown> = {}

  for (const node of schemaFields(schema)) {
    if (WORDS.has(node.type) || !(node.id in sample)) continue

    const value = sample[node.id]

    // Nested blocks in a sample are the author's own — a page starts with an empty container.
    if (Array.isArray(value) && value.length > 0 && typeof value[0] === 'object') continue

    values[node.id] = value
  }

  return values
}

/** Plain text fields — what shows a value as it is stored, tags and all. */
const PLAIN = new Set(['wx-input', 'wx-textarea'])

/** A tag, opening or closing: `<span>`, `</em>`, `<br/>`. A lone `<` in a sentence is not one. */
const TAG = /<\/?[a-z][a-z0-9-]*(\s[^<>]*)?\/?>/i

/**
 * Whether a value — or any language of a translated one — holds markup.
 *
 * A heading written with an accent in it (`Deeply heard<span>.</span>`) still sits in a plain
 * input in types that came before the inline rich field, and the input shows it raw: the editor
 * sees the tags and is one stray keystroke away from printing them on the site.
 */
export function holdsMarkup(value: unknown): boolean {
  if (typeof value === 'string') return TAG.test(value)

  if (value && typeof value === 'object' && !Array.isArray(value)) {
    return Object.values(value as Record<string, unknown>).some(
      (one) => typeof one === 'string' && TAG.test(one),
    )
  }

  return false
}

/** The plain text fields of a block whose values hold markup, by id. */
export function markupFields(schema: ScreenNode[], values: Record<string, unknown>): string[] {
  return schemaFields(schema)
    .filter((node) => PLAIN.has(node.type) && holdsMarkup(values[node.id]))
    .map((node) => node.id)
}

/**
 * The form of a block, with the sample's words shown as placeholders in the empty fields that
 * take words: the editor sees what goes where without the example becoming the content.
 *
 * `marked` are plain fields whose value holds tags ({@see markupFields}); each gets `markupHelp`
 * under it unless the type already says something there. Nothing is changed in the value — the
 * tags are the author's, and the type moving the field to `wx-rich-text` with `inline` is what
 * takes them out of sight.
 */
export function withPlaceholders(
  nodes: ScreenNode[],
  sample: Record<string, unknown>,
  marked: string[] = [],
  markupHelp = '',
): ScreenNode[] {
  return nodes.map((node) => {
    if (LAYOUT.has(node.type)) {
      return node.children
        ? { ...node, children: withPlaceholders(node.children, sample, marked, markupHelp) }
        : node
    }

    const inline = node.type === 'wx-rich-text' && node.props?.inline === true
    if (!PLAIN.has(node.type) && !inline) return node

    const helped =
      markupHelp !== '' && marked.includes(node.id) && node.help === undefined
        ? { ...node, help: markupHelp }
        : node

    if (helped.props?.placeholder !== undefined) return helped

    // An inline field's sample is markup itself; its placeholder is the words without it.
    const words = sampleWords(sample[node.id], inline)

    return words === null
      ? helped
      : { ...helped, props: { ...(helped.props ?? {}), placeholder: words } }
  })
}

/**
 * Whether the site reads shortcodes in a field's value: words a person wrote — a line, a
 * paragraph, a rich text. An input typed as an e-mail, an address or a phone is a value the site
 * prints into an attribute, and a `[phone]` there would be a broken link rather than a number.
 */
function takesShortcodes(node: ScreenNode): boolean {
  if (node.type === 'wx-textarea' || node.type === 'wx-rich-text') return true
  if (node.type !== 'wx-input') return false

  const type = node.props?.type

  return type === undefined || type === 'text'
}

/**
 * The form of a block, with the site's shortcodes handed to every field the site reads them in —
 * through layout and into a repeater's items, whose children are the fields of one item. A field
 * whose type already set `tokens` keeps its own.
 *
 * Here and not in the panel's field types: the same `wx-input` is a slug or a search box on
 * other screens, and only block content is printed through the shortcodes. `words` are the
 * field's own words for them — core's defaults speak of placeholders, the panel of shortcodes.
 */
export function withShortcodes<T>(
  nodes: ScreenNode[],
  tokens: readonly T[],
  words: { tokensTitle?: string; tokensLabel?: string } = {},
): ScreenNode[] {
  if (tokens.length === 0) return nodes

  return nodes.map((node) => {
    const children = node.children ? withShortcodes(node.children, tokens, words) : node.children
    const next = children === node.children ? node : { ...node, children }

    return takesShortcodes(node) && next.props?.tokens === undefined
      ? { ...next, props: { ...words, ...(next.props ?? {}), tokens } }
      : next
  })
}

/** A sample's text: itself, or the first language of a translated one. */
function sampleWords(value: unknown, strip = false): string | null {
  if (value && typeof value === 'object' && !Array.isArray(value)) {
    value = Object.values(value as Record<string, unknown>).find(
      (one) => typeof one === 'string' && one.trim() !== '',
    )
  }

  if (typeof value !== 'string') return null

  const words = strip ? value.replace(new RegExp(TAG.source, 'gi'), '') : value

  return words.trim() === '' ? null : words.slice(0, 160)
}

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
