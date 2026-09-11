import { Comment, Fragment, Text, defineComponent, type PropType, type VNode } from 'vue'

/**
 * The children a slot was given, as a flat list, so they can be counted and split —
 * the entries a menu bar has no room for, the avatars a group shows past its limit.
 *
 * A `v-for` arrives as one fragment holding many entries, and a `v-if` that is false
 * arrives as a comment. Both have to go: the list is lined up index for index against
 * the `<li>` elements the browser actually laid out, and a comment is not one.
 */
export function flattenNodes(nodes: VNode[]): VNode[] {
  return nodes.flatMap((node) => {
    if (node.type === Fragment) return flattenNodes((node.children ?? []) as VNode[])
    if (node.type === Comment || node.type === Text) return []
    return [node]
  })
}

/**
 * Renders vnodes that have already been created — the two halves of a split slot.
 *
 * It exists because a template can render a slot but not a slice of one, and the
 * children must be rendered *once*: an entry that appeared in both halves would
 * register itself twice and report the wrong branch to the menu above it.
 */
export const WxNodes = defineComponent({
  name: 'WxNodes',
  props: {
    nodes: { type: Array as PropType<VNode[]>, required: true },
  },
  setup(props) {
    return () => props.nodes
  },
})
