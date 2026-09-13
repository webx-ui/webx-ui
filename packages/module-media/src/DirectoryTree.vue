<script setup lang="ts">
import { ref, watch } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { WxTree, type TreeDropEvent } from '@webx-ui/core'
import type { MediaDirectory } from './types'

/**
 * The folders, on the left.
 *
 * The root carries a translated label rather than the title it was given in the database: it is
 * the library itself, and an editor should not have to know it is a row.
 */
const props = defineProps<{
  directories: MediaDirectory[]
}>()

const selected = defineModel<number | null>('selected', { default: null })

const emit = defineEmits<{
  move: [id: number, parentId: number]
}>()

const t = useTranslate('webx-media')

type Node = MediaDirectory & { label: string }

// The tree rearranges this list itself when something is dropped, and then says so; the answer
// from the server replaces it a moment later. A computed would have nothing to rearrange.
const nodes = ref<Node[]>([])
const expanded = ref<number[]>([])

watch(
  () => props.directories,
  (directories) => {
    nodes.value = directories.map(label)
    expanded.value = keysOf(directories)
  },
  { immediate: true, deep: true },
)

function label(directory: MediaDirectory): Node {
  return {
    ...directory,
    label: directory.is_root ? t('manager.root') : directory.title,
    children: (directory.children ?? []).map(label),
  }
}

function keysOf(directories: MediaDirectory[]): number[] {
  return directories.flatMap((directory) => [directory.id, ...keysOf(directory.children ?? [])])
}

function onDrop(event: TreeDropEvent<Node>): void {
  const parent = event.zone === 'inside' ? event.target : event.parent

  if (parent) {
    emit('move', event.node.id, parent.id)
  }
}
</script>

<template>
  <wx-tree
    v-model:selected="selected"
    v-model:expanded="expanded"
    v-model="nodes"
    class="wx-media-tree"
    node-key="id"
    draggable
    show-lines
    @drop="onDrop"
  />
</template>

<style>
.wx-media-tree {
  min-width: 0;
  overflow: auto;
}
</style>
