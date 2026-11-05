<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { toast, WxTable, type TableColumn } from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate, WxDate, WxListScreen } from '@webx-ui/module-admin'
import { createFeedsApi } from './api'
import { NAMESPACE, useCatalogFeedsMessages } from './i18n'
import type { FeedRow } from './types'

/**
 * The feeds (§11 of the feeds spec): name, format, language and what came of the last build.
 * Nothing opens from here yet: the form and «New feed» arrive with the panel's stage.
 */
defineOptions({ name: 'WxCatalogFeedsPage' })

const admin = useAdmin()
const api = createFeedsApi(admin)
useCatalogFeedsMessages()

const t = useTranslate(NAMESPACE)
const panel = useTranslate('webx-admin')
const message = useErrorText()

const rows = ref<FeedRow[] | null>(null)

onMounted(async () => {
  try {
    rows.value = (await api.list()).data
  } catch (error) {
    toast.danger(message(error))
    rows.value = []
  }
})

const columns = computed<TableColumn<FeedRow>[]>(() => [
  { key: 'name', label: t('feed.name'), minWidth: 200 },
  { key: 'format', label: t('feed.format'), width: 100, hideBelow: 480 },
  { key: 'locale', label: t('feed.locale'), width: 100, hideBelow: 560 },
  { key: 'products', label: t('panel.products'), width: 110, hideBelow: 640 },
  { key: 'built', label: t('panel.built'), width: 160, hideBelow: 720 },
  { key: 'status', label: t('panel.status'), width: 130 },
])
</script>

<template>
  <wx-list-screen :title="t('module.title')">
    <wx-table
      :loading-text="panel('filters.loading')"
      :data="rows ?? []"
      :columns="columns"
      row-key="id"
      flush
      layout="fixed"
      :loading="rows === null"
      :empty-text="t('panel.empty')"
      :aria-label="t('module.title')"
    >
      <template #cell-products="{ row }">{{ row.last_build?.written ?? '—' }}</template>
      <template #cell-built="{ row }">
        <wx-date v-if="row.last_build?.finished_at" :value="row.last_build.finished_at" />
        <template v-else>—</template>
      </template>
      <template #cell-status="{ row }">
        {{ t(`panel.status-${row.last_build?.status ?? 'none'}`) }}
      </template>
    </wx-table>
  </wx-list-screen>
</template>
