<script setup lang="ts">
import { computed, ref } from 'vue'
import { useTranslate, useAdmin } from '@webx-ui/module-admin'
import {
  createModal,
  WxAlert,
  WxBadge,
  WxCard,
  WxLink,
  WxTable,
  WxText,
  type TableColumn,
  type TableState,
} from '@webx-ui/core'
import SeoLayout from './SeoLayout.vue'
import TestUrlDialog from './TestUrlDialog.vue'
import { createSeoApi } from './api'
import { useSeoMessages } from './i18n'
import type { SeoAlias, SeoPage } from './types'

/**
 * The redirects nobody wrote.
 *
 * Renaming a page or moving a branch leaves its old address behind, answering 301 — which is
 * the only reason an external link, a bookmark or a search result survives an edit in the panel.
 * They are shown here because a reader who lands on a dead address does not care which half of
 * the system made the redirect, and an editor chasing one should not have to.
 *
 * Nothing on this screen writes. These rows belong to the entity that moved: it makes them, and
 * deleting it takes them with it. An editor who wants a different answer for an old address
 * writes a rule of their own on the next tab — those are tried first, and this one stops
 * mattering.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/seo' })

const context = useAdmin()
const api = createSeoApi(context)
useSeoMessages()

const t = useTranslate('webx-seo')

const page = ref<SeoPage<SeoAlias> | null>(null)
const loading = ref(false)

const test = createModal<void, Record<string, never>>(TestUrlDialog)

/** More than one language on the site is the only reason to say which one a row is in. */
const multilingual = computed(() => (context.state.manifest?.locales?.length ?? 1) > 1)

const columns = computed<TableColumn<SeoAlias>[]>(() => [
  { key: 'pattern', label: t('page.address'), sortable: false },
  { key: 'target', label: t('page.target') },
  {
    key: 'locale',
    label: t('page.language'),
    align: 'center',
    hidden: !multilingual.value,
    hideBelow: 760,
  },
  { key: 'created_at', label: t('page.moved-at'), hideBelow: 900, hideOnCards: true },
])

async function load(state: TableState): Promise<void> {
  loading.value = true

  try {
    page.value = await api.aliases({
      q: state.search,
      page: state.page,
      per_page: state.perPage,
    })
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <seo-layout :base="props.base" current="aliases" @test="test({})">
    <wx-card>
      <template #header>{{ t('page.automatic') }}</template>

      <wx-alert
        type="info"
        variant="soft"
        :description="t('page.aliases-help')"
        class="wx-seo-aliases__note"
      />

      <wx-table
        :data="page"
        :columns="columns"
        row-key="id"
        searchable
        flush
        :loading="loading"
        :search-placeholder="t('page.search-aliases')"
        :empty-text="t('page.aliases-empty')"
        @state-change="load"
      >
        <template #cell-pattern="{ row }">
          <wx-text mono size="sm">{{ row.pattern }}</wx-text>
        </template>

        <!-- The address it leads to now, as a link: this is the one place in the panel where an
             editor can walk from a dead address to the live page without knowing the section it
             lives in. -->
        <template #cell-target="{ row }">
          <wx-link v-if="row.target && row.target_url" :href="row.target_url" external size="sm">
            <wx-text mono size="sm">{{ row.target }}</wx-text>
          </wx-link>
          <wx-badge v-else type="warning">{{ t('page.gone') }}</wx-badge>
        </template>

        <template #cell-locale="{ row }">
          <wx-badge>{{ row.locale }}</wx-badge>
        </template>

        <template #cell-created_at="{ row }">
          <wx-text size="sm" tone="muted">
            {{ row.created_at ? new Date(row.created_at).toLocaleString() : '' }}
          </wx-text>
        </template>
      </wx-table>
    </wx-card>
  </seo-layout>
</template>

<style scoped>
.wx-seo-aliases__note {
  margin-bottom: var(--wx-space-12);
}
</style>
