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
