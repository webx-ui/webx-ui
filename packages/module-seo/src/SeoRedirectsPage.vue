<script setup lang="ts">
import { computed, ref } from 'vue'
import {
  useAdmin,
  useErrorText,
  useTranslate,
  WxDate,
  WxRowMenu,
  type RowAction,
} from '@webx-ui/module-admin'
import {
  confirm,
  createModal,
  toast,
  WxBadge,
  WxButton,
  WxTable,
  WxText,
  type TableColumn,
  type TableState,
} from '@webx-ui/core'
import SeoLayout from './SeoLayout.vue'
import SeoRedirectDialog from './SeoRedirectDialog.vue'
import TestUrlDialog from './TestUrlDialog.vue'
import { createSeoApi } from './api'
import { useSeoMessages } from './i18n'
import type { SeoPage, SeoRedirect } from './types'

/**
 * The addresses that have moved.
 *
 * Busiest first by default, which is the order this list is worked on: a redirect nobody has
 * followed since the site was rebuilt is the one worth deleting, and it sits at the bottom.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/seo' })

const context = useAdmin()
const api = createSeoApi(context)
useSeoMessages()

const t = useTranslate('webx-seo')
/* Not the server's `message`: the panel says how a request failed in its own words (§13.3). */
const message = useErrorText()

const page = ref<SeoPage<SeoRedirect> | null>(null)
const loading = ref(false)

let last: TableState = { page: 1, perPage: 20, sort: null, search: '' }

const canManage = context.can('seo.manage')

const edit = createModal<SeoRedirect, { redirect: SeoRedirect | null }>(SeoRedirectDialog)
const test = createModal<void, Record<string, never>>(TestUrlDialog)

const columns = computed<TableColumn<SeoRedirect>[]>(() => [
  { key: 'pattern', label: t('page.address'), sortable: true },
  { key: 'target', label: t('page.target') },
  { key: 'status', label: t('page.status'), align: 'center', hideBelow: 560 },
  { key: 'hits', label: t('page.hits'), align: 'center', sortable: true, hideBelow: 760 },
  {
    key: 'last_hit_at',
    label: t('page.last-hit'),
    sortable: true,
    hideBelow: 900,
    hideOnCards: true,
  },
  { key: 'is_active', label: t('page.state'), align: 'center', hideBelow: 660 },
  { key: 'actions', label: '', width: 56, align: 'right', hidden: !canManage, hideOnCards: true },
])

/** One line, and the same menu every other list of the panel puts a record's actions in. */
function actionsFor(redirect: SeoRedirect): RowAction[] {
  return [
    {
      key: 'delete',
      icon: 'trash',
      label: t('page.delete'),
      danger: true,
      run: () => remove(redirect),
    },
  ]
}

async function load(state: TableState): Promise<void> {
  last = state
  loading.value = true

  try {
    page.value = await api.redirects({
      q: state.search,
      sort: state.sort ? `${state.sort.order === 'desc' ? '-' : ''}${state.sort.key}` : '-hits',
      page: state.page,
      per_page: state.perPage,
    })
  } finally {
    loading.value = false
  }
}

/**
 * Opening a redirect is editing it, so a reader who may not manage these has nowhere to go: the
 * table is told so (`:clickable="canManage"`) and withholds the pointer, the highlight and the
 * click together, rather than promising a dialog that would not open (§13).
 */
async function open(redirect: SeoRedirect | null): Promise<void> {
  const saved = await edit({ redirect })

  if (saved) void load(last)
}

async function remove(redirect: SeoRedirect): Promise<void> {
  const agreed = await confirm({
    title: t('page.delete-title', { pattern: redirect.pattern }),
    message: t('page.delete-text'),
    confirmText: t('page.delete'),
    cancelText: t('page.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.removeRedirect(redirect.id)
    toast.success(t('page.deleted'))
    void load(last)
  } catch (error) {
    toast.danger(message(error))
  }
}
</script>

<template>
  <seo-layout :base="props.base" current="redirects" @test="test({})">
    <template v-if="canManage" #actions>
      <wx-button type="primary" icon="add" @click="open(null)">
        {{ t('page.new-redirect') }}
      </wx-button>
    </template>

    <wx-table
      :data="page"
      :columns="columns"
      row-key="id"
      searchable
      :clickable="canManage"
      :hover="canManage"
      flush
      :loading="loading"
      :search-placeholder="t('page.search-redirects')"
      :empty-text="t('page.empty')"
      @row-click="open"
      @state-change="load"
    >
      <template #cell-pattern="{ row }">
        <wx-text mono size="sm">{{ row.pattern }}</wx-text>
        <!-- Said out loud rather than refused on save: the middleware steps over it, and a
               row that quietly does nothing is a row nobody ever fixes. -->
        <wx-badge v-if="row.is_loop" type="warning">{{ t('page.loop') }}</wx-badge>
      </template>

      <template #cell-target="{ row }">
        <wx-text mono size="sm">{{ row.target }}</wx-text>
      </template>

      <template #cell-last_hit_at="{ row }">
        <wx-date :value="row.last_hit_at" :tone="row.last_hit_at ? 'default' : 'muted'" />
      </template>

      <template #cell-is_active="{ row }">
        <wx-badge :type="row.is_active ? 'success' : 'default'" dot>
          {{ row.is_active ? t('page.active') : t('page.inactive') }}
        </wx-badge>
      </template>

      <template #card-actions="{ row }">
        <wx-row-menu v-if="canManage" :actions="actionsFor(row)" :label="row.pattern" />
      </template>

      <template #cell-actions="{ row }">
        <wx-row-menu :actions="actionsFor(row)" :label="row.pattern" />
      </template>
    </wx-table>
  </seo-layout>
</template>
