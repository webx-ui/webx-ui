<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate } from '@webx-ui/admin'
import { toast, WxEmpty, WxFileCard, WxSelectionArea } from '@webx-ui/core'
import type { MediaApi } from './api'
import type { MediaFile } from './types'

/**
 * The files, as cards, with a rubber band over them.
 *
 * Previews come from the server's thumbnail endpoint rather than the files themselves: a grid of
 * forty photographs is forty full-size images otherwise, and on a phone that is the difference
 * between a screen and a wait.
 */
const props = defineProps<{
  files: MediaFile[]
  api: MediaApi
  query?: string
  /** A picker takes one file; the manager selects to act on a batch. */
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

function copied(): void {
  toast.success(t('manager.link-copied'))
}

/**
 * The clipboard is not always there: it is missing outside a secure context, and an iframe has
 * to be granted it. Rather than let the card fail silently, try the old way — and if that is
 * refused too, put the address on screen, where it can at least be copied by hand.
 */
function copyFailed(file: MediaFile): void {
  const area = document.createElement('textarea')
  area.value = file.url
  area.setAttribute('readonly', '')
  area.style.position = 'fixed'
  area.style.opacity = '0'
  document.body.appendChild(area)
  area.select()

  let done = false

  try {
    done = document.execCommand('copy')
  } catch {
    done = false
  }

  area.remove()

  if (done) {
    copied()

    return
  }

  toast.danger(`${t('errors.copy')} ${file.url}`, { duration: 0 })
}
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
      :rename-label="t('manager.rename')"
      :edit-label="t('manager.edit')"
      :remove-label="t('manager.delete')"
      :remove-confirm-text="t('dialogs.delete-file', { name: file.name })"
      :cancel-label="t('manager.cancel')"
      :save-label="t('manager.save')"
      :copy-label="t('manager.copy-link')"
      :copied-label="t('manager.link-copied')"
      @rename="(name) => emit('rename', file, name)"
      @edit="emit('edit', file)"
      @remove="emit('remove', file)"
      @copy="copied"
      @copy-error="copyFailed(file)"
      @dblclick="emit('open', file)"
    />
  </wx-selection-area>

  <wx-empty v-else :description="empty" />
</template>

<style>
.wx-media-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: var(--wx-space-12);
  align-content: start;
  min-height: 0;
  overflow: auto;
  padding: var(--wx-space-2);
  /* A rubber band over names would otherwise select the names. */
  user-select: none;
}

.wx-media--compact .wx-media-grid {
  grid-template-columns: repeat(auto-fill, minmax(104px, 1fr));
  gap: var(--wx-space-8);
}
</style>
