<script setup lang="ts">
import { ref } from 'vue'
import { WxButton, WxCard, WxLoading, WxSwitch } from '@webx-ui/core'

const loading = ref(true)
const blur = ref(false)
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div class="wx-demo__row">
      <wx-switch v-model="loading" label="Loading" />
      <wx-switch v-model="blur" label="Blur what is underneath" />
    </div>

    <wx-loading :loading="loading" :blur="blur" text="Fetching orders">
      <wx-card title="Orders" bordered shadow="never">
        <p>Three orders this week, two of them paid.</p>
        <wx-button size="sm" variant="outline">Open</wx-button>
      </wx-card>
    </wx-loading>

    <div>
      <span class="wx-demo__label">On its own, at three sizes</span>
      <div class="wx-demo__row">
        <wx-loading loading size="sm" class="bare" />
        <wx-loading loading size="md" class="bare" />
        <wx-loading loading size="lg" class="bare" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.wx-demo__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-16);
}

.bare {
  width: 72px;
  height: 72px;
}
</style>
