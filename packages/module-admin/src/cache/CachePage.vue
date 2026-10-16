<script setup lang="ts">
import { computed, onMounted, ref, useTemplateRef } from 'vue'
import {
  confirm,
  toast,
  WxAlert,
  WxBadge,
  WxCard,
  WxDescriptions,
  WxDescriptionsItem,
  WxTable,
  WxText,
  type TableColumn,
} from '@webx-ui/core'
import { useAdmin } from '../admin'
import { useErrorText } from '../errors'
import { useTranslate } from '../i18n'
import type { RowAction, ScreenAction } from '../types'
import HistoryFeed from '../HistoryFeed.vue'
import ListScreen from '../ListScreen.vue'
import RowMenu from '../RowMenu.vue'
import { createCacheApi, type CacheGroupRow, type CacheReport } from './api'

/**
 * «System → Cache» (WEBX_UI_CACHE.md §5).
 *
 * Opened when the site shows what the panel no longer says. The page answers in that order: is
 * anything cached at all (the store), which groups there are and what resets each of them by
 * itself, and the button — all groups, or the one that is stale. Every press is a row in the
 * journal below, because a press is a `bump` somebody forgot, and the row is where that shows.
 */
const context = useAdmin()
const t = useTranslate('webx-admin')
const message = useErrorText()
const api = createCacheApi(context)

const report = ref<CacheReport | null>(null)
const loading = ref(true)
const failed = ref(false)
const resetting = ref<string | null>(null)
const history = useTemplateRef<InstanceType<typeof HistoryFeed>>('history')

const canReset = computed(() => context.can('cache.reset'))
const volatile = computed(
  () =>
    report.value !== null && (report.value.driver === 'array' || report.value.driver === 'null'),
)

const actions = computed<ScreenAction[]>(() => {
  const list: ScreenAction[] = []

  if (canReset.value && report.value !== null && report.value.groups.length > 0) {
    list.push({
      key: 'reset-all',
      label: t('cache.reset-all'),
      icon: 'refresh',
      primary: true,
      loading: resetting.value === '*',
      disabled: resetting.value !== null,
      run: () => void reset(null),
    })
  }

  list.push({
    key: 'refresh',
    label: t('cache.refresh'),
    icon: 'refresh',
    menu: true,
    run: () => void load(),
  })

  return list
})

const columns = computed<TableColumn<CacheGroupRow>[]>(() => {
  const list: TableColumn<CacheGroupRow>[] = [
    { key: 'title', label: t('cache.group'), minWidth: 180 },
    { key: 'models', label: t('cache.models'), minWidth: 200, hideBelow: 640 },
    { key: 'version', label: t('cache.version'), align: 'right', width: 140, hideBelow: 480 },
  ]

  if (canReset.value) {
    list.push({ key: 'actions', label: '', width: 56, align: 'right' })
  }

  return list
})

function rowActions(row: CacheGroupRow): RowAction[] {
  return [
    {
      key: 'reset',
      label: t('cache.reset-group'),
      icon: 'refresh',
      disabled: resetting.value !== null,
      run: () => void reset(row),
    },
  ]
}

async function load(): Promise<void> {
  try {
    report.value = await api.report()
    failed.value = false
  } catch {
    failed.value = report.value === null
  } finally {
    loading.value = false
  }
}

async function reset(row: CacheGroupRow | null): Promise<void> {
  const agreed = await confirm({
    title:
      row === null
        ? t('cache.reset-all-title')
        : t('cache.reset-group-title', { group: row.title }),
    message: row === null ? t('cache.reset-all-text') : t('cache.reset-group-text'),
    confirmText: t('cache.reset'),
    cancelText: t('cache.cancel'),
  })

  if (!agreed) return

  resetting.value = row === null ? '*' : row.id

  try {
    const result = await api.reset(row === null ? null : row.id)
    report.value = result
    toast.success(
      row === null ? t('cache.reset-done') : t('cache.reset-group-done', { group: row.title }),
    )
    void history.value?.reload()
  } catch (error) {
    toast.danger(message(error))
  } finally {
    resetting.value = null
  }
}

function hours(seconds: number): string {
  return t('cache.ttl-value', { hours: Math.max(1, Math.round(seconds / 3600)).toString() })
}

onMounted(() => void load())
</script>

<template>
  <list-screen :title="t('cache.title')" :actions="actions" :card="false">
    <div class="wx-cache">
      <wx-text tone="muted">{{ t('cache.lead') }}</wx-text>

      <wx-alert v-if="failed" type="danger">{{ t('cache.load-failed') }}</wx-alert>

      <template v-if="report">
        <wx-alert v-if="!report.enabled" type="warning">{{ t('cache.disabled') }}</wx-alert>
        <wx-alert v-else-if="volatile" type="warning">{{ t('cache.store-array') }}</wx-alert>

        <wx-card bordered :title="t('cache.store')">
          <wx-descriptions :columns="1" label-width="160px">
            <wx-descriptions-item :label="t('cache.store')">
              <span class="wx-cache__store">
                <code>{{ report.store }}</code>
                <wx-badge :type="volatile || !report.enabled ? 'warning' : 'success'">
                  {{ report.driver }}
                </wx-badge>
              </span>
            </wx-descriptions-item>
            <wx-descriptions-item :label="t('cache.ttl')">{{
              hours(report.ttl)
            }}</wx-descriptions-item>
          </wx-descriptions>
        </wx-card>

        <wx-card bordered :title="t('cache.groups')">
          <wx-table
            :data="report.groups"
            :columns="columns"
            row-key="id"
            flush
            :cards-below="400"
            :empty-text="t('cache.no-groups')"
            :aria-label="t('cache.groups')"
          >
            <template #cell-title="{ row }">
              <div class="wx-cache__group">
                <wx-text weight="medium">{{ row.title }}</wx-text>
                <code v-if="row.title !== row.id">{{ row.id }}</code>
              </div>
            </template>
            <template #cell-models="{ row }">
              <span v-if="row.models.length" class="wx-cache__models">
                <code v-for="model in row.models" :key="model">{{ model }}</code>
              </span>
              <wx-text v-else size="sm" tone="muted">{{ t('cache.models-none') }}</wx-text>
            </template>
            <template #cell-version="{ row }">
              <code>{{ row.version }}</code>
            </template>
            <template #cell-actions="{ row }">
              <row-menu :actions="rowActions(row)" :label="row.title" />
            </template>
          </wx-table>
        </wx-card>

        <!-- Every reset, signed: the journal is what says a `bump` is missing somewhere. -->
        <wx-card bordered :title="t('cache.history')">
          <history-feed :id="0" ref="history" type="cache" title="" />
        </wx-card>
      </template>
    </div>
  </list-screen>
</template>

<style scoped>
.wx-cache {
  container-type: inline-size;
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  min-width: 0;
}

.wx-cache__store,
.wx-cache__models {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-6);
}

.wx-cache__group {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--wx-space-2);
  min-width: 0;
}

.wx-cache code {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-sm);
  overflow-wrap: anywhere;
}
</style>
