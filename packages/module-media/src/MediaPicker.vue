<script setup lang="ts">
import { useTranslate } from '@webx-ui/module-admin'
import { useModal, WxDialog } from '@webx-ui/core'
import MediaManager from './MediaManager.vue'
import type { MediaFile, MediaKind } from './types'

/**
 * The library as a dialog, for a form that needs a picture.
 *
 * The same manager, not a second one: a picker that browses differently from the manager is a
 * second thing to learn and a second thing to keep working.
 */
withDefaults(
  defineProps<{
    accept?: MediaKind | null
    title?: string
    /** Opened to manage the library rather than to choose from it. */
    manage?: boolean
  }>(),
  { accept: 'image', title: undefined, manage: false },
)

const { open, resolve } = useModal<MediaFile>()

const t = useTranslate('webx-media')
</script>

<template>
  <wx-dialog v-model:open="open" :title="title ?? t('module.title')" :width="960">
    <div class="wx-media-picker">
      <media-manager
        :picking="!manage"
        :accept="manage ? null : accept"
        @pick="(file) => resolve(file)"
      />
    </div>
  </wx-dialog>
</template>

<style>
.wx-media-picker {
  height: min(70vh, 560px);
  min-height: 0;
}
</style>
