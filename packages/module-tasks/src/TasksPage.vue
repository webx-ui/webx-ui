<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  useAdmin,
  useErrorText,
  useTranslate,
  WxDate,
  WxListScreen,
  type ScreenAction,
} from '@webx-ui/module-admin'
import {
  toast,
  WxBadge,
  WxSelect,
  WxTable,
  WxText,
  type SelectValue,
  type TabItem,
  type TableColumn,
  type TableState,
  type TabValue,
} from '@webx-ui/core'
import { createTasksApi } from './api'
import { useTasksMessages } from './i18n'
import { priorityTones, stageTones, taskTones } from './tones'
import type { Project, TaskRow, TasksPage, TaskView } from './types'

/**
 * The tasks (§6.1): a table, not a board. One row per task — its reference, what it is called,
 * where it is and which stage it is on — highest priority first, then the most recently changed.
 *
 * The views are the questions somebody opens this screen with: what waits for me, what is
 * being worked on, what I have not put to work yet, what is over. «Waiting for me» is the one
 * the badge in the menu counts — a stage a person has to check, or an agent's question.
 *
 * Everything the list is looking at lives in the address, so a link to it is a link.
 */
defineOptions({ name: 'WxTasksPage' })

const props = withDefaults(defineProps<{ base?: string }>(), { base: '/tasks' })

const context = useAdmin()
const api = createTasksApi(context)
const route = useRoute()
const router = useRouter()
useTasksMessages()

const t = useTranslate('webx-tasks')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const page = ref<TasksPage | null>(null)
const projects = ref<Project[]>([])
const loading = ref(true)

const canManage = context.can('tasks.manage')

const VIEWS: TaskView[] = ['all', 'waiting', 'active', 'draft', 'closed']

const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'tasks')?.title ??
    t('module.title'),
)

const view = computed<TabValue>({
  get: () =>
    typeof route.query.view === 'string' && VIEWS.includes(route.query.view as TaskView)
      ? route.query.view
      : 'all',
  set: (value) => {
    void router.replace({
      query: { ...route.query, view: value === 'all' ? undefined : String(value), page: undefined },
    })
  },
})

const project = computed<SelectValue | null>({
  get: () => (typeof route.query.project === 'string' ? Number(route.query.project) : null),
  set: (value) => {
    void router.replace({
      query: {
        ...route.query,
        project: value === null || value === '' ? undefined : String(value),
        page: undefined,
      },
    })
  },
})

const views = computed<TabItem[]>(() =>
  VIEWS.map((value) => ({
    value,
    label: t(`panel.view-${value}`),
    badge: page.value?.counts[value] ?? undefined,
  })),
)

const projectOptions = computed(() =>
  projects.value.map((one) => ({ label: one.title, value: one.id })),
)

const rows = computed(() => page.value?.data ?? [])

/*
 * The task and its stage stay at every width; the project goes first — it is in the reference
 * already — then the date and the priority.
 */
const columns = computed<TableColumn<TaskRow>[]>(() => [
  { key: 'title', label: t('panel.column-task'), minWidth: 220 },
  { key: 'status', label: t('panel.column-status'), width: 140 },
  { key: 'current_stage', label: t('panel.column-stage'), minWidth: 180, hideBelow: 640 },
  { key: 'priority', label: t('panel.column-priority'), width: 120, hideBelow: 760 },
  { key: 'updated_at', label: t('panel.column-updated'), width: 120, hideBelow: 900 },
])

async function load(state?: TableState): Promise<void> {
  loading.value = true

  try {
    page.value = await api.tasks({
      view: view.value as TaskView,
      project: typeof project.value === 'number' ? project.value : null,
      q: state?.search ?? (typeof route.query.q === 'string' ? route.query.q : ''),
      page: state?.page ?? (Number(route.query.page ?? 1) || 1),
      per_page: state?.perPage,
    })
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

function onState(state: TableState): void {
  void router.replace({
    query: {
      ...route.query,
      q: state.search === '' ? undefined : state.search,
      page: state.page === 1 ? undefined : String(state.page),
    },
  })
  void load(state)
}

async function loadProjects(): Promise<void> {
  try {
    projects.value = await api.projects()
  } catch (error) {
    toast.danger(message(error))
  }
}

watch([view, project], () => void load())

void load()
void loadProjects()

function open(row: TaskRow): void {
  void router.push(`${props.base}/${row.id}`)
}

const actions = computed<ScreenAction[]>(() => [
  {
    key: 'projects',
    label: t('panel.projects'),
    icon: 'folder',
    run: () => void router.push(`${props.base}/projects`),
  },
  ...(canManage
    ? [
        {
          key: 'settings',
          label: t('panel.settings'),
          icon: 'settings' as const,
          menu: true,
          run: () => void router.push(`${props.base}/settings`),
        },
        {
          key: 'new',
          label: t('panel.new'),
          icon: 'plus' as const,
          primary: true,
          run: () => void router.push(`${props.base}/new`),
        },
      ]
    : []),
])
</script>

<template>
  <div class="wx-tasks-list">
    <wx-list-screen v-model:view="view" :title="title" :views="views" :actions="actions">
      <template #actions>
        <wx-select
          v-if="projects.length > 1"
          v-model="project"
          class="wx-tasks-list__project"
          :options="projectOptions"
          :placeholder="t('panel.all-projects')"
          :aria-label="t('panel.all-projects')"
          clearable
          size="sm"
        />
      </template>

      <wx-table
        :data="page"
        :columns="columns"
        row-key="id"
        searchable
        hover
        flush
        layout="fixed"
        :loading="loading"
        :cards-below="0"
        :search-placeholder="t('panel.search')"
        :empty-text="rows.length === 0 && !loading ? t('panel.empty') : undefined"
        :aria-label="title"
        @state-change="onState"
        @row-click="open"
      >
        <template #cell-title="{ row }">
          <div class="wx-tasks-list__task">
            <wx-text size="sm" tone="muted" mono>{{ row.ref }}</wx-text>
            <wx-text truncate weight="medium">{{ row.title }}</wx-text>
          </div>
        </template>

        <template #cell-status="{ row }">
          <wx-badge :type="taskTones[row.status]">{{ t(`panel.status-${row.status}`) }}</wx-badge>
        </template>

        <template #cell-current_stage="{ row }">
          <div v-if="row.current_stage" class="wx-tasks-list__stage">
            <wx-text size="sm" truncate>
              {{ t('panel.stage-of', { position: row.current_stage.position, count: row.stages }) }}
              · {{ row.current_stage.title }}
            </wx-text>
            <wx-badge size="sm" :type="stageTones[row.current_stage.status]">
              {{ t(`panel.stage-${row.current_stage.status}`) }}
            </wx-badge>
          </div>
          <wx-text v-else size="sm" tone="muted">—</wx-text>
        </template>

        <template #cell-priority="{ row }">
          <wx-badge variant="outline" :type="priorityTones[row.priority]">
            {{ t(`panel.priority-${row.priority}`) }}
          </wx-badge>
        </template>

        <template #cell-updated_at="{ row }">
          <wx-date :value="row.updated_at" compact size="sm" tone="muted" />
        </template>
      </wx-table>
    </wx-list-screen>
  </div>
</template>

<style scoped>
.wx-tasks-list {
  min-width: 0;
}

.wx-tasks-list__project {
  width: 200px;
  max-width: 100%;
}

.wx-tasks-list__task,
.wx-tasks-list__stage {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
  align-items: flex-start;
  min-width: 0;
}

/* Held to the cell, or a long title runs under the next column instead of ending in an ellipsis. */
.wx-tasks-list__task > *,
.wx-tasks-list__stage > * {
  max-width: 100%;
}
</style>
