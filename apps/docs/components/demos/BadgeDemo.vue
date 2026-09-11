<script setup lang="ts">
import { ref } from 'vue'
import { WxBadge } from '@webx-ui/core'

const types = ['default', 'primary', 'success', 'warning', 'danger', 'info'] as const

const tags = ref(['News', 'Releases', 'Guides'])
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Soft — the default</span>
      <div class="wx-demo__row">
        <wx-badge v-for="type in types" :key="type" :type="type">{{ type }}</wx-badge>
      </div>
    </div>

    <div>
      <span class="wx-demo__label">Solid</span>
      <div class="wx-demo__row">
        <wx-badge v-for="type in types" :key="type" :type="type" variant="solid">
          {{ type }}
        </wx-badge>
      </div>
    </div>

    <div>
      <span class="wx-demo__label">Outline</span>
      <div class="wx-demo__row">
        <wx-badge v-for="type in types" :key="type" :type="type" variant="outline">
          {{ type }}
        </wx-badge>
      </div>
    </div>

    <div>
      <span class="wx-demo__label">Status dots and sizes</span>
      <div class="wx-demo__row">
        <wx-badge type="success" dot round>Published</wx-badge>
        <wx-badge type="warning" dot round>Pending review</wx-badge>
        <wx-badge type="default" dot round>Draft</wx-badge>
        <wx-badge size="sm">sm</wx-badge>
        <wx-badge size="md">md</wx-badge>
        <wx-badge size="lg">lg</wx-badge>
      </div>
    </div>

    <div>
      <span class="wx-demo__label">Closable</span>
      <div class="wx-demo__row">
        <wx-badge
          v-for="tag in tags"
          :key="tag"
          type="primary"
          round
          closable
          @close="tags = tags.filter((item) => item !== tag)"
        >
          {{ tag }}
        </wx-badge>
        <wx-badge v-if="tags.length === 0" type="default">Everything removed</wx-badge>
      </div>
    </div>
  </div>
</template>
