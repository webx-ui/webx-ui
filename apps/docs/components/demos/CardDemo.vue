<script setup lang="ts">
import { WxButton, WxCard, WxInput } from '@webx-ui/core'
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <wx-card title="Page settings" shadow="always">
      <template #extra>Draft</template>
      <p style="margin: 0 0 12px">Cards group a form, a table or any block of an admin screen.</p>
      <wx-input model-value="About us" placeholder="Title" clearable />
      <template #footer>
        <div style="display: flex; gap: 8px; justify-content: flex-end">
          <wx-button variant="text">Cancel</wx-button>
          <wx-button type="primary">Save</wx-button>
        </div>
      </template>
    </wx-card>

    <wx-card shadow="hover">Body-only card, shadow on hover.</wx-card>

    <wx-card title="Compact" padding="sm" bordered>Bordered with small padding.</wx-card>
  </div>
</template>
