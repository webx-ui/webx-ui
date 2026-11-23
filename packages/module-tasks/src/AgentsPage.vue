<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
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
  WxAlert,
  WxBadge,
  WxCard,
  WxDescriptions,
  WxDescriptionsItem,
  WxEmpty,
  WxSkeleton,
  WxTable,
  WxText,
  type TableColumn,
} from '@webx-ui/core'
import { createTasksApi } from './api'
import { useTasksMessages } from './i18n'
import type { RunnerBeat, RunnerStatus, RunnerWindow } from './types'

/**
 * «Agents» (§14.3): the watcher as it last reported itself — when, the sessions it runs, the pause
 * and why it waits — and a plain «silent» when it has said nothing for longer than the limit.
 *
 * It only shows: the watcher's limits and the pause are the settings screen's. Read again every
 * half a minute, so a page left open turns silent on its own.
 */
defineOptions({ name: 'WxTasksAgentsPage' })

const props = withDefaults(defineProps<{ base?: string }>(), { base: '/tasks' })

const REFRESH = 30_000

const context = useAdmin()
const api = createTasksApi(context)
const router = useRouter()
useTasksMessages()

const t = useTranslate('webx-tasks')
const message = useErrorText()

const status = ref<RunnerStatus | null>(null)
const loading = ref(true)
let timer: ReturnType<typeof setInterval> | undefined

const canManage = context.can('tasks.manage')

const columns = computed<TableColumn<RunnerWindow>[]>(() => [
  { key: 'agent', label: t('panel.window-agent'), minWidth: 140 },
  { key: 'stage', label: t('panel.window-stage'), width: 72 },
  { key: 'runner', label: t('panel.window-runner'), width: 100, hideBelow: 480 },
  { key: 'since', label: t('panel.window-since'), width: 120 },
])

async function load(quiet = false): Promise<void> {
  try {
    status.value = await api.runner()
  } catch (error) {
    if (!quiet) toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void load()
  timer = setInterval(() => void load(true), REFRESH)
})

onBeforeUnmount(() => clearInterval(timer))

/* Minutes since the beat, whole: the limit is counted in minutes too. */
function minutes(beat: RunnerBeat): number {
  return Math.max(0, Math.floor((Date.now() - new Date(beat.beat_at).getTime()) / 60_000))
}

function workers(beat: RunnerBeat): string {
  if (beat.max_workers === null) return '—'
  if (beat.hard_max_workers === null) return String(beat.max_workers)

  return t('panel.runner-workers-value', {
    applied: beat.max_workers,
    hard: beat.hard_max_workers,
  })
}

const actions = computed<ScreenAction[]>(() =>
  canManage
    ? [
        {
          key: 'settings',
          label: t('panel.settings'),
          icon: 'settings',
          run: () => void router.push(`${props.base}/settings`),
        },
      ]
    : [],
)
</script>

<template>
  <wx-list-screen
    :title="t('panel.agents')"
    :back="props.base"
    :back-label="t('panel.back')"
    :actions="actions"
    :card="false"
  >
    <div class="wx-tasks-agents">
      <wx-skeleton v-if="loading" title :rows="5" />

      <template v-else-if="status">
        <wx-alert v-if="status.config.paused" type="warning">
          {{ t('panel.queue-paused') }}
        </wx-alert>

        <wx-empty v-if="status.runners.length === 0" :description="t('panel.agents-empty')" />

        <wx-card v-for="beat in status.runners" :key="beat.runner" class="wx-tasks-agents__runner">
          <div class="wx-tasks-agents__head">
            <wx-text weight="medium" mono>{{ beat.runner }}</wx-text>
            <wx-badge :type="beat.silent ? 'danger' : 'success'" size="sm">
              {{ beat.silent ? t('panel.runner-silent') : t('panel.runner-alive') }}
            </wx-badge>
            <wx-badge v-if="beat.paused" type="warning" size="sm">
              {{ t('panel.runner-paused') }}
            </wx-badge>
          </div>

          <wx-alert v-if="beat.silent" type="danger">
            {{ t('panel.runner-silent-text', { minutes: minutes(beat) }) }}
          </wx-alert>

          <wx-descriptions :columns="2" size="sm">
            <wx-descriptions-item :label="t('panel.runner-last-beat')">
              <wx-date :value="beat.beat_at" size="sm" />
            </wx-descriptions-item>
            <wx-descriptions-item :label="t('panel.runner-version')">
              {{ beat.version ?? '—' }}
            </wx-descriptions-item>
            <wx-descriptions-item :label="t('panel.runner-workers')">
              {{ workers(beat) }}
            </wx-descriptions-item>
            <wx-descriptions-item :label="t('panel.runner-today')">
              {{ beat.daily_sessions ?? '—' }}
            </wx-descriptions-item>
            <wx-descriptions-item v-if="beat.waiting" :label="t('panel.runner-waiting')" :span="2">
              {{ beat.waiting }}
            </wx-descriptions-item>
          </wx-descriptions>

          <wx-text size="sm" weight="medium">{{ t('panel.runner-windows') }}</wx-text>

          <wx-table
            :data="beat.windows"
            :columns="columns"
            flush
            layout="fixed"
            :cards-below="0"
            :empty-text="t('panel.runner-windows-empty')"
            :aria-label="t('panel.runner-windows')"
          >
            <template #cell-agent="{ row }">
              <wx-text size="sm" mono truncate>{{ row.agent ?? '—' }}</wx-text>
            </template>
            <template #cell-stage="{ row }">
              <wx-text size="sm" mono>{{ row.stage ?? '—' }}</wx-text>
            </template>
            <template #cell-runner="{ row }">
              <wx-text size="sm" tone="muted">{{ row.runner ?? '—' }}</wx-text>
            </template>
            <template #cell-since="{ row }">
              <wx-date v-if="row.since" :value="row.since" compact size="sm" tone="muted" />
              <wx-text v-else size="sm" tone="muted">—</wx-text>
            </template>
          </wx-table>
        </wx-card>
      </template>
    </div>
  </wx-list-screen>
</template>

<style scoped>
.wx-tasks-agents {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  min-width: 0;
  container-type: inline-size;
}

.wx-tasks-agents__runner :deep(.wx-card__body) {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-tasks-agents__head {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
  align-items: center;
}
</style>
