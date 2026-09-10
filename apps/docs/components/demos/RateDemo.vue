<script setup lang="ts">
import { ref } from 'vue'
import { WxRate } from '@webx-ui/core'

const rating = ref(3)
const halfRating = ref(3.5)
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Whole stars</span>
      <wx-rate v-model="rating" show-value />
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 13px">
        Model: <code>{{ rating }}</code> — click the current value to clear it
      </p>
    </div>

    <div>
      <span class="wx-demo__label">Halves</span>
      <wx-rate v-model="halfRating" allow-half show-value />
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 13px">
        Model: <code>{{ halfRating }}</code> — click the left side of a star
      </p>
    </div>

    <div>
      <span class="wx-demo__label">Sizes, read-only, ten stars</span>
      <div class="wx-demo__stack">
        <wx-rate :model-value="4" size="sm" readonly />
        <wx-rate :model-value="4" size="md" readonly />
        <wx-rate :model-value="4" size="lg" readonly />
        <wx-rate :model-value="7" :max="10" readonly show-value />
      </div>
    </div>
  </div>
</template>
