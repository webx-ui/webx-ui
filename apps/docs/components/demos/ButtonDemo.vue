<script setup lang="ts">
import { ref } from 'vue'
import { WxButton } from '@webx-ui/core'

const clicks = ref(0)
const loading = ref(false)

function load() {
  loading.value = true
  setTimeout(() => (loading.value = false), 1200)
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div class="wx-demo__row">
      <span class="wx-demo__label">Types</span>
      <wx-button>Default</wx-button>
      <wx-button type="primary">Primary</wx-button>
      <wx-button type="success">Success</wx-button>
      <wx-button type="warning">Warning</wx-button>
      <wx-button type="danger">Danger</wx-button>
    </div>

    <div class="wx-demo__row">
      <span class="wx-demo__label">Variants</span>
      <wx-button type="primary" variant="solid">Solid</wx-button>
      <wx-button type="primary" variant="outline">Outline</wx-button>
      <wx-button type="primary" variant="text">Text</wx-button>
    </div>

    <div class="wx-demo__row">
      <span class="wx-demo__label">Sizes &amp; states</span>
      <wx-button type="primary" size="sm">Small</wx-button>
      <wx-button type="primary" size="md">Medium</wx-button>
      <wx-button type="primary" size="lg">Large</wx-button>
      <wx-button type="primary" round>Round</wx-button>
      <wx-button type="primary" disabled>Disabled</wx-button>
      <wx-button type="primary" :loading="loading" @click="load">Load</wx-button>
    </div>

    <div class="wx-demo__row">
      <span class="wx-demo__label">Events</span>
      <wx-button type="primary" @click="clicks++">Clicked {{ clicks }} times</wx-button>
    </div>
  </div>
</template>
