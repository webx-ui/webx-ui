<script setup lang="ts">
import { ref } from 'vue'
import { WxButton, WxCard, WxResult, WxSegmented } from '@webx-ui/core'

const status = ref<'success' | 'danger' | '404' | '403'>('success')

const options = [
  { label: 'Success', value: 'success' },
  { label: 'Failed', value: 'danger' },
  { label: '404', value: '404' },
  { label: '403', value: '403' },
]

const copy = {
  success: ['Order placed', 'A receipt is on its way to office@novabuild.example.'],
  danger: ['Payment failed', 'The card was declined. Nothing has been charged.'],
  404: ['Page not found', 'The link may be old, or the record may have been deleted.'],
  403: ['No access', 'Ask an administrator to add you to this workspace.'],
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <wx-segmented v-model="status" :options="options" size="sm" aria-label="Outcome" />

    <wx-card bordered shadow="never" padding="none">
      <wx-result :status="status" :title="copy[status][0]" :subtitle="copy[status][1]">
        <template #actions>
          <wx-button variant="outline">Back to orders</wx-button>
          <wx-button type="primary">Try again</wx-button>
        </template>
      </wx-result>
    </wx-card>
  </div>
</template>
