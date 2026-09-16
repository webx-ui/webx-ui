<script setup lang="ts">
import { ref } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { useModal, WxButton, WxDialog, WxSpace } from '@webx-ui/core'
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
    /** Several files at once, handed back as a list. */
    multiple?: boolean
    /** The most that may be chosen, when choosing several. */
    max?: number | null
  }>(),
  { accept: 'image', title: undefined, manage: false, multiple: false, max: null },
)

const { open, resolve, dismiss } = useModal<MediaFile | MediaFile[]>()

const t = useTranslate('webx-media')

/**
 * What is marked in the grid right now.
 *
 * A dialog that takes several files needs a button to end on, and the button needs to say how
 * many it would hand back — a double-click cannot mean "these ten".
 */
const chosen = ref<MediaFile[]>([])
</script>

<template>
  <wx-dialog v-model:open="open" :title="title ?? t('module.title')" :width="960">
    <div class="wx-media-picker">
      <media-manager
        :picking="!manage"
        :accept="manage ? null : accept"
        :multiple="multiple && !manage"
        :max="max"
        @pick="(file) => resolve(file)"
        @selection="(files) => (chosen = files)"
      />
    </div>

    <template v-if="multiple && !manage" #footer>
      <wx-space size="sm">
        <!-- The count is what tells somebody they have reached the limit: the grid stops
             taking more, and this is where it says so. -->
        <span v-if="max !== null && max > 0" class="wx-media-picker__count">
          {{ t('manager.chosen', { count: chosen.length, max }) }}
        </span>

        <wx-button variant="outline" @click="dismiss()">{{ t('manager.cancel') }}</wx-button>
        <wx-button
          class="wx-media-picker__confirm"
          type="primary"
          :disabled="chosen.length === 0"
          @click="resolve(chosen)"
        >
          {{ t('manager.pick', { count: chosen.length }) }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style>
.wx-media-picker {
  height: min(70vh, 560px);
  min-height: 0;
}

.wx-media-picker__count {
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
  align-self: center;
}
</style>
