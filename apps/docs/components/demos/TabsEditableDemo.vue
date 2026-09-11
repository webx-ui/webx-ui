<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  WxAction,
  WxButton,
  WxForm,
  WxFormItem,
  WxInput,
  WxPopover,
  WxSelect,
  WxTab,
  WxTabs,
} from '@webx-ui/core'

interface Section {
  id: string
  title: string
  icon?: string
  body: string
}

const sections = ref<Section[]>([
  { id: 'general', title: 'General', icon: 'edit', body: 'Name, slug and template.' },
  { id: 'seo', title: 'SEO', icon: 'search', body: 'Title, description, Open Graph image.' },
  { id: 'access', title: 'Access', icon: 'lock', body: 'Who may edit this page.' },
])

const active = ref('general')
const editing = ref(false)
const adding = ref(false)

const icons = [
  { label: 'Edit', value: 'edit' },
  { label: 'Search', value: 'search' },
  { label: 'Lock', value: 'lock' },
  { label: 'Bell', value: 'bell' },
]

const current = computed(() => sections.value.find((section) => section.id === active.value))

const draft = ref({ title: '', icon: null as string | number | null })

/* The panels open themselves; the draft is taken from the tab when they do. */
watch(editing, (open) => {
  if (open && current.value)
    draft.value = { title: current.value.title, icon: current.value.icon ?? null }
})

watch(adding, (open) => {
  if (open) draft.value = { title: '', icon: null }
})

function save() {
  if (!current.value) return
  current.value.title = draft.value.title.trim() || current.value.title
  current.value.icon = (draft.value.icon as string) || undefined
  editing.value = false
}

function add() {
  const id = `section-${sections.value.length + 1}-${Date.now().toString(36)}`
  sections.value.push({
    id,
    title: draft.value.title.trim() || 'New section',
    icon: (draft.value.icon as string) || undefined,
    body: 'Nothing here yet.',
  })
  // Opening the new tab also scrolls the strip to it.
  active.value = id
  adding.value = false
}

/* The tabs re-open the first one that is left, so nothing has to be chosen here. */
function remove() {
  sections.value = sections.value.filter((section) => section.id !== active.value)
  editing.value = false
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <wx-tabs v-model="active" aria-label="Page sections">
      <template #extra>
        <wx-popover v-model:open="editing" title="Tab" :width="300" side="bottom" align="end">
          <template #trigger>
            <wx-action type="edit" size="sm" title="Edit this tab" :disabled="!current" />
          </template>

          <wx-form gap="sm">
            <wx-form-item label="Name">
              <wx-input v-model="draft.title" size="sm" placeholder="Tab name" />
            </wx-form-item>
            <wx-form-item label="Icon">
              <wx-select v-model="draft.icon" :options="icons" size="sm" clearable />
            </wx-form-item>
          </wx-form>

          <template #footer="{ close }">
            <wx-button
              size="sm"
              type="danger"
              variant="text"
              class="wx-demo__push"
              :disabled="sections.length < 2"
              @click="remove"
            >
              Delete
            </wx-button>

            <wx-button size="sm" @click="close">Cancel</wx-button>
            <wx-button size="sm" type="primary" @click="save">Save</wx-button>
          </template>
        </wx-popover>

        <wx-popover v-model:open="adding" title="New tab" :width="260" side="bottom" align="end">
          <template #trigger>
            <wx-action type="add" size="sm" title="Add a tab" />
          </template>

          <wx-form gap="sm">
            <wx-form-item label="Name">
              <wx-input v-model="draft.title" size="sm" placeholder="Tab name" />
            </wx-form-item>
            <wx-form-item label="Icon">
              <wx-select v-model="draft.icon" :options="icons" size="sm" clearable />
            </wx-form-item>
          </wx-form>

          <template #footer="{ close }">
            <wx-button size="sm" @click="close">Cancel</wx-button>
            <wx-button size="sm" type="primary" @click="add">Add</wx-button>
          </template>
        </wx-popover>
      </template>

      <wx-tab
        v-for="section in sections"
        :key="section.id"
        :value="section.id"
        :label="section.title"
        :icon="section.icon"
      >
        {{ section.body }}
      </wx-tab>
    </wx-tabs>
  </div>
</template>

<style scoped>
.wx-demo__push {
  margin-right: auto;
}
</style>
