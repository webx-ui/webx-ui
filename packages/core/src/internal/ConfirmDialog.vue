<script setup lang="ts">
import WxButton from '../components/Button/Button.vue'
import WxDialog from '../components/Dialog/Dialog.vue'
import { useModal } from '../composables/useModal'
import type { ButtonType } from '../components/Button/types'

defineOptions({ name: 'WxConfirmDialog' })

withDefaults(
  defineProps<{
    title?: string
    message?: string
    confirmText?: string
    cancelText?: string
    tone?: ButtonType
    width?: number | string
  }>(),
  {
    title: 'Are you sure?',
    message: undefined,
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    tone: 'primary',
    width: 420,
  },
)

const { open, resolve, dismiss } = useModal<boolean>()
</script>

<template>
  <wx-dialog
    v-model:open="open"
    :title="title"
    :width="width"
    :close-on-overlay="false"
    role="alertdialog"
  >
    <p v-if="message" class="wx-confirm__message">{{ message }}</p>

    <template #footer>
      <wx-button variant="outline" @click="dismiss()">{{ cancelText }}</wx-button>
      <wx-button :type="tone" @click="resolve(true)">{{ confirmText }}</wx-button>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-confirm__message {
  margin: 0;
  color: var(--wx-text-default);
  font-size: var(--wx-font-size-md);
  line-height: var(--wx-font-line-height-normal);
}
</style>
