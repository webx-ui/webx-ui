import {
  Comment,
  Fragment,
  Text,
  cloneVNode,
  defineComponent,
  type PropType,
  type VNode,
} from 'vue'

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
 *
 * The vnodes are cloned on the way out, and that is not a nicety. A vnode belongs to
 * the place it was mounted, and a slice that moves between two of them — the bar and
 * the overflow branch — would be asking Vue to mount the same one twice. Vue does this
 * cloning itself for a slot rendered in a template; vnodes handed along as a prop skip
 * that path. In a development build the compiler hands back a fresh vnode per render
 * and the reuse never shows; in a production build an entry with nothing dynamic about
 * it is cached and handed back as the very same object, and the second place it is
 * rendered comes out empty. That was an open, empty "More" on a phone, and nothing at
 * all in dev.
 */
export const WxNodes = defineComponent({
  name: 'WxNodes',
  props: {
    nodes: { type: Array as PropType<VNode[]>, required: true },
  },
  setup(props) {
    return () => props.nodes.map((node) => cloneVNode(node))
  },
})
