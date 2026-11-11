<script setup lang="ts">
import { ref } from 'vue'
import { WxIconPicker } from '@webx-ui/core'

/* What `module-admin` hands the panel for `source: "theme"`: the theme's names and its sprite. */
const theme = {
  icons: ['area', 'bath', 'bed', 'terrace'],
  sprite: `${import.meta.env.BASE_URL}demos/property-icons.svg`,
}

const feature = ref<string | null>('bed')
const dropped = ref<string | null>('pool')
const part = ref<string | null>('trash')
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">The site's theme: names and a sprite</span>
      <wx-icon-picker v-model="feature" v-bind="theme" clearable />
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 13px">
        Model: <code>{{ feature ?? 'null' }}</code>
      </p>
    </div>

    <div>
      <span class="wx-demo__label">A name the theme no longer has</span>
      <wx-icon-picker v-model="dropped" v-bind="theme" />
    </div>

    <div>
      <span class="wx-demo__label">A part of the panel's set: names, no sprite</span>
      <wx-icon-picker v-model="part" :icons="['search', 'trash', 'edit', 'copy']" />
    </div>
  </div>
</template>
