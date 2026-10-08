<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { toast, WxEmpty, WxFileCard, WxSelectionArea } from '@webx-ui/core'
import type { MediaApi } from './api'
import { startDrag } from './dragging'
import { details, usePanelLocale } from './format'
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
  /**
   * Cards can be dragged onto a folder of the tree — the selection, when the card is in it.
   * A rubber band then starts from the gaps between cards, not from a card.
   */
  draggable?: boolean
}>()

const selected = defineModel<number[]>('selected', { default: () => [] })

const emit = defineEmits<{
  open: [file: MediaFile]
  rename: [file: MediaFile, name: string]
  edit: [file: MediaFile]
  remove: [file: MediaFile]
}>()

const t = useTranslate('webx-media')
const locale = usePanelLocale()

const empty = computed(() =>
  props.query ? t('manager.empty-search', { query: props.query }) : t('manager.empty'),
)

/**
 * The selection goes when the card is in it; a card outside it goes alone, and becomes the
 * selection — what is moving is then what is highlighted.
 */
function dragStart(file: MediaFile, event: DragEvent): void {
  if (!selected.value.includes(file.id)) {
    selected.value = [file.id]
  }

  startDrag(event, [...selected.value])
}

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
    :drag-items="draggable"
  >
    <wx-file-card
      v-for="file in files"
      :key="file.id"
      v-wx-select="file.id"
      class="wx-media-grid__card"
      :name="file.name"
      :extension="file.extension"
      show-extension
      :title="details(file, locale())"
      :url="file.url"
      :thumbnail="api.thumb(file, 320, 320) ?? undefined"
      :type="file.mime"
      :selected="isSelected(file.id)"
      renamable
      :editable="file.editable"
      removable
      copyable
      actions-menu
      :rename-label="t('manager.rename')"
      :edit-label="t('manager.edit')"
      :remove-label="t('manager.delete')"
      :confirm-remove="false"
      :more-label="t('manager.more')"
      :actions-label="t('manager.actions-for', { name: file.name })"
      :draggable="draggable ? 'true' : undefined"
      :cancel-label="t('manager.cancel')"
      :save-label="t('manager.save')"
      :download-url="file.source ?? file.url"
      :copy-label="t('manager.copy-link')"
      :download-label="t('manager.download')"
      :copied-label="t('manager.link-copied')"
      @rename="(name) => emit('rename', file, name)"
      @edit="emit('edit', file)"
      @remove="emit('remove', file)"
      @copy="copied"
      @copy-error="copyFailed(file)"
      @dblclick="emit('open', file)"
      @dragstart="dragStart(file, $event)"
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

/*
 * A touch screen shows a card's actions without a hover, and on a phone-sized card they sit
 * right over the glyph — and over the extension under it, which for a PDF or a HEIC is the one
 * thing that says what the file is. Smaller and in the bottom corner — where a picture carries
 * its badge — it clears them.
 */
@media (hover: none) {
  .wx-media--compact .wx-media-grid .wx-file-card {
    --wx-file-card-glyph: 20px;
  }

  .wx-media--compact .wx-media-grid .wx-file-card__preview {
    align-items: flex-end;
    justify-content: flex-start;
  }

  .wx-media--compact .wx-media-grid .wx-file-card__file {
    align-items: flex-start;
  }
}

/*
 * On a touch screen the card's menu button is grown to 44 px, the size a finger needs — on a
 * tile a hundred pixels wide that is half the picture. Here it is drawn small and the 44 px stay
 * as an invisible margin around it: as easy to hit, and the photo is visible again.
 */
@media (pointer: coarse) {
  .wx-media-grid .wx-file-card__actions .wx-actions__menu .wx-action {
    --wx-action-size: 28px;

    position: relative;
  }

  .wx-media-grid .wx-file-card__actions .wx-actions__menu .wx-action::after {
    content: '';
    position: absolute;
    inset: -8px;
  }
}
</style>
