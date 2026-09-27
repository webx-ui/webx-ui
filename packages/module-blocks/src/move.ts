import type { ScreenNode } from '@webx-ui/schema'
import { isNodeList, locate, nestedFields } from './content'
import type { BlockNode, BlockType } from './types'

/**
 * May a block of this type stand here: the container's say-so and the type's own, both.
 *
 * `parent` is the block whose field it is, or null for the page; `allow` is what that field
 * narrows it to, when it does. The picker asks this for a new block and "Move to" for an
 * existing one, and the two must not disagree about where a block can go.
 */
export function allows(type: BlockType, parent: BlockType | null, allow: string[] | null): boolean {
  if (parent === null) {
    const fieldAllows = allow === null || allow.includes(type.slug)

    return fieldAllows && (type.allowed_in === null || type.allowed_in.includes('root'))
  }

  const parentAllows = allow
    ? allow.includes(type.slug)
    : parent.allow?.includes(type.slug) === true
  const typeAllows = type.allowed_in === null || type.allowed_in.includes(parent.slug)

  return parentAllows && typeAllows
}

/** One list a block could be moved into. */
export interface Destination {
  /** The container, or null for the top level. */
  parentKey: string | null
  field: string | null
  /** The containers down to it, in words, outermost first. Empty at the top level. */
  trail: string[]
  /** It already holds as many blocks as its field takes. */
  full: boolean
}

/** What the top level is: the page, or — in a block's sample form — that block. */
export interface TopLevel {
  owner: BlockType | null
  allow: string[] | null
  max: number | null
}

/**
 * Every list a block may be moved into, in the order the page draws them.
 *
 * Not the list it is in already — that is a drag — and nothing inside the block itself, which
 * would take it out of the tree along with its own children. A block of an unknown type goes
 * nowhere: there is nothing to judge the rules by.
 */
export function destinations(
  tree: BlockNode[],
  key: string,
  catalog: BlockType[],
  top: TopLevel,
): Destination[] {
  const moving = locate(tree, key)
  const type = moving ? catalog.find((entry) => entry.slug === moving.node.type) : undefined

  if (!moving || !type) return []

  const found: Destination[] = []
  const here = (parentKey: string | null, field: string | null) =>
    (moving.parent?.key ?? null) === parentKey && moving.field === field

  if (!here(null, null) && allows(type, top.owner, top.allow)) {
    found.push({
      parentKey: null,
      field: null,
      trail: [],
      full: top.max !== null && tree.length >= top.max,
    })
  }

  const visit = (list: BlockNode[], trail: string[]) => {
    for (const node of list) {
      if (node.key === key) continue

      const container = catalog.find((entry) => entry.slug === node.type)
      const fields: ScreenNode[] = container?.content ? nestedFields(container.content.schema) : []

      for (const slot of fields) {
        const title = container!.title
        const step = fields.length > 1 ? `${title} · ${slot.label ?? slot.id}` : title
        const value = node.values[slot.id]
        const children = isNodeList(value) ? value : []
        const max = (slot.props?.max as number | undefined) ?? null
        const allow = (slot.props?.allow as string[] | undefined) ?? null

        if (!here(node.key, slot.id) && allows(type, container!, allow)) {
          found.push({
            parentKey: node.key,
            field: slot.id,
            trail: [...trail, step],
            full: max !== null && children.length >= max,
          })
        }

        visit(children, [...trail, step])
      }
    }
  }

  visit(tree, [])

  return found
}
