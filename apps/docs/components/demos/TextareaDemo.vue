<script setup lang="ts">
import { ref } from 'vue'
import { WxTextarea } from '@webx-ui/core'

const summary = ref('WebX UI is a Vue 3 design system for admin panels.')
const notes = ref('')
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Fixed rows, counter</span>
      <wx-textarea v-model="summary" :rows="3" :maxlength="200" show-count />
    </div>

    <div>
      <span class="wx-demo__label">Autosize between 2 and 6 rows</span>
      <wx-textarea
        v-model="notes"
        :autosize="{ minRows: 2, maxRows: 6 }"
        placeholder="Type several lines and watch it grow"
      />
    </div>

    <div>
      <span class="wx-demo__label">States</span>
      <wx-textarea model-value="Invalid value" status="error" :rows="2" />
      <wx-textarea
        model-value="Read-only"
        readonly
        :rows="2"
        resize="none"
        style="margin-top: 8px"
      />
    </div>
  </div>
</template>
