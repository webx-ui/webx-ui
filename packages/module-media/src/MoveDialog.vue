<script setup lang="ts">
import { ref } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { useModal, WxButton, WxDialog, WxSpace, WxTree } from '@webx-ui/core'
import type { MediaDirectory } from './types'

/**
 * Where to put the selected files.
 *
 * The tree itself rather than a list of names: a folder is known by its place as much as by its
 * name — two folders called «Photos» are two places — and a nested one read without its parent
 * is a guess. The folder they are in now is there, marked and not to be chosen.
 */
const props = defineProps<{
  directories: MediaDirectory[]
  /** The folder they are in now. */
  from?: number | null
  count: number
}>()

const { open, resolve, dismiss } = useModal<number>()

const t = useTranslate('webx-media')

type Node = { id: number; label: string; disabled: boolean; children: Node[] }

const nodes = ref<Node[]>(props.directories.map(node))
const expanded = ref<number[]>(keysOf(props.directories))
const target = ref<number | null>(null)

function node(directory: MediaDirectory): Node {
  const title = directory.is_root ? t('manager.root') : directory.title
  const here = directory.id === props.from

  return {
    id: directory.id,
    label: here ? `${title} — ${t('manager.current')}` : title,
    disabled: here,
    children: (directory.children ?? []).map(node),
  }
}

function keysOf(directories: MediaDirectory[]): number[] {
  return directories.flatMap((directory) => [directory.id, ...keysOf(directory.children ?? [])])
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('manager.move-to')" :width="420">
    <div class="wx-media-move">
      <wx-tree
        v-model:selected="target"
        v-model:expanded="expanded"
        :model-value="nodes"
        node-key="id"
        show-lines
        :aria-label="t('manager.move-to')"
      />
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('manager.cancel') }}</wx-button>
        <wx-button type="primary" :disabled="target === null" @click="resolve(target!)">
          {{ t('manager.move') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style>
.wx-media-move {
  max-height: min(360px, 50vh);
  overflow: auto;
}
</style>
