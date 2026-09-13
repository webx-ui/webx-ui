<script setup lang="ts">
import { computed, ref } from 'vue'
import { useTranslate } from '@webx-ui/admin'
import { useModal, WxButton, WxDialog, WxSelect, WxSpace } from '@webx-ui/core'
import type { MediaDirectory } from './types'

/**
 * Where to put the selected files.
 *
 * A list of folders rather than a box to type in: an editor knows a folder by its name and its
 * place in the tree, and has no reason to ever learn that it also has a number.
 */
const props = defineProps<{
  directories: MediaDirectory[]
  /** The folder they are in now, which is not worth offering. */
  from?: number | null
  count: number
}>()

const { open, resolve, dismiss } = useModal<number>()

const t = useTranslate('webx-media')

const options = computed(() =>
  flatten(props.directories).filter((option) => option.value !== props.from),
)
const target = ref<number | null>(options.value[0]?.value ?? null)

function flatten(directories: MediaDirectory[]): { value: number; label: string }[] {
  return directories.flatMap((directory) => [
    {
      value: directory.id,
      // Indented, so the tree is still readable once it is a flat list.
      label:
        ' '.repeat(directory.depth * 3) + (directory.is_root ? t('manager.root') : directory.title),
    },
    ...flatten(directory.children ?? []),
  ])
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('manager.move-to')" :width="420">
    <wx-select v-model="target" :options="options" />

    <template #footer>
      <wx-space size="6">
        <wx-button variant="outline" @click="dismiss()">{{ t('manager.cancel') }}</wx-button>
        <wx-button type="primary" :disabled="target === null" @click="resolve(target!)">
          {{ t('manager.move') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>
