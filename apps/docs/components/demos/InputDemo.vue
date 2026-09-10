<script setup lang="ts">
import { computed, ref } from 'vue'
import { WxInput } from '@webx-ui/core'

const title = ref('Landing page')
const slug = ref('')
const email = ref('not-an-email')

const emailStatus = computed(() => (email.value.includes('@') ? 'success' : 'error'))
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Clearable, with counter</span>
      <wx-input v-model="title" placeholder="Title" clearable show-count :maxlength="60" />
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 13px">
        Value: {{ title || '—' }}
      </p>
    </div>

    <div>
      <span class="wx-demo__label">Sizes</span>
      <div class="wx-demo__row">
        <wx-input v-model="slug" size="sm" placeholder="Small" />
        <wx-input v-model="slug" size="md" placeholder="Medium" />
        <wx-input v-model="slug" size="lg" placeholder="Large" />
      </div>
    </div>

    <div>
      <span class="wx-demo__label">Prefix, suffix, validation state</span>
      <wx-input v-model="email" :status="emailStatus" placeholder="you@example.com">
        <template #prefix>@</template>
        <template #suffix>email</template>
      </wx-input>
    </div>

    <div>
      <span class="wx-demo__label">Disabled &amp; readonly</span>
      <div class="wx-demo__row">
        <wx-input model-value="Disabled" disabled />
        <wx-input model-value="Readonly" readonly />
      </div>
    </div>
  </div>
</template>
