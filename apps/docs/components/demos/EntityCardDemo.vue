<script setup lang="ts">
import { ref } from 'vue'
import { WxBadge, WxButton, WxButtonGroup, WxCard, WxEntityCard, WxIcon } from '@webx-ui/core'

const selected = ref('news')

const posts = [
  {
    id: 'news',
    title: 'It is a long established fact that a reader will be distracted',
    image: 'https://picsum.photos/seed/webx-news/96/96',
    meta: [
      { label: 'Sections', text: 'News' },
      { label: 'Tags', text: '' },
    ],
  },
  {
    id: 'team',
    title: 'Our team has grown twice this year',
    image: 'https://picsum.photos/seed/webx-team/96/96',
    meta: [
      { label: 'Sections', text: 'Company' },
      { label: 'Tags', text: 'hiring, culture' },
    ],
  },
]
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <wx-card title="Client (registered)" padding="sm">
      <wx-entity-card
        variant="plain"
        title="Alexey Sizintsev"
        subtitle="alexxgroup@gmail.com"
        image="https://picsum.photos/seed/webx-client/96/96"
        shape="circle"
      >
        <template #actions>
          <wx-button-group size="sm" :attached="false" aria-label="Client actions">
            <wx-button type="primary" variant="outline" aria-label="Edit">
              <template #icon><wx-icon name="edit" /></template>
            </wx-button>
            <wx-button type="danger" variant="outline" aria-label="Delete">
              <template #icon><wx-icon name="trash" /></template>
            </wx-button>
          </wx-button-group>
        </template>
      </wx-entity-card>
    </wx-card>

    <div>
      <span class="wx-demo__label">A list of content rows</span>
      <div class="wx-demo__stack">
        <wx-entity-card
          v-for="post in posts"
          :key="post.id"
          :title="post.title"
          :image="post.image"
          :meta="post.meta"
          :selected="selected === post.id"
          href="#entity-card"
          @click="selected = post.id"
        >
          <template #actions>
            <wx-badge type="success" dot round>Published</wx-badge>
            <wx-button size="sm" variant="text" aria-label="More">
              <template #icon><wx-icon name="more-vertical" /></template>
            </wx-button>
          </template>
        </wx-entity-card>
      </div>
    </div>

    <div>
      <span class="wx-demo__label">Without a picture, and compact</span>
      <div class="wx-demo__stack">
        <wx-entity-card
          title="Draft without a cover"
          :meta="[{ label: 'Updated', text: 'today' }]"
          bordered
        />
        <wx-entity-card
          size="sm"
          title="Order #2026-0431"
          subtitle="Paid · 2 items"
          image="https://picsum.photos/seed/webx-order/64/64"
          bordered
        >
          <template #actions>
            <wx-button size="sm" variant="text">Open</wx-button>
          </template>
        </wx-entity-card>
      </div>
    </div>
  </div>
</template>
