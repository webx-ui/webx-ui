<script setup lang="ts">
import { ref } from 'vue'
import { WxColorPicker } from '@webx-ui/core'

const brand = ref('#427edd')
const accent = ref<string | null>(null)
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">With presets</span>
      <wx-color-picker
        v-model="brand"
        clearable
        :presets="['#427edd', '#21c36d', '#ff9f43', '#f14646', '#21262c', '#ffffff']"
      />
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 13px">
        Model: <code>{{ brand ?? 'null' }}</code>
      </p>
    </div>

    <div>
      <span class="wx-demo__label">Empty, and sizes</span>
      <div class="wx-demo__stack">
        <wx-color-picker v-model="accent" placeholder="#000000" />
        <wx-color-picker model-value="#21c36d" size="sm" />
        <wx-color-picker model-value="#f14646" size="lg" />
        <wx-color-picker model-value="#427edd" disabled />
      </div>
    </div>
  </div>
</template>
