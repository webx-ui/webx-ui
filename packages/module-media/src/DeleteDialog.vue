<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { useModal, WxButton, WxDialog } from '@webx-ui/core'
import type { FileInUse, UsagePlace } from './types'

/**
 * The one question before anything leaves the library — a file, a selection, a folder.
 *
 * Asked once, with everything in it: what goes (a folder's counts through its whole subtree)
 * and which of it the site still uses, where. A file in use is not refused here — the person
 * may know the page is being rewritten — but the button then says what it does: «delete
 * anyway», which is the `force` the server waits for.
 */
const props = withDefaults(
  defineProps<{
    title: string
    message?: string
    inUse?: FileInUse[]
  }>(),
  { message: undefined, inUse: () => [] },
)

const { open, resolve, dismiss } = useModal<true>()

const t = useTranslate('webx-media')

/* A few are enough to see what is at stake; the rest is a number. */
const FILES = 5
const PLACES = 3

const shown = computed(() => props.inUse.slice(0, FILES))
const hidden = computed(() => Math.max(0, props.inUse.length - FILES))

function where(place: UsagePlace): string {
  const row = place.id === null ? place.table : `${place.table} #${place.id}`

  return place.label ? `«${place.label}» · ${row}` : row
}
</script>

<template>
  <wx-dialog
    v-model:open="open"
    :title="title"
    :width="480"
    :close-on-overlay="false"
    role="alertdialog"
  >
    <div class="wx-media-delete">
      <p v-if="message" class="wx-media-delete__text">{{ message }}</p>

      <template v-if="inUse.length > 0">
        <p class="wx-media-delete__heading">{{ t('dialogs.in-use') }}</p>

        <ul class="wx-media-delete__files">
          <li v-for="file in shown" :key="file.id">
            <span class="wx-media-delete__name">{{ file.name }}</span>
            <span
              v-for="place in file.used_in.slice(0, PLACES)"
              :key="`${place.table}.${place.column}.${place.id}`"
              class="wx-media-delete__place"
              >{{ where(place) }}</span
            >
            <span v-if="file.used_in.length > PLACES" class="wx-media-delete__place">
              {{ t('dialogs.in-use-more', { count: file.used_in.length - PLACES }) }}
            </span>
          </li>
        </ul>

        <p v-if="hidden > 0" class="wx-media-delete__more">
          {{ t('dialogs.in-use-more', { count: hidden }) }}
        </p>

        <p class="wx-media-delete__warning">{{ t('dialogs.in-use-warning') }}</p>
      </template>
    </div>

    <template #footer>
      <wx-button variant="outline" @click="dismiss()">{{ t('manager.cancel') }}</wx-button>
      <wx-button type="danger" @click="resolve(true)">
        {{ inUse.length > 0 ? t('dialogs.delete-anyway') : t('dialogs.confirm') }}
      </wx-button>
    </template>
  </wx-dialog>
</template>

<style>
.wx-media-delete {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  color: var(--wx-text-default);
  font-size: var(--wx-font-size-md);
  line-height: var(--wx-font-line-height-normal);
}

.wx-media-delete p {
  margin: 0;
}

.wx-media-delete__heading {
  font-weight: var(--wx-font-weight-semibold);
}

.wx-media-delete__files {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
  max-height: 240px;
  margin: 0;
  padding: 0;
  overflow: auto;
  list-style: none;
}

.wx-media-delete__files li {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.wx-media-delete__name {
  font-weight: var(--wx-font-weight-medium);
  overflow-wrap: anywhere;
}

.wx-media-delete__place,
.wx-media-delete__more {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  overflow-wrap: anywhere;
}

.wx-media-delete__warning {
  color: var(--wx-color-danger);
  font-size: var(--wx-font-size-sm);
}
</style>
