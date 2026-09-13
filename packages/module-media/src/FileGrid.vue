<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate } from '@webx-ui/admin'
import { WxEmpty, WxFileCard, WxSelectionArea } from '@webx-ui/core'
import type { MediaApi } from './api'
import type { MediaFile } from './types'

/**
 * The files, as cards, with a rubber-band selection over them.
 *
 * Previews come from the server's thumbnail endpoint rather than the file itself: a grid of
 * forty photographs is forty full-size images otherwise, and on a phone that is the difference
 * between a screen and a wait.
 */
const props = defineProps<{
  files: MediaFile[]
  api: MediaApi
  query?: string
  /** A picker takes one file, or a few; the manager selects to act on a batch. */
  single?: boolean
}>()

const selected = defineModel<number[]>('selected', { default: () => [] })

const emit = defineEmits<{
  open: [file: MediaFile]
  rename: [file: MediaFile, name: string]
  edit: [file: MediaFile]
  remove: [file: MediaFile]
}>()

const t = useTranslate('webx-media')

const empty = computed(() =>
  props.query ? t('manager.empty-search', { query: props.query }) : t('manager.empty'),
)
</script>

<template>
  <wx-selection-area
    v-if="files.length > 0"
    v-slot="{ isSelected }"
    v-model="selected"
    class="wx-media-grid"
    :multiple="!single"
  >
    <wx-file-card
      v-for="file in files"
      :key="file.id"
      v-wx-select="file.id"
      class="wx-media-grid__card"
      :name="file.name"
      :url="file.url"
      :thumbnail="api.thumb(file, 320, 320) ?? undefined"
      :type="file.mime"
      :selected="isSelected(file.id)"
      renamable
      :editable="file.editable"
      removable
      copyable
      @rename="(name) => emit('rename', file, name)"
      @edit="emit('edit', file)"
      @remove="emit('remove', file)"
      @dblclick="emit('open', file)"
    />
  </wx-selection-area>

  <wx-empty v-else :description="empty" />
</template>

<style>
.wx-media-grid {
  /* A rubber band over names would otherwise select the names. */
  user-select: none;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: var(--wx-space-12);
  align-content: start;
  min-height: 0;
  overflow: auto;
  padding: var(--wx-space-2);
}

@container (max-width: 480px) {
  .wx-media-grid {
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: var(--wx-space-8);
  }
}
</style>
