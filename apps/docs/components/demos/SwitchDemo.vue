<script setup lang="ts">
import { ref } from 'vue'
import { WxSwitch } from '@webx-ui/core'

const published = ref(true)
const indexing = ref('no')
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div class="wx-demo__row">
      <span class="wx-demo__label">Sizes</span>
      <wx-switch :model-value="true" size="sm" label="Small" />
      <wx-switch :model-value="true" size="md" label="Medium" />
      <wx-switch :model-value="true" size="lg" label="Large" />
    </div>

    <div class="wx-demo__row">
      <span class="wx-demo__label">States</span>
      <wx-switch v-model="published" label="Published" />
      <wx-switch :model-value="false" disabled label="Disabled off" />
      <wx-switch :model-value="true" disabled label="Disabled on" />
    </div>

    <div>
      <span class="wx-demo__label">Custom values</span>
      <wx-switch v-model="indexing" active-value="yes" inactive-value="no" label="Allow indexing" />
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 13px">
        Value: {{ indexing }}
      </p>
    </div>
  </div>
</template>
