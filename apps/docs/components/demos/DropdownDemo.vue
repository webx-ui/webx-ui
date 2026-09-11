<script setup lang="ts">
import { ref } from 'vue'
import {
  WxAction,
  WxBadge,
  WxButton,
  WxCheckboxGroup,
  WxDropdown,
  WxDropdownItem,
  WxIcon,
} from '@webx-ui/core'

const picked = ref('—')
const statuses = ref<string[]>(['published'])
const open = ref(false)
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Any control as the trigger, any markup as the content</span>
      <div class="wx-demo__row">
        <wx-dropdown>
          <template #trigger>
            <wx-button type="primary">
              Actions
              <template #icon><wx-icon name="chevron-down" /></template>
            </wx-button>
          </template>

          <wx-dropdown-item icon="edit" @click="picked = 'Edit'">Edit</wx-dropdown-item>
          <wx-dropdown-item icon="copy" @click="picked = 'Duplicate'">Duplicate</wx-dropdown-item>
          <wx-dropdown-item icon="external-link" href="#dropdown"
            >Open in a new tab</wx-dropdown-item
          >
          <hr />
          <wx-dropdown-item icon="trash" tone="danger" @click="picked = 'Delete'">
            Delete
          </wx-dropdown-item>
        </wx-dropdown>

        <wx-dropdown align="end">
          <template #trigger>
            <wx-action type="more" title="More" />
          </template>
          <wx-dropdown-item icon="download" @click="picked = 'Export'">
            Export
            <template #after>CSV</template>
          </wx-dropdown-item>
          <wx-dropdown-item icon="upload" @click="picked = 'Import'">Import</wx-dropdown-item>
          <wx-dropdown-item icon="settings" disabled>Settings</wx-dropdown-item>
        </wx-dropdown>

        <wx-dropdown v-model:open="open" side="right" align="start">
          <template #trigger="{ open: isOpen }">
            <wx-button variant="outline">{{ isOpen ? 'Close' : 'Open to the right' }}</wx-button>
          </template>
          <wx-dropdown-item icon="user">Profile</wx-dropdown-item>
          <wx-dropdown-item icon="logout" tone="danger">Sign out</wx-dropdown-item>
        </wx-dropdown>

        <span style="color: var(--wx-text-muted); font-size: 14px">
          Picked: <code>{{ picked }}</code>
        </span>
      </div>
    </div>

    <div>
      <span class="wx-demo__label">A panel that stays open while it is worked in</span>
      <wx-dropdown :close-on-click="false" match-trigger-width>
        <template #trigger>
          <wx-button variant="outline">
            <template #icon><wx-icon name="filter" /></template>
            Status
            <wx-badge v-if="statuses.length" type="primary" size="sm" round>
              {{ statuses.length }}
            </wx-badge>
          </wx-button>
        </template>

        <template #default="{ close }">
          <wx-checkbox-group
            v-model="statuses"
            size="sm"
            :options="[
              { label: 'Draft', value: 'draft' },
              { label: 'Published', value: 'published' },
              { label: 'Archived', value: 'archived' },
            ]"
          />
          <hr />
          <wx-button size="sm" type="primary" block @click="close">Apply</wx-button>
        </template>
      </wx-dropdown>
    </div>
  </div>
</template>
