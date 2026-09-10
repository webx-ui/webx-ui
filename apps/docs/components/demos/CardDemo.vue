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
          <wx-button type="success">Save</wx-button>
        </div>
      </template>
    </wx-card>

    <wx-card title="With a sidebar">
      <template #sidebar>
        <nav style="display: flex; flex-direction: column; gap: 4px">
          <wx-button variant="text" block style="justify-content: flex-start">General</wx-button>
          <wx-button variant="text" block style="justify-content: flex-start">SEO</wx-button>
          <wx-button variant="text" block style="justify-content: flex-start">Access</wx-button>
        </nav>
      </template>

      <p style="margin: 0 0 12px">
        The sidebar column keeps its width until the card itself drops below 560px — then the two
        columns stack.
      </p>
      <wx-input model-value="" placeholder="Title" />
    </wx-card>

    <wx-card shadow="hover">Body-only card, shadow on hover.</wx-card>

    <wx-card title="Compact" padding="sm" bordered>Bordered with small padding.</wx-card>
  </div>
</template>
