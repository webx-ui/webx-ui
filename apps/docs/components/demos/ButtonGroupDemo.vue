<script setup lang="ts">
import { ref } from 'vue'
import { WxButton, WxButtonGroup, WxIcon } from '@webx-ui/core'

const view = ref<'grid' | 'list'>('list')
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">One look for the whole group</span>
      <div class="wx-demo__row">
        <wx-button-group type="primary" variant="outline" aria-label="Text alignment">
          <wx-button>Left</wx-button>
          <wx-button>Center</wx-button>
          <wx-button>Right</wx-button>
        </wx-button-group>

        <wx-button-group size="sm" aria-label="View">
          <wx-button :type="view === 'list' ? 'primary' : 'default'" @click="view = 'list'">
            <template #icon><wx-icon name="list" /></template>
            List
          </wx-button>
          <wx-button :type="view === 'grid' ? 'primary' : 'default'" @click="view = 'grid'">
            <template #icon><wx-icon name="grid" /></template>
            Grid
          </wx-button>
        </wx-button-group>
      </div>
    </div>

    <div>
      <span class="wx-demo__label">Row actions — icons only</span>
      <div class="wx-demo__row">
        <wx-button-group size="sm" aria-label="Row actions">
          <wx-button aria-label="Edit"
            ><template #icon><wx-icon name="edit" /></template
          ></wx-button>
          <wx-button aria-label="Copy"
            ><template #icon><wx-icon name="copy" /></template
          ></wx-button>
          <wx-button type="danger" variant="outline" aria-label="Delete">
            <template #icon><wx-icon name="trash" /></template>
          </wx-button>
        </wx-button-group>

        <wx-button-group size="sm" :attached="false" aria-label="Separate actions">
          <wx-button variant="text">Cancel</wx-button>
          <wx-button type="success">Save</wx-button>
        </wx-button-group>
      </div>
    </div>

    <div>
      <span class="wx-demo__label">Stacked, and disabled as a whole</span>
      <div class="wx-demo__row" style="align-items: flex-start">
        <wx-button-group vertical aria-label="Sections">
          <wx-button>General</wx-button>
          <wx-button>SEO</wx-button>
          <wx-button>Access</wx-button>
        </wx-button-group>

        <wx-button-group disabled aria-label="Disabled group">
          <wx-button>Publish</wx-button>
          <wx-button>Schedule</wx-button>
        </wx-button-group>
      </div>
    </div>
  </div>
</template>
