<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { WxTreeSelect, type TreeSelectValue } from '@webx-ui/core'
import { useAdmin, useTranslate } from '@webx-ui/module-admin'
import { createPropertiesApi } from './api'
import { wordsIn } from './format'
import { NAMESPACE } from './i18n'
import type { PropertyRow, PropertyValue, ValueNode } from './types'

/**
 * A value of a tree-shaped reference book in the product form (§7.2): picked in the tree, which
 * reads a branch when it is opened. With «only the last level» a branch is opened but not chosen;
 * without it a node in the middle is a value too — «fits every BMW 3».
 *
 * A chosen value deep in an unopened tree is named by its path, which the server gives with it.
 */
defineOptions({ name: 'WxCatalogPropertyValueTree' })

const props = withDefaults(
  defineProps<{ property: PropertyRow; disabled?: boolean; ariaLabel?: string }>(),
  { disabled: false, ariaLabel: undefined },
)

const value = defineModel<number | number[] | null>({ default: null })

const admin = useAdmin()
const api = createPropertiesApi(admin)
const t = useTranslate(NAMESPACE)

const locale = computed(() => admin.i18n.state.locale)

const roots = ref<ValueNode[]>([])
const paths = ref<ValueNode[][]>([])

function nodeOf(one: PropertyValue): ValueNode {
  return {
    ...one,
    label: wordsIn(one.title, locale.value, `#${one.id}`),
    leaf: !one.has_children,
    blocked: props.property.leaves_only && one.has_children,
    children: undefined,
  }
}

const chosen = computed<number[]>(() =>
  value.value === null ? [] : Array.isArray(value.value) ? value.value : [value.value],
)

async function loadRoots(): Promise<void> {
  try {
    roots.value = (await api.values(props.property.id)).map(nodeOf)
  } catch {
    roots.value = []
  }
}

void loadRoots()

watch(
  chosen,
  async (ids) => {
    const named = new Set(paths.value.map((path) => path[path.length - 1]?.id))
    const missing = ids.filter((id) => !named.has(id))

    if (missing.length === 0) return

    try {
      const answer = await api.valuesById(props.property.id, missing)

      paths.value = [
        ...paths.value.filter((path) => ids.includes(path[path.length - 1]?.id ?? -1)),
        ...answer.map((one) => [...(one.ancestors ?? []).map(nodeOf), nodeOf(one)]),
      ]
    } catch {
      // Named by id until the tree is opened; the value itself is kept.
    }
  },
  { immediate: true },
)

const selectedPath = computed(() =>
  props.property.is_multiple
    ? paths.value
    : (paths.value.find((path) => path[path.length - 1]?.id === chosen.value[0]) ?? []),
)

async function load(node: ValueNode): Promise<ValueNode[]> {
  return (await api.values(props.property.id, node.id)).map(nodeOf)
}

function pick(next: TreeSelectValue): void {
  const list = next === null ? [] : Array.isArray(next) ? next.map(Number) : [Number(next)]

  value.value = props.property.is_multiple ? list : (list[0] ?? null)
}
</script>

<template>
  <wx-tree-select
    :model-value="props.property.is_multiple ? chosen : (chosen[0] ?? null)"
    :nodes="roots"
    :multiple="props.property.is_multiple"
    :check-strictly="true"
    :selected-path="selectedPath"
    :load="load"
    lazy
    node-key="id"
    label-key="label"
    leaf-key="leaf"
    disabled-key="blocked"
    show-path
    clearable
    :disabled="props.disabled"
    :aria-label="props.ariaLabel"
    :placeholder="t('panel.value-pick')"
    :empty-text="t('panel.values-empty')"
    @update:model-value="pick"
  />
</template>
