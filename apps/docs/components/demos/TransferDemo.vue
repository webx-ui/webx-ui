<script setup lang="ts">
import { ref } from 'vue'
import { WxTransfer, type TransferItem } from '@webx-ui/core'

const permissions: TransferItem[] = [
  { value: 'posts.read', label: 'Read posts' },
  {
    value: 'posts.write',
    label: 'Write posts',
    description: 'Create and edit, without publishing',
  },
  { value: 'posts.publish', label: 'Publish posts' },
  { value: 'media.upload', label: 'Upload media' },
  { value: 'media.delete', label: 'Delete media' },
  { value: 'orders.read', label: 'Read orders' },
  { value: 'orders.refund', label: 'Refund orders', description: 'Money leaves over this one' },
  { value: 'users.manage', label: 'Manage users' },
  { value: 'settings.write', label: 'Change settings', disabled: true },
]

const granted = ref<string[]>(['posts.read', 'media.upload'])

const narrow = ref<string[]>(['b'])

const letters: TransferItem[] = [
  { value: 'a', label: 'Alternator Belt' },
  { value: 'b', label: 'Drive Pump Belt' },
  { value: 'c', label: 'Water Coolant Tank Cap' },
]
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Two lists and what moves between them</span>

      <wx-transfer
        v-model="granted"
        :items="permissions"
        :titles="['Available', 'Granted']"
        searchable
      />

      <span class="wx-demo__note">Granted: {{ granted.join(', ') || 'nothing' }}</span>
    </div>

    <div>
      <span class="wx-demo__label">In a panel too narrow for two columns</span>

      <div class="narrow">
        <wx-transfer v-model="narrow" :items="letters" :height="140" />
      </div>

      <span class="wx-demo__note">
        The container decides, not the window — this one is 360px wide inside a page that is not.
      </span>
    </div>
  </div>
</template>

<style scoped>
.narrow {
  width: 360px;
  max-width: 100%;
}
</style>
