<script setup lang="ts">
import { ref } from 'vue'
import { WxAction, WxActions, WxCard, WxDropdownItem, WxEntityCard, WxSlider } from '@webx-ui/core'

const last = ref('')

const rows = [
  { id: 12, title: 'It is a long established fact that a reader', removable: true },
  { id: 13, title: 'Our team has grown twice this year', removable: false },
]

/* The collapse demo needs a container the reader can narrow by hand. */
const width = ref(100)
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">The kinds of action</span>
      <wx-actions aria-label="Every action type">
        <wx-action type="add" @click="last = 'add'" />
        <wx-action type="edit" @click="last = 'edit'" />
        <wx-action type="remove" @click="last = 'remove'" />
        <wx-action type="copy" @click="last = 'copy'" />
        <wx-action type="sort" @click="last = 'sort'" />
        <wx-action type="view" @click="last = 'view'" />
        <wx-action type="details" @click="last = 'details'" />
        <wx-action type="restore" @click="last = 'restore'" />
        <wx-action type="goto" href="#actions" />
      </wx-actions>
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 14px">
        Last clicked: <code>{{ last || '—' }}</code>
      </p>
    </div>

    <div>
      <span class="wx-demo__label">In a list — a row that cannot be deleted keeps the space</span>
      <div class="wx-demo__stack">
        <wx-entity-card
          v-for="row in rows"
          :key="row.id"
          :title="row.title"
          bordered
          variant="plain"
        >
          <template #actions>
            <wx-actions size="sm" align="end" aria-label="Row actions">
              <wx-action type="edit" :href="`#page-${row.id}`" />
              <wx-action type="copy" />
              <wx-action type="remove" :hidden="!row.removable" />
            </wx-actions>
          </template>
        </wx-entity-card>
      </div>
    </div>

    <div>
      <span class="wx-demo__label">Collapsing — drag to narrow the container</span>
      <wx-slider v-model="width" :min="20" :max="100" :step="1" style="max-width: 320px" />
      <div :style="{ width: `${width}%`, marginTop: '12px', transition: 'width 120ms linear' }">
        <wx-card padding="sm" bordered>
          <wx-actions collapse align="end" aria-label="Page actions">
            <wx-action type="add" title="Add" />
            <wx-action type="edit" title="Edit" />
            <wx-action type="copy" title="Duplicate" />
            <wx-action type="upload" title="Publish" />
            <wx-action type="download" title="Export" />
            <wx-action type="remove" title="Delete" />

            <template #collapsed>
              <wx-dropdown-item icon="plus">Add</wx-dropdown-item>
              <wx-dropdown-item icon="edit">Edit</wx-dropdown-item>
              <wx-dropdown-item icon="copy">Duplicate</wx-dropdown-item>
              <wx-dropdown-item icon="upload">Publish</wx-dropdown-item>
              <wx-dropdown-item icon="download">Export</wx-dropdown-item>
              <wx-dropdown-item icon="trash" tone="danger">Delete</wx-dropdown-item>
            </template>
          </wx-actions>
        </wx-card>
      </div>
    </div>
  </div>
</template>
