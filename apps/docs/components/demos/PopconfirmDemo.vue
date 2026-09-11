<script setup lang="ts">
import { ref } from 'vue'
import { WxAction, WxButton, WxPopconfirm, useToast } from '@webx-ui/core'

const toast = useToast()
const saving = ref(false)
const open = ref(false)

function slowly() {
  saving.value = true
  setTimeout(() => {
    saving.value = false
    open.value = false
    toast.success('Archived')
  }, 1200)
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Asked where the answer lands</span>
      <div class="wx-demo__row">
        <wx-popconfirm
          title="Delete this order?"
          description="It cannot be brought back."
          confirm-text="Delete"
          confirm-type="danger"
          @confirm="toast('Deleted')"
        >
          <template #trigger><wx-action type="remove" /></template>
        </wx-popconfirm>

        <wx-popconfirm
          title="Send the invoice now?"
          confirm-text="Send"
          @confirm="toast.success('Sent')"
        >
          <template #trigger>
            <wx-button size="sm" variant="outline">Send invoice</wx-button>
          </template>
        </wx-popconfirm>
      </div>
    </div>

    <div>
      <span class="wx-demo__label">An answer that takes a moment</span>
      <wx-popconfirm
        v-model:open="open"
        title="Archive this order?"
        confirm-text="Archive"
        :loading="saving"
        @confirm="slowly"
      >
        <template #trigger><wx-button size="sm" variant="outline">Archive</wx-button></template>
      </wx-popconfirm>
    </div>
  </div>
</template>

<style scoped>
.wx-demo__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-12);
}
</style>
