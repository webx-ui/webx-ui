<script setup lang="ts">
import { ref, useTemplateRef } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { useModal, WxButton, WxDialog, WxSpace, WxText } from '@webx-ui/core'
import AdminList from './AdminList.vue'
import { useAuthMessages } from './i18n'
import type { Admin } from './types'

/**
 * The list of administrators as a dialog, resolving with whoever was chosen.
 *
 * The same list as the screen — one component, so that searching and filtering behave the same
 * wherever these people are being looked at.
 */
const props = withDefaults(
  defineProps<{
    multiple?: boolean
    title?: string
    resolveAvatar?: (key: string) => Promise<string | null>
  }>(),
  { multiple: false, title: undefined, resolveAvatar: undefined },
)

const { open, resolve, dismiss } = useModal<Admin[]>()

useAuthMessages()

const t = useTranslate('webx-auth')

const list = useTemplateRef<{ chosen: () => Admin[] }>('list')
const count = ref(0)

/** One is chosen by clicking the row; several are ticked and then confirmed. */
function chosen(admins: Admin[]): void {
  resolve(admins)
}

function track(admins: Admin[]): void {
  count.value = admins.length
}
</script>

<template>
  <wx-dialog
    v-model:open="open"
    :title="title ?? t(multiple ? 'admins.select-many' : 'admins.select-one')"
    :width="880"
  >
    <admin-list
      ref="list"
      picking
      :multiple="multiple"
      :resolve-avatar="props.resolveAvatar"
      @chosen="chosen"
      @selection="track"
    />

    <template v-if="multiple" #footer>
      <wx-space size="sm" align="center">
        <wx-text size="sm" tone="muted">{{ t('admins.chosen', { count }) }}</wx-text>
        <wx-button variant="outline" @click="dismiss()">{{ t('admins.cancel') }}</wx-button>
        <wx-button type="primary" :disabled="count === 0" @click="resolve(list?.chosen() ?? [])">
          {{ t('admins.save') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>
