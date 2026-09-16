<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useAdmin, useTranslate } from '@webx-ui/module-admin'
import { WxTreeSelect, type TreeNode } from '@webx-ui/core'
import { createPagesApi } from './api'
import { usePagesMessages } from './i18n'
import type { PageRow } from './types'

/**
 * A page chosen out of the tree — where a new page goes, or where a moved one lands.
 *
 * Lazy, like the section's own list: a catalogue is fetched a level at a time, and a picker
 * that pulled the whole tree down to offer one parent would be the one screen of the section
 * that does not.
 */
const props = withDefaults(
  defineProps<{
    /** Pages that may not be chosen — the page being moved, above all. */
    exclude?: number[]
    placeholder?: string
    /** The page the value names, with its ancestors, so a lazy tree can show it unopened. */
    selected?: PageRow[]
  }>(),
  { exclude: () => [], placeholder: undefined, selected: () => [] },
)

const value = defineModel<number | null>({ default: null })

const context = useAdmin()
const api = createPagesApi(context)
usePagesMessages()

const t = useTranslate('webx-pages')

const nodes = ref<TreeNode[]>([])

/**
 * The home page is the root of the picker as well as of the tree: everything on the site is
 * inside it, so "at the top level" and "inside the home page" are the same choice, and offering
 * both would be offering one thing twice.
 */
onMounted(async () => {
  const level = await api.list()

  if (level.home)
    nodes.value = [
      toNode(
        level.home,
        level.items.map((page) => toNode(page)),
      ),
    ]
})

function toNode(page: PageRow, children?: TreeNode[]): TreeNode {
  return {
    id: page.id,
    label: page.is_home ? t('pages.home') : page.title,
    disabled: props.exclude.includes(page.id),
    leaf: page.children_count === 0,
    ...(children ? { children } : {}),
  }
}

async function load(node: TreeNode): Promise<TreeNode[]> {
  const level = await api.list({ parent: Number(node.id) })

  return level.items.map((page) => toNode(page))
}
</script>

<template>
  <wx-tree-select
    v-model="value"
    :nodes="nodes"
    lazy
    :load="load"
    show-path
    clearable
    :selected-path="
      props.selected.map((page) => ({
        id: page.id,
        label: page.is_home ? t('pages.home') : page.title,
      }))
    "
    :placeholder="props.placeholder ?? t('page.field-parent')"
    :aria-label="t('page.field-parent')"
    teleport
  />
</template>
