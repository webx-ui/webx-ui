<script setup lang="ts">
import { computed } from 'vue'
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
defineProps<{
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
</script>

<template>
  <wx-repeater v-bind="$attrs" :model-value="items" @update:model-value="model = $event">
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
