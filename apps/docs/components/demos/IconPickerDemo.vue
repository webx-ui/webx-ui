<script setup lang="ts">
import { ref } from 'vue'
import { WxIconPicker } from '@webx-ui/core'

const icon = ref<string | null>('grid')
const empty = ref<string | null>(null)
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Pick one</span>
      <wx-icon-picker v-model="icon" clearable />
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 13px">
        Model: <code>{{ icon ?? 'null' }}</code>
      </p>
    </div>

    <div>
      <span class="wx-demo__label">Empty, and sizes</span>
      <div class="wx-demo__stack">
        <wx-icon-picker v-model="empty" placeholder="Search icons" />
        <wx-icon-picker model-value="search" size="sm" />
        <wx-icon-picker model-value="trash" size="lg" />
        <wx-icon-picker model-value="image" disabled />
      </div>
    </div>
  </div>
</template>
