<script setup lang="ts">
import { ref } from 'vue'
import { WxFormItem, WxSelect } from '@webx-ui/core'

const status = ref<string | null>('draft')
const author = ref<number | null>(null)
const tags = ref<string[]>(['news'])
const empty = ref<string | null>(null)

const statuses = [
  { label: 'Draft', value: 'draft' },
  { label: 'Published', value: 'published' },
  { label: 'Archived', value: 'archived', disabled: true },
]

const authors = [
  { label: 'Alexey Sizintsev', value: 1 },
  { label: 'Admin Adminovich', value: 10 },
  { label: 'Editor Editorovich', value: 11 },
  { label: 'Maria Kovalenko', value: 12 },
  { label: 'Ivan Petrenko', value: 13 },
]

const tagOptions = [
  { label: 'News', value: 'news' },
  { label: 'Guides', value: 'guides' },
  { label: 'Releases', value: 'releases' },
  { label: 'Cases', value: 'cases' },
]
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Single</span>
      <wx-select v-model="status" :options="statuses" placeholder="Pick a status" clearable />
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 13px">
        Model: <code>{{ status ?? 'null' }}</code>
      </p>
    </div>

    <div>
      <span class="wx-demo__label">Searchable</span>
      <wx-select
        v-model="author"
        :options="authors"
        filterable
        clearable
        placeholder="Find an author"
      />
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 13px">
        Model: <code>{{ author ?? 'null' }}</code>
      </p>
    </div>

    <div>
      <span class="wx-demo__label">Multiple, searchable</span>
      <wx-select
        v-model="tags"
        :options="tagOptions"
        multiple
        filterable
        clearable
        placeholder="Pick tags"
      />
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 13px">
        Model: <code>{{ JSON.stringify(tags) }}</code>
      </p>
    </div>

    <div>
      <span class="wx-demo__label">In a form item, with an error</span>
      <wx-form-item label="Category" error="The category field is required.">
        <wx-select v-model="empty" :options="tagOptions" placeholder="Pick a category" />
      </wx-form-item>
    </div>

    <div>
      <span class="wx-demo__label">Sizes and disabled</span>
      <div class="wx-demo__stack">
        <wx-select model-value="draft" :options="statuses" size="sm" />
        <wx-select model-value="draft" :options="statuses" size="lg" />
        <wx-select model-value="draft" :options="statuses" disabled />
      </div>
    </div>
  </div>
</template>
