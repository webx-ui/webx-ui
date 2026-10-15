<script setup lang="ts">
import { computed, ref } from 'vue'
import {
  useAdmin,
  useErrorText,
  useTranslate,
  WxListScreen,
  type ScreenAction,
} from '@webx-ui/module-admin'
import {
  createModal,
  toast,
  WxBadge,
  WxButton,
  WxTable,
  WxText,
  type TableColumn,
} from '@webx-ui/core'
import ProjectDialog from './ProjectDialog.vue'
import { createTasksApi } from './api'
import { useTasksMessages } from './i18n'
import type { Project } from './types'

/**
 * The projects (§6, «Projects»): where work happens. A short list — a handful of rows, archived
 * ones last — and a dialog to write one; nothing here pages or filters.
 */
defineOptions({ name: 'WxTasksProjectsPage' })

const props = withDefaults(defineProps<{ base?: string }>(), { base: '/tasks' })

const context = useAdmin()
const api = createTasksApi(context)
useTasksMessages()

const t = useTranslate('webx-tasks')
const message = useErrorText()

const projects = ref<Project[]>([])
const loading = ref(true)

const canManage = context.can('tasks.manage')

const edit = createModal<Project, { project?: Project | null }>(ProjectDialog)

const columns = computed<TableColumn<Project>[]>(() => [
  { key: 'title', label: t('panel.project-title'), minWidth: 200 },
  { key: 'workdir', label: t('panel.project-workdir'), minWidth: 200, hideBelow: 640 },
  { key: 'tasks_count', label: t('panel.project-tasks'), width: 100, align: 'right' },
  { key: 'actions', label: '', width: 120, align: 'right' },
])

async function load(): Promise<void> {
  loading.value = true

  try {
    projects.value = await api.projects()
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

void load()

async function write(project: Project | null): Promise<void> {
  if (await edit({ project })) await load()
}

const actions = computed<ScreenAction[]>(() =>
  canManage
    ? [
        {
          key: 'new',
          label: t('panel.project-new'),
          icon: 'plus',
          primary: true,
          run: () => void write(null),
        },
      ]
    : [],
)
</script>

<template>
  <wx-list-screen
    :title="t('panel.projects')"
    :back="props.base"
    :back-label="t('panel.back')"
    :actions="actions"
  >
    <wx-table
      :data="projects"
      :columns="columns"
      row-key="id"
      flush
      layout="fixed"
      :loading="loading"
      :cards-below="0"
      :empty-text="projects.length === 0 && !loading ? t('panel.projects-empty') : undefined"
      :aria-label="t('panel.projects')"
    >
      <template #cell-title="{ row }">
        <div class="wx-tasks-projects__title">
          <wx-text truncate weight="medium">{{ row.title }}</wx-text>
          <wx-text size="sm" tone="muted" mono>{{ row.slug }}</wx-text>
          <wx-badge v-if="row.archived" size="sm">{{ t('panel.project-archived') }}</wx-badge>
        </div>
      </template>

      <template #cell-workdir="{ row }">
        <wx-text size="sm" tone="muted" mono truncate>{{ row.workdir ?? '—' }}</wx-text>
      </template>

      <template #cell-actions="{ row }">
        <wx-button v-if="canManage" size="sm" variant="outline" @click="write(row)">
          {{ t('panel.edit') }}
        </wx-button>
      </template>
    </wx-table>
  </wx-list-screen>
</template>

<style scoped>
.wx-tasks-projects__title {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
  align-items: flex-start;
  min-width: 0;
}
</style>
