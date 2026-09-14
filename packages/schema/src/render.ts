import { defineComponent, h, type PropType, type VNode } from 'vue'
import { WxFormItem } from '@webx-ui/core'
import { isVisible } from './visible'
import type { ScreenModel, ScreenNode, Translate, TypeRegistry } from './types'

export const TRANS_MARKER = 'trans::'

/** What the recursive renderer needs at every level. */
export interface RenderContext {
  types: TypeRegistry
  model: ScreenModel
  update: (name: string, value: unknown) => void
  translate: Translate
  can: (permission: string) => boolean
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
  if (node.can && !context.can(node.can)) return null
  if (!isVisible(node, context.model)) return null

  const entry = context.types[node.type]
  if (!entry) return renderUnknown(node)

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
      name,
      localized: node.localized || undefined,
      modelValue: name === undefined ? undefined : context.model[name],
      'onUpdate:modelValue': (value: unknown) => {
        if (name !== undefined) context.update(name, value)
      },
    })
    return h(
      WxFormItem,
      { key: node.id, name, label, help: words(node.help, translate) },
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
