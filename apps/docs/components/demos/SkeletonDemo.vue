<script setup lang="ts">
import { ref } from 'vue'
import { WxButton, WxCard, WxSkeleton, WxSkeletonItem, WxSwitch } from '@webx-ui/core'

const loading = ref(true)
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <wx-switch v-model="loading" label="Loading" />

    <div>
      <span class="wx-demo__label">Rows, with a face and a heading</span>
      <wx-card bordered shadow="never">
        <wx-skeleton :loading="loading" avatar title :rows="3">
          <div class="person">
            <strong>Ada Lovelace</strong>
            <p>Analyst, mathematician, and the first to write a program for a machine.</p>
          </div>
        </wx-skeleton>
      </wx-card>
    </div>

    <div>
      <span class="wx-demo__label">A shape of your own</span>
      <wx-card bordered shadow="never">
        <wx-skeleton :loading="loading">
          <template #template>
            <div class="card-shape">
              <wx-skeleton-item variant="image" />
              <wx-skeleton-item variant="title" />
              <wx-skeleton-item variant="text" />
              <wx-skeleton-item variant="button" />
            </div>
          </template>

          <div class="card-shape">
            <img class="shot" src="https://picsum.photos/seed/wx/320/140" alt="" />
            <strong>Nova Build</strong>
            <p>Three orders this month.</p>
            <wx-button size="sm">Open</wx-button>
          </div>
        </wx-skeleton>
      </wx-card>
    </div>
  </div>
</template>

<style scoped>
.person p,
.card-shape p {
  margin: var(--wx-space-4) 0 0;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.card-shape {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--wx-space-8);
  max-width: 320px;
}

.shot {
  width: 100%;
  height: 140px;
  border-radius: var(--wx-radius-sm);
  object-fit: cover;
}
</style>
