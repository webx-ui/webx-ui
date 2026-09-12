<script setup lang="ts">
import { ref } from 'vue'
import { WxFileCard, WxSelectionArea, vWxSelect, type SelectionValue } from '@webx-ui/core'

interface Item {
  id: number
  name: string
  url: string
  thumbnail?: string
}

const files = ref<Item[]>([
  {
    id: 1,
    name: 'excavator-front-view.jpg',
    url: 'https://picsum.photos/seed/wx-file-a/600/450',
    thumbnail: 'https://picsum.photos/seed/wx-file-a/240/180',
  },
  {
    id: 2,
    name: 'hydraulics-diagram.png',
    url: 'https://picsum.photos/seed/wx-file-b/600/450',
    thumbnail: 'https://picsum.photos/seed/wx-file-b/240/180',
  },
  { id: 3, name: 'service-manual-2026.pdf', url: 'https://files.example/service-manual-2026.pdf' },
  { id: 4, name: 'spare-parts.xlsx', url: 'https://files.example/spare-parts.xlsx' },
  { id: 5, name: 'delivery-terms.docx', url: 'https://files.example/delivery-terms.docx' },
  { id: 6, name: 'site-survey.zip', url: 'https://files.example/site-survey.zip' },
  { id: 7, name: 'walkaround.mp4', url: 'https://files.example/walkaround.mp4' },
  { id: 8, name: 'brand-mark.svg', url: 'https://files.example/brand-mark.svg' },
  { id: 9, name: 'proposal-deck.pptx', url: 'https://files.example/proposal-deck.pptx' },
  { id: 10, name: 'field-notes.sketch', url: 'https://files.example/field-notes.sketch' },
])

const picked = ref<SelectionValue[]>([1])

const log = ref('Nothing yet')

function rename(item: Item, name: string) {
  item.name = name
  log.value = `Renamed to ${name}`
}

function remove(item: Item) {
  files.value = files.value.filter((file) => file.id !== item.id)
  log.value = `Deleted ${item.name}`
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">A media library — drag across them, or pick one</span>

      <wx-selection-area v-slot="{ isSelected }" v-model="picked" class="library">
        <wx-file-card
          v-for="file in files"
          :key="file.id"
          v-wx-select="file.id"
          :name="file.name"
          :url="file.url"
          :thumbnail="file.thumbnail"
          :selected="isSelected(file.id)"
          renamable
          editable
          removable
          copyable
          @rename="rename(file, $event)"
          @remove="remove(file)"
          @edit="log = `An editor would open for ${file.name}`"
          @copy="log = `Copied the link to ${file.name}`"
          @copy-error="log = 'The clipboard refused'"
        />
      </wx-selection-area>

      <span class="wx-demo__note">
        {{ log }} — double-click a name to rename it, too. Selected: {{ picked.length }}.
      </span>
    </div>

    <div>
      <span class="wx-demo__label">Sizes, and a card with nothing to do</span>

      <div class="row">
        <wx-file-card name="thumbnail.png" size="sm" :url="files[0]?.url" copyable />
        <wx-file-card name="report.pdf" size="md" removable />
        <wx-file-card name="archive.tar.gz" size="lg" />
        <wx-file-card name="locked-by-policy.docx" disabled removable />
      </div>
    </div>
  </div>
</template>

<style scoped>
.library {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap: var(--wx-space-8);
  padding: var(--wx-space-10);
  background: var(--wx-bg-body);
  border: 1px solid var(--wx-border-muted);
  border-radius: var(--wx-radius-md);
}

.row {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: var(--wx-space-12);
  align-items: start;
}
</style>
