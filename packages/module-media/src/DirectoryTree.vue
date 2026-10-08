<script setup lang="ts">
import { ref, watch } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { WxSkeleton, WxTree, type TreeDropEvent } from '@webx-ui/core'
import { carriesLibraryFiles, droppedIds } from './dragging'
import type { MediaDirectory } from './types'

/**
 * The folders, on the left.
 *
 * The root carries a translated label rather than the title it was given in the database: it is
 * the library itself, and an editor should not have to know it is a row.
 *
 * Two drags land here. A folder dragged inside the tree is the tree's own and moves the folder;
 * files dragged from the grid are ours, and drop into the folder under the pointer.
 */
const props = defineProps<{
  directories: MediaDirectory[]
  /** Not answered yet: a placeholder, rather than a tree that says it is empty. */
  loading?: boolean
}>()

const selected = defineModel<number | null>('selected', { default: null })

const emit = defineEmits<{
  move: [id: number, parentId: number]
  /** Files from the grid dropped onto a folder. */
  'move-files': [ids: number[], directoryId: number]
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

/*
 * The row files would land in. Marked on the row itself rather than through the tree: the
 * tree's own highlight belongs to its own drag, which knows nothing of files.
 */
const target = ref<HTMLElement | null>(null)

function mark(row: HTMLElement | null): void {
  if (target.value === row) return

  target.value?.classList.remove('is-file-target')
  row?.classList.add('is-file-target')
  target.value = row
}

function rowOf(event: DragEvent): HTMLElement | null {
  return (event.target as HTMLElement | null)?.closest<HTMLElement>('[data-key]') ?? null
}

function filesOver(event: DragEvent): void {
  if (!carriesLibraryFiles(event)) return

  const row = rowOf(event)

  mark(row)

  if (row) {
    // Taking the event is what makes the row a place to drop on.
    event.preventDefault()

    if (event.dataTransfer) event.dataTransfer.dropEffect = 'move'
  }
}

function filesLeave(event: DragEvent): void {
  const into = event.relatedTarget

  if (!(into instanceof Element) || !(event.currentTarget as HTMLElement).contains(into)) {
    mark(null)
  }
}

function filesDrop(event: DragEvent): void {
  if (!carriesLibraryFiles(event)) return

  const row = rowOf(event)

  mark(null)

  if (!row) return

  event.preventDefault()

  const ids = droppedIds(event)

  if (ids.length > 0) {
    emit('move-files', ids, Number(row.dataset.key))
  }
}
</script>

<template>
  <wx-skeleton v-if="loading" class="wx-media-tree" :rows="3" :title="false" animated />

  <div
    v-else
    class="wx-media-tree"
    @dragover="filesOver"
    @dragleave="filesLeave"
    @drop="filesDrop"
  >
    <wx-tree
      v-model:selected="selected"
      v-model:expanded="expanded"
      v-model="nodes"
      node-key="id"
      draggable
      show-lines
      :empty-text="t('manager.no-folders')"
      @drop="onDrop"
    />
  </div>
</template>

<style>
.wx-media-tree {
  min-width: 0;
  overflow: auto;
}

.wx-media-tree .wx-tree__row.is-file-target {
  background: var(--wx-color-primary-soft);
  box-shadow: inset 0 0 0 2px var(--wx-color-primary);
}
</style>
