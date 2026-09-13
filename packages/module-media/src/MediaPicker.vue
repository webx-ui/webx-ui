<script setup lang="ts">
import { useTranslate } from '@webx-ui/admin'
import { WxDialog } from '@webx-ui/core'
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
  }>(),
  { accept: 'image', title: undefined },
)

const emit = defineEmits<{ pick: [file: MediaFile]; cancel: [] }>()

const open = defineModel<boolean>({ default: true })

const t = useTranslate('webx-media')
</script>

<template>
  <wx-dialog v-model="open" size="lg" :title="title ?? t('module.title')" @close="emit('cancel')">
    <div class="wx-media-picker">
      <media-manager picking :accept="accept" @pick="(file) => emit('pick', file)" />
    </div>
  </wx-dialog>
</template>

<style>
.wx-media-picker {
  height: min(70vh, 560px);
  min-height: 0;
}
</style>
