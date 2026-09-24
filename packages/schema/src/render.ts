import { defineComponent, h, type InjectionKey, type PropType, type Ref, type VNode } from 'vue'
import { WxFormItem } from '@webx-ui/core'
import { isVisible } from './visible'
import type { ScreenModel, ScreenNode, Translate, TypeRegistry, ValidationErrors } from './types'

export const TRANS_MARKER = 'trans::'

/** What the recursive renderer needs at every level. */
export interface RenderContext {
  types: TypeRegistry
  model: ScreenModel
  update: (name: string, value: unknown) => void
  translate: Translate
  can: (permission: string) => boolean
}

/**
 * The refusal of the last save, for the nodes that have to act on it rather than only draw it:
 * the tabs, which open the one the failing field is on. Fields draw theirs through `WxForm`.
 */
export const screenErrorsKey: InjectionKey<Ref<ValidationErrors | undefined>> =
  Symbol('wx-screen-errors')

/** Whether a node would draw anything at all for this administrator and these values. */
function shown(node: ScreenNode, context: RenderContext): boolean {
  if (node.can && !context.can(node.can)) return false
  return isVisible(node, context.model)
}

/** Without a dictionary the key itself shows — honest, and easy to spot in a screenshot. */
export const keyAsIs: Translate = (key) => key

/** Translates a marked string; anything else — including `undefined` — passes through. */
export function words<T>(value: T, translate: Translate): T {
  if (typeof value === 'string' && value.startsWith(TRANS_MARKER)) {
    return translate(value.slice(TRANS_MARKER.length)) as T
  }
  return value
}

/** Same, through arrays and objects: `props.options[].label` is the common case. */
export function translateDeep<T>(value: T, translate: Translate): T {
  if (typeof value === 'string') return words(value, translate)
  if (Array.isArray(value)) return value.map((item) => translateDeep(item, translate)) as T
  if (value && typeof value === 'object') {
    const out: Record<string, unknown> = {}
    for (const [key, item] of Object.entries(value)) out[key] = translateDeep(item, translate)
    return out as T
  }
  return value
}

function renderUnknown(node: ScreenNode): VNode {
  return h(
    'div',
    { key: node.id, class: 'wx-screen__unknown', role: 'note' },
    `Unknown type: ${node.type}`,
  )
}

/** Children grouped by the slot they asked for. */
function childSlots(
  node: ScreenNode,
  defaultSlot: string,
  context: RenderContext,
): Record<string, () => VNode[]> {
  const groups = new Map<string, ScreenNode[]>()
  for (const child of node.children ?? []) {
    const name = child.slot ?? defaultSlot
    const list = groups.get(name) ?? []
    list.push(child)
    groups.set(name, list)
  }
  const slots: Record<string, () => VNode[]> = {}
  for (const [name, children] of groups) {
    slots[name] = () => renderNodes(children, context)
  }
  return slots
}

export function renderNode(node: ScreenNode, context: RenderContext): VNode | null {
  if (!shown(node, context)) return null

  const entry = context.types[node.type]
  if (!entry) return renderUnknown(node)

  /*
   * A container with nothing in it is not drawn. A screen declares the card the fields of a
   * project go into (`project-fields`) so that a patch knows where to stand, and on every site
   * that patches nothing it was an empty card with a heading — a promise of fields that are not
   * there. Only a container that has children to lose: one described without any is a component
   * that draws itself.
   */
  if (
    entry.kind === 'layout' &&
    node.children !== undefined &&
    !node.children.some((child) => shown(child, context))
  ) {
    return null
  }

  const { translate } = context
  const props: Record<string, unknown> = {
    key: node.id,
    ...translateDeep(node.props ?? {}, translate),
    ...entry.bind?.(node),
  }
  const label = words(node.label, translate)

  if (entry.kind === 'field') {
    const name = node.name
    const control = h(entry.component, {
      ...props,
      /* A nested field draws the children itself, so it needs the node and the way down. */
      ...(entry.nested ? { node, context } : {}),
      name,
      localized: node.localized || undefined,
      modelValue: name === undefined ? undefined : context.model[name],
      'onUpdate:modelValue': (value: unknown) => {
        if (name !== undefined) context.update(name, value)
      },
    })
    return h(
      WxFormItem,
      { key: node.id, name, label, help: words(node.help, translate), wide: entry.wide },
      () => control,
    )
  }

  if (entry.kind === 'layout') {
    if (entry.labelProp && label !== undefined) props[entry.labelProp] = label
    return h(entry.component, props, childSlots(node, entry.childrenSlot ?? 'default', context))
  }

  // display
  if (entry.labelProp) {
    if (label !== undefined) props[entry.labelProp] = label
    return h(entry.component, props)
  }
  return h(entry.component, props, label === undefined ? undefined : () => label)
}

export function renderNodes(nodes: ScreenNode[], context: RenderContext): VNode[] {
  const out: VNode[] = []
  for (const node of nodes) {
    const rendered = renderNode(node, context)
    if (rendered) out.push(rendered)
  }
  return out
}

/** A list of nodes as a component, so the tree can recurse through slots. */
export const WxScreenNodes = defineComponent({
  name: 'WxScreenNodes',
  props: {
    nodes: { type: Array as PropType<ScreenNode[]>, required: true },
    context: { type: Object as PropType<RenderContext>, required: true },
  },
  setup(props) {
    return () => renderNodes(props.nodes, props.context)
  },
})
