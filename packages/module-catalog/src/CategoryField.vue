<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useAdmin, useRecordAddress, useTranslate } from '@webx-ui/module-admin'
import { WxTreeSelect, type TreeSelectValue } from '@webx-ui/core'
import { useCatalogMessages } from './i18n'
import { useCategoryTree } from './store'
import type { CategoryNode } from './types'

/**
 * `wx-catalog-category`: a category of the catalogue, or several with `props.multiple`.
 *
 * A tree and not a flat list, because the tree is how anybody finds a shelf — "Laptops" means
 * nothing until it is seen under "Computers" — and a single choice shows its whole path once
 * picked, for the same reason.
 *
 * The additional categories cannot include the main one (decision 2): the server would drop it
 * anyway, and a picker that lets somebody tick what will silently not be saved is a picker that
 * lies. The main one is read off the form the editor hosts.
 *
 * Nothing but `multiple` is declared: a wrapper that declared what `WxTreeSelect` takes would hand
 * it a `false` where the screen asked for nothing (CLAUDE.md §4).
 */
defineOptions({ name: 'WxCatalogCategoryField', inheritAttrs: false })

const props = withDefaults(defineProps<{ multiple?: boolean }>(), { multiple: false })

const value = defineModel<number | number[] | null>({ default: null })

const admin = useAdmin()
const tree = useCategoryTree(admin)
const address = useRecordAddress()
useCatalogMessages()

const t = useTranslate('webx-catalog')

type Choice = CategoryNode & { locked?: boolean }

/** The main category, for the list of the others to leave out. */
const main = computed(() => {
  const chosen = address?.values.value.category_id

  return typeof chosen === 'number' ? chosen : null
})

const nodes = computed<Choice[]>(() => {
  const mark = (list: CategoryNode[]): Choice[] =>
    list.map((node) => ({
      ...node,
      locked: props.multiple && node.id === main.value,
      children: mark(node.children ?? []),
    }))

  return mark(tree.nodes.value ?? [])
})

const model = computed<TreeSelectValue>({
  get: () => {
    if (props.multiple) return Array.isArray(value.value) ? value.value : []

    return typeof value.value === 'number' ? value.value : null
  },
  set: (next) => {
    if (props.multiple) {
      const ids = (Array.isArray(next) ? next : []).map(Number)
      value.value = ids.filter((id) => id !== main.value)

      return
    }

    value.value = next === null || next === undefined || Array.isArray(next) ? null : Number(next)
  },
})

onMounted(() => {
  // A tree that did not arrive leaves the chosen ids where they are, drawn by number; the form
  // still saves what it opened with.
  void tree.load().catch(() => undefined)
})
</script>

<template>
  <wx-tree-select
    v-bind="$attrs"
    v-model="model"
    class="wx-catalog-category"
    :nodes="nodes"
    :multiple="props.multiple"
    :check-strictly="props.multiple"
    node-key="id"
    label-key="name"
    children-key="children"
    disabled-key="locked"
    :show-path="!props.multiple"
    separator=" / "
    filterable
    clearable
    default-expand-all
    teleport
    :placeholder="props.multiple ? t('panel.pick-categories') : t('panel.pick-category')"
  />
</template>
