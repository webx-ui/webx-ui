<script setup lang="ts">
import { ref } from 'vue'
import { WxButton, WxTab, WxTabs } from '@webx-ui/core'

const section = ref('general')

const many = [
  'Overview',
  'Content',
  'SEO',
  'Media',
  'Translations',
  'Comments',
  'Revisions',
  'Permissions',
  'Webhooks',
  'Danger zone',
]
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">A page editor · v-model is «{{ section }}»</span>
      <wx-tabs v-model="section" aria-label="Page sections">
        <template #extra>
          <wx-button size="sm" type="primary">Save</wx-button>
        </template>

        <wx-tab value="general" label="General" icon="edit">
          Name, slug and the template the page is rendered with.
        </wx-tab>
        <wx-tab value="seo" label="SEO" icon="search">Title, description, Open Graph image.</wx-tab>
        <wx-tab value="comments" label="Comments" icon="mail" :badge="12">
          Twelve comments are waiting for a decision.
        </wx-tab>
        <wx-tab value="access" label="Access" icon="lock" disabled>
          Only an administrator may change this.
        </wx-tab>
      </wx-tabs>
    </div>

    <div>
      <span class="wx-demo__label">Segmented, stretched across the width</span>
      <wx-tabs variant="pill" size="sm" align="stretch">
        <wx-tab value="day" label="Day">Orders placed today.</wx-tab>
        <wx-tab value="week" label="Week">Orders placed this week.</wx-tab>
        <wx-tab value="month" label="Month">Orders placed this month.</wx-tab>
      </wx-tabs>
    </div>

    <div>
      <span class="wx-demo__label">Folder tabs</span>
      <wx-tabs variant="card">
        <wx-tab value="html" label="HTML">The markup of the block.</wx-tab>
        <wx-tab value="css" label="CSS">Its styles.</wx-tab>
        <wx-tab value="fields" label="Fields">What the editor may fill in.</wx-tab>
      </wx-tabs>
    </div>

    <div>
      <span class="wx-demo__label">A column of tabs — it lies down in a narrow container</span>
      <wx-tabs orientation="vertical">
        <wx-tab value="profile" label="Profile" icon="user">Name, avatar, contacts.</wx-tab>
        <wx-tab value="security" label="Security" icon="lock">Password and sessions.</wx-tab>
        <wx-tab value="notifications" label="Notifications" icon="bell"
          >What to email about.</wx-tab
        >
      </wx-tabs>
    </div>

    <div>
      <span class="wx-demo__label">
        More tabs than fit: the strip scrolls, and the open one is kept in view
      </span>
      <wx-tabs>
        <wx-tab v-for="name in many" :key="name" :value="name" :label="name">
          The {{ name.toLowerCase() }} panel.
        </wx-tab>
      </wx-tabs>
    </div>
  </div>
</template>
