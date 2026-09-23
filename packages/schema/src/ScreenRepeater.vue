<script setup lang="ts">
import { computed, useAttrs } from 'vue'
import { WxRepeater } from '@webx-ui/core'
import { WxScreenNodes, type RenderContext } from './render'
import type { ScreenModel, ScreenNode } from './types'

defineOptions({ name: 'WxScreenRepeater', inheritAttrs: false })

/**
 * The one type with a nested model: the value is a list of records, and the node's
 * children are the fields of one of them. Every child is bound by its own `name` to a key
 * of the item, which is why a repeater is the only place where a name means nesting.
 *
 * Nothing of `WxRepeater`'s own is declared here — `title`, `itemLabel`, `min` and the
 * rest ride in as attributes, so a prop the screen did not set stays unset instead of
 * arriving as `false`.
 */
const props = defineProps<{
  /** The node being drawn — its children are one item's fields. */
  node: ScreenNode
  /** The renderer's context; each row gets a copy bound to its own item. */
  context: RenderContext
  /** The renderer hands every field these two. A repeater has no use for either. */
  name?: string
  localized?: boolean
}>()

const model = defineModel<ScreenModel[]>({ default: () => [] })

const items = computed<ScreenModel[]>(() => (Array.isArray(model.value) ? model.value : []))

const attrs = useAttrs()

/**
 * The panel's words, where the core has only English defaults: the core does not know the
 * panel's dictionary, and a translated form with "Add" and "Remove" in the middle of it is what
 * that looks like. A key the dictionary does not have — a renderer without one — comes back as
 * itself, and then the core's own word is the better answer.
 */
function word(key: string): string | undefined {
  const line = props.context.translate(`webx-admin::screens.repeater.${key}`)

  return line.includes('::') ? undefined : line
}

/**
 * Folded unless the node says otherwise: a list of records opened all at once is a form a
 * kilometre long, and the header of each row already says which one it is. A row added now is
 * the core's business and opens anyway.
 */
const bound = computed(() => {
  const defaults: Record<string, unknown> = { collapsed: true }

  for (const [prop, key] of [
    ['addLabel', 'add'],
    ['removeLabel', 'remove'],
    ['dragLabel', 'reorder'],
    ['emptyText', 'empty'],
  ] as const) {
    const line = word(key)
    if (line !== undefined) defaults[prop] = line
  }

  return { ...defaults, ...attrs }
})
</script>

<template>
  <wx-repeater v-bind="bound" :model-value="items" @update:model-value="model = $event">
    <template #default="{ item, update }">
      <wx-screen-nodes
        :nodes="node.children ?? []"
        :context="{
          ...context,
          model: item,
          update: (key: string, value: unknown) => update({ [key]: value }),
        }"
      />
    </template>
  </wx-repeater>
</template>
