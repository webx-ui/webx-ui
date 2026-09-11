<script setup lang="ts">
import { ref } from 'vue'
import { WxBadge, WxButton, WxInput, WxSpace } from '@webx-ui/core'

const sizes = ['xs', 'sm', 'md', 'lg', 'xl'] as const

const query = ref('')
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">The spacing scale</span>
      <div class="wx-demo__stack">
        <wx-space v-for="size in sizes" :key="size" :size="size">
          <wx-badge>{{ size }}</wx-badge>
          <wx-button size="sm" variant="outline">One</wx-button>
          <wx-button size="sm" variant="outline">Two</wx-button>
          <wx-button size="sm" variant="outline">Three</wx-button>
        </wx-space>
      </div>
    </div>

    <div>
      <span class="wx-demo__label">A toolbar: filters left, actions right</span>
      <wx-space justify="between" align="center">
        <wx-space size="sm">
          <wx-input v-model="query" size="sm" placeholder="Search" />
          <wx-badge type="primary" round>3 filters</wx-badge>
        </wx-space>
        <wx-space size="sm">
          <wx-button size="sm" variant="outline">Export</wx-button>
          <wx-button size="sm" type="primary">New page</wx-button>
        </wx-space>
      </wx-space>
    </div>

    <div>
      <span class="wx-demo__label">Vertical, and a filled row</span>
      <wx-space direction="vertical" size="sm">
        <wx-space fill>
          <wx-button variant="outline">Equal</wx-button>
          <wx-button variant="outline">Width</wx-button>
          <wx-button variant="outline">Buttons</wx-button>
        </wx-space>
        <wx-button block type="primary">Save</wx-button>
      </wx-space>
    </div>
  </div>
</template>
