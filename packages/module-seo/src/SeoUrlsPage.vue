<script setup lang="ts">
import { computed, ref, type Component } from 'vue'
import { useAdmin, useTranslate } from '@webx-ui/module-admin'
import {
  confirm,
  createModal,
  toast,
  WxAction,
  WxActions,
  WxBadge,
  WxButton,
  WxCard,
  WxSelect,
  WxTable,
  WxText,
  type TableColumn,
  type TableState,
} from '@webx-ui/core'
import SeoUrlDialog from './SeoUrlDialog.vue'
import TestUrlDialog from './TestUrlDialog.vue'
import SeoLayout from './SeoLayout.vue'
import { createSeoApi } from './api'
import { useSeoMessages } from './i18n'
import { ruleTitle } from './rule'
import type { SeoPage, SeoUrlRule } from './types'

/**
 * The rules written for addresses.
 *
 * Listed in the order the site tries them — exact, then mask, then regular expression, by
 * priority inside each group — so that reading the table top to bottom is reading what will
 * happen. Sorting it any other way would hide the one thing a person comes here to work out.
 */
const props = withDefaults(defineProps<{ base?: string; mediaField?: Component }>(), {
  base: '/seo',
  mediaField: undefined,
})

const context = useAdmin()
const api = createSeoApi(context)
useSeoMessages()

const t = useTranslate('webx-seo')

const page = ref<SeoPage<SeoUrlRule> | null>(null)
const loading = ref(false)
/* A word rather than an empty string: a select reads "" as nothing chosen and shows a blank
   control where the option said "any kind". */
const kind = ref('any')

let last: TableState = { page: 1, perPage: 20, sort: null, search: '' }

const canManage = context.can('seo.manage')

const edit = createModal<SeoUrlRule, { rule: SeoUrlRule | null; mediaField?: Component }>(
  SeoUrlDialog,
)
const test = createModal<void, Record<string, never>>(TestUrlDialog)

const kindOptions = computed(() => [
  { value: 'any', label: t('page.any-kind') },
  { value: 'exact', label: t('page.exact') },
  { value: 'mask', label: t('page.mask') },
  { value: 'regex', label: t('page.regex') },
])

const columns = computed<TableColumn<SeoUrlRule>[]>(() => [
  { key: 'pattern', label: t('page.address') },
  { key: 'match_type', label: t('page.kind'), hideBelow: 560 },
  { key: 'title', label: t('page.title'), hideBelow: 900 },
  { key: 'priority', label: t('page.priority'), align: 'center', hideBelow: 760 },
  { key: 'is_active', label: t('page.state'), align: 'center', hideBelow: 660 },
  {
    key: 'actions',
    label: '',
    width: 56,
    align: 'right',
    hidden: !canManage,
    hideOnCards: true,
  },
])

async function load(state: TableState): Promise<void> {
  last = state
  loading.value = true

  try {
    page.value = await api.urls({
      q: state.search,
      match_type: kind.value === 'any' ? null : (kind.value as 'exact' | 'mask' | 'regex'),
      page: state.page,
      per_page: state.perPage,
    })
  } finally {
    loading.value = false
  }
}

async function open(rule: SeoUrlRule | null): Promise<void> {
  const saved = await edit({ rule, mediaField: props.mediaField })

  if (saved) void load(last)
}

async function remove(rule: SeoUrlRule): Promise<void> {
  const agreed = await confirm({
    title: t('page.delete-title', { pattern: rule.pattern }),
    message: t('page.delete-text'),
    confirmText: t('page.delete'),
    cancelText: t('page.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.removeUrl(rule.id)
    toast.success(t('page.deleted'))
    void load(last)
  } catch (error) {
    toast.danger((error as { body?: { message?: string } }).body?.message ?? String(error))
  }
}
</script>

<template>
  <seo-layout :base="props.base" current="rules" @test="test({})">
    <wx-card>
      <template #header>{{ t('page.rules') }}</template>

      <template v-if="canManage" #extra>
        <wx-button type="primary" icon="add" @click="open(null)">
          {{ t('page.new-rule') }}
        </wx-button>
      </template>

      <wx-table
        :data="page"
        :columns="columns"
        row-key="id"
        searchable
        hover
        flush
        :loading="loading"
        :search-placeholder="t('page.search-rules')"
        :empty-text="t('page.empty')"
        @state-change="load"
        @row-click="canManage ? open($event) : undefined"
      >
        <template #actions>
          <wx-select v-model="kind" :options="kindOptions" size="sm" style="width: 200px" />
        </template>

        <template #cell-pattern="{ row }">
          <wx-text mono size="sm">{{ row.pattern }}</wx-text>
        </template>

        <template #cell-match_type="{ row }">
          <wx-badge>{{ t(`page.${row.match_type}`) }}</wx-badge>
        </template>

        <template #cell-title="{ row }">
          <wx-text v-if="ruleTitle(row)" size="sm">{{ ruleTitle(row) }}</wx-text>
          <wx-text v-else size="sm" tone="muted">—</wx-text>
        </template>

        <template #cell-is_active="{ row }">
          <wx-badge :type="row.is_active ? 'success' : 'default'" dot>
            {{ row.is_active ? t('page.active') : t('page.inactive') }}
          </wx-badge>
        </template>

        <template #card-actions="{ row }">
          <wx-actions v-if="canManage" size="sm" @click.stop>
            <wx-action type="remove" :title="t('page.delete')" @click="remove(row)" />
          </wx-actions>
        </template>

        <template #cell-actions="{ row }">
          <!-- `.stop`: the row opens the rule, and deleting one is not opening it. -->
          <wx-actions size="sm" @click.stop>
            <wx-action type="remove" :title="t('page.delete')" @click="remove(row)" />
          </wx-actions>
        </template>
      </wx-table>
    </wx-card>
  </seo-layout>
</template>
