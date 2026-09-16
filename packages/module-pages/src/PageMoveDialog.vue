<script setup lang="ts">
import { ref } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { useModal, WxAlert, WxButton, WxDialog, WxSpace } from '@webx-ui/core'
import PagePicker from './PagePicker.vue'
import { usePagesMessages } from './i18n'
import type { PageRow } from './types'

/**
 * Where a page goes, chosen from a tree rather than dragged.
 *
 * Dragging is the quick way and it is in the table already; this is the one that works — on a
 * touch screen, and in a catalogue where the page and its new parent are four screens apart.
 *
 * Inside, and only inside. Order among siblings is what dragging is for, and a dialog that also
 * asked "before or after what" would be asking about the one thing nobody opened it for.
 */
const props = defineProps<{ page: PageRow }>()

const { open, resolve, dismiss } = useModal<number>()

usePagesMessages()

const t = useTranslate('webx-pages')

const target = ref<number | null>(props.page.parent_id)
</script>

<template>
  <wx-dialog
    v-model:open="open"
    :title="t('page.move-title', { title: props.page.title })"
    :width="460"
  >
    <wx-space direction="vertical" size="md" fill>
      <wx-alert type="info" variant="soft" :description="t('page.move-help')" />

      <!-- Its own branch is not a place it can go, and the page itself least of all. The rest
           of the branch is refused by the server, which is the only side that knows the whole
           tree without fetching it. -->
      <page-picker v-model="target" :exclude="[props.page.id]" />
    </wx-space>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('page.cancel') }}</wx-button>
        <wx-button
          type="primary"
          :disabled="target === null || target === props.page.parent_id"
          @click="resolve(target!)"
        >
          {{ t('page.move-confirm') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>
