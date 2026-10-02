<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useAdmin, useErrorText, useTranslate, WxDate } from '@webx-ui/module-admin'
import {
  toast,
  WxAlert,
  WxBadge,
  WxButton,
  WxCard,
  WxEmpty,
  WxProgress,
  WxSegmented,
  WxSkeleton,
  WxStatistic,
  WxText,
  type ProgressStatus,
} from '@webx-ui/core'
import AuditLayout from './AuditLayout.vue'
import { createAuditApi } from './api'
import { useAuditMessages } from './i18n'
import type { AuditLatest, AuditRun, AuditScope, AuditSeverity } from './types'

/**
 * The last run at a glance (§8): health, the counts by severity and by group, what is new and
 * what got fixed since the run before, and the button that starts the next one. While a run is
 * going the page asks for it every couple of seconds and shows the stage it is in.
 */
const props = defineProps<{ base: string; settingsPath: string }>()

const context = useAdmin()
const api = createAuditApi(context)
useAuditMessages()

const t = useTranslate('webx-audit')
const message = useErrorText()

const POLL_MS = 2000

const latest = ref<AuditLatest | null>(null)
const scope = ref<AuditScope>('quick')
const starting = ref(false)
let timer: ReturnType<typeof setTimeout> | undefined

const canRun = context.can('audit.run') || context.can('audit.manage')

const active = computed(() => latest.value?.active ?? null)
const done = computed(() => latest.value?.done ?? null)
const counts = computed(() => done.value?.counts ?? null)

const scopes = computed(() => [
  { value: 'quick', label: t('page.scope-quick') },
  { value: 'full', label: t('page.scope-full') },
])

const severities: { key: AuditSeverity; label: string; tone: 'danger' | 'warning' | 'info' }[] = [
  { key: 'error', label: 'page.errors', tone: 'danger' },
  { key: 'warning', label: 'page.warnings', tone: 'warning' },
  { key: 'notice', label: 'page.notices', tone: 'info' },
]

const healthStatus = computed<ProgressStatus>(() => {
  const health = counts.value?.health ?? 0

  if (health >= 90) return 'success'
  if (health >= 60) return 'warning'

  return 'danger'
})

const groups = computed(() =>
  Object.entries(counts.value?.groups ?? {}).map(([id, bySeverity]) => ({
    id,
    label: t(`page.group-${['config', 'host', 'hosts'].includes(id) ? id : 'other'}`),
    bySeverity,
  })),
)

function stageLabel(run: AuditRun): string {
  if (run.status === 'queued') return t('page.status-queued')

  return run.progress.stage ? t(`page.stage-${run.progress.stage}`) : t('page.status-running')
}

async function load(): Promise<void> {
  try {
    latest.value = await api.latest()
  } catch (error) {
    toast.danger(message(error))
  }

  clearTimeout(timer)

  if (latest.value?.active) {
    timer = setTimeout(() => void load(), POLL_MS)
  }
}

async function start(): Promise<void> {
  starting.value = true

  try {
    await api.start(scope.value)
    toast.success(t('page.started'))
    await load()
  } catch (error) {
    toast.danger(message(error))
  } finally {
    starting.value = false
  }
}

async function cancel(run: AuditRun): Promise<void> {
  try {
    await api.cancel(run.id)
    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

onMounted(load)
onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <audit-layout
    :base="props.base"
    :settings-path="props.settingsPath"
    current="overview"
    :card="false"
  >
    <div class="wx-audit-overview">
      <wx-card v-if="!latest"><wx-skeleton :rows="4" /></wx-card>

      <template v-else>
        <wx-alert v-if="latest.queue.sync" type="warning" :description="t('page.sync-queue')" />

        <wx-alert
          v-if="latest.last && latest.last.status === 'failed'"
          type="danger"
          :description="t('page.last-failed', { error: latest.last.error ?? '' })"
        />

        <wx-card v-if="active" class="wx-audit-overview__active">
          <div class="wx-audit-overview__row">
            <wx-text weight="semibold">{{ stageLabel(active) }}</wx-text>
            <wx-text size="sm" tone="muted" class="wx-audit-overview__url">{{
              active.base_url
            }}</wx-text>
            <wx-button
              v-if="canRun"
              size="sm"
              class="wx-audit-overview__end"
              @click="cancel(active)"
              >{{ t('page.cancel') }}</wx-button
            >
          </div>
          <wx-progress indeterminate :aria-label="stageLabel(active)" />
        </wx-card>

        <wx-card v-else-if="canRun && !latest.queue.sync">
          <div class="wx-audit-overview__row">
            <wx-segmented v-model="scope" :options="scopes" :aria-label="t('page.scope')" />
            <wx-text size="sm" tone="muted">{{
              scope === 'full' ? t('page.scope-full-help') : t('page.scope-quick-help')
            }}</wx-text>
            <wx-button
              type="primary"
              icon="refresh"
              :loading="starting"
              class="wx-audit-overview__end"
              @click="start"
              >{{ t('page.run') }}</wx-button
            >
          </div>
        </wx-card>

        <wx-card v-if="!done && !active">
          <wx-empty
            icon="check-circle"
            :title="t('page.never')"
            :description="t('page.never-help')"
          />
        </wx-card>

        <template v-if="done && counts">
          <wx-card>
            <div class="wx-audit-overview__summary">
              <wx-progress
                type="circle"
                :value="counts.health"
                :status="healthStatus"
                show-value
                :formatter="(value: number) => `${value}%`"
                :aria-label="t('page.health')"
              />
              <div class="wx-audit-overview__stats">
                <wx-statistic
                  v-for="severity in severities"
                  :key="severity.key"
                  :title="t(severity.label)"
                  :value="counts.severity[severity.key] ?? 0"
                  :tone="(counts.severity[severity.key] ?? 0) > 0 ? severity.tone : 'muted'"
                />
                <wx-statistic :title="t('page.new')" :value="counts.new" />
                <wx-statistic :title="t('page.fixed')" :value="counts.fixed" tone="success" />
              </div>
            </div>
            <div class="wx-audit-overview__meta">
              <wx-text size="sm" tone="muted">
                {{ t('page.last-run') }} <wx-date :value="done.finished_at" /> ·
                {{ t(`page.scope-${done.scope}`) }} ·
              </wx-text>
              <wx-text size="sm" tone="muted" class="wx-audit-overview__url">{{
                done.base_url
              }}</wx-text>
            </div>
          </wx-card>

          <wx-card v-if="groups.length" :title="t('page.by-group')">
            <ul class="wx-audit-overview__groups">
              <li v-for="group in groups" :key="group.id">
                <wx-text size="sm">{{ group.label }}</wx-text>
                <span class="wx-audit-overview__badges">
                  <template v-for="severity in severities" :key="severity.key">
                    <wx-badge
                      v-if="(group.bySeverity[severity.key] ?? 0) > 0"
                      :type="severity.tone"
                      size="sm"
                      >{{ t(severity.label) }} · {{ group.bySeverity[severity.key] }}</wx-badge
                    >
                  </template>
                </span>
              </li>
            </ul>
          </wx-card>

          <wx-card v-else>
            <wx-empty icon="check-circle" :title="t('page.clean')" size="sm" />
          </wx-card>

          <wx-card :title="t('page.sources')">
            <div class="wx-audit-overview__badges">
              <wx-badge v-for="source in counts.sources.searched" :key="source" type="success">{{
                source
              }}</wx-badge>
            </div>
            <div v-if="counts.sources.missing.length" class="wx-audit-overview__missing">
              <wx-text size="sm" tone="warning">{{ t('page.sources-missing') }}</wx-text>
              <wx-badge v-for="source in counts.sources.missing" :key="source" type="warning">{{
                source
              }}</wx-badge>
            </div>
          </wx-card>
        </template>
      </template>
    </div>
  </audit-layout>
</template>

<style scoped>
.wx-audit-overview {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  container-type: inline-size;
}

.wx-audit-overview__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8) var(--wx-space-12);
  margin-bottom: var(--wx-space-8);
}

.wx-audit-overview__end {
  margin-left: auto;
}

.wx-audit-overview__summary {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-24);
}

.wx-audit-overview__stats {
  display: flex;
  flex: 1 1 auto;
  flex-wrap: wrap;
  gap: var(--wx-space-16) var(--wx-space-32);
}

.wx-audit-overview__meta {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-4);
  margin-top: var(--wx-space-16);
}

/* A long domain breaks rather than pushing the card sideways on a phone. */
.wx-audit-overview__url {
  min-width: 0;
  overflow-wrap: anywhere;
}

.wx-audit-overview__groups {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-audit-overview__groups li {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-8);
}

.wx-audit-overview__badges,
.wx-audit-overview__missing {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-6);
}

.wx-audit-overview__missing {
  margin-top: var(--wx-space-12);
}
</style>
