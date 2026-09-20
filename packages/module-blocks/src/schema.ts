import type { ScreenNode } from '@webx-ui/schema'

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
