<script setup lang="ts">
import { computed, ref } from 'vue'
import {
  useAdmin,
  useErrorText,
  useTranslate,
  WxFilterChips,
  type AppliedFilter,
  rowMenuWidth,
  WxRowMenu,
  type RowAction,
  type ScreenAction,
} from '@webx-ui/module-admin'
import {
  confirm,
  createModal,
  toast,
  WxActionBar,
  WxBadge,
  WxButton,
  WxEmpty,
  WxFormItem,
  WxSwitch,
  WxTable,
  WxText,
  type RowKey,
  type TableColumn,
  type TableState,
} from '@webx-ui/core'
import SeoLayout from './SeoLayout.vue'
import SeoLinkDialog from './SeoLinkDialog.vue'
import SeoLinkHeadingDialog from './SeoLinkHeadingDialog.vue'
import SeoImportDialog from './SeoImportDialog.vue'
import TestUrlDialog from './TestUrlDialog.vue'
import { createSeoApi } from './api'
import { linksEnabled } from './features'
import { useSeoMessages } from './i18n'
import type { SeoLinkBlock, SeoPage } from './types'

/**
 * Interlinking (§18.4): the donors, each with its block of links.
 *
 * A list of donors rather than of links, because that is how a brief arrives and how it is
 * replaced — a page and what it points at — and because a list of links would show the same
 * donor on fifty rows. The broken ones are counted here so that the reader can find them
 * without opening every donor; the filter keeps only those.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/seo' })

const context = useAdmin()
const api = createSeoApi(context)
useSeoMessages()

const t = useTranslate('webx-seo')
/* The funnel, "reset all" and the selection boxes are the panel's own words. */
const panel = useTranslate('webx-admin')
/* Not the server's `message`: the panel says how a request failed in its own words (§13.3). */
const message = useErrorText()

const page = ref<SeoPage<SeoLinkBlock> | null>(null)
const loading = ref(false)
const broken = ref(false)
const selected = ref<RowKey[]>([])

let last: TableState = { page: 1, perPage: 20, sort: null, search: '' }

const canManage = context.can('seo.manage')
const enabled = computed(() => linksEnabled(context))

const edit = createModal<SeoLinkBlock, { block: SeoLinkBlock | null }>(SeoLinkDialog)
const importing = createModal<boolean, { kind?: 'links' | 'faq' }>(SeoImportDialog)
const heading = createModal<boolean, { ids: number[] }>(SeoLinkHeadingDialog)
const test = createModal<void, Record<string, never>>(TestUrlDialog)

const applied = computed<AppliedFilter[]>(() =>
  broken.value
    ? [{ key: 'broken', label: t('links.with-broken'), clear: () => (broken.value = false) }]
    : [],
)

const columns = computed<TableColumn<SeoLinkBlock>[]>(() => [
  { key: 'donor', label: t('links.donor') },
  { key: 'heading', label: t('links.heading'), hideBelow: 760 },
  { key: 'links_count', label: t('links.links'), align: 'center', hideBelow: 520 },
  { key: 'broken_count', label: t('links.broken'), align: 'center', hideBelow: 620 },
  { key: 'is_active', label: t('page.state'), align: 'center', hideBelow: 900 },
  {
    key: 'actions',
    label: '',
    width: rowMenuWidth,
    align: 'right',
    hidden: !canManage,
    hideOnCards: true,
  },
])

function actionsFor(block: SeoLinkBlock): RowAction[] {
  return [
    {
      key: 'delete',
      icon: 'trash',
      label: t('page.delete'),
      danger: true,
      run: () => remove(block),
    },
  ]
}

async function load(state: TableState): Promise<void> {
  last = state

  if (!enabled.value) return

  loading.value = true

  try {
    page.value = await api.links({
      q: state.search,
      broken: broken.value,
      page: state.page,
      per_page: state.perPage,
    })
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

function reload(): void {
  void load({ ...last, page: 1 })
}

async function open(block: SeoLinkBlock | null): Promise<void> {
  const saved = await edit({ block })

  if (saved) void load(last)
}

async function remove(block: SeoLinkBlock): Promise<void> {
  const agreed = await confirm({
    title: t('links.delete-title', { donor: block.donor.url }),
    message: t('page.delete-text'),
    confirmText: t('page.delete'),
    cancelText: t('page.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.removeLink(block.id)
    toast.success(t('page.deleted'))
    selected.value = selected.value.filter((key) => key !== block.id)
    void load(last)
  } catch (error) {
    toast.danger(message(error))
  }
}

async function upload(): Promise<void> {
  if (await importing({})) reload()
}

/** The ticked donors, or — with nothing ticked — every donor under a prefix typed in the dialog. */
async function setHeading(ids: number[]): Promise<void> {
  if (await heading({ ids })) {
    selected.value = []
    void load(last)
  }
}

/* The main action and the file tools. Declared, because on a phone the head folds them. */
const actions = computed<ScreenAction[]>(() => {
  if (!enabled.value) return []

  const exports: ScreenAction[] = [
    {
      key: 'export-csv',
      label: t('links.export-csv'),
      icon: 'download',
      menu: true,
      href: api.exportLinksUrl('csv'),
    },
    {
      key: 'export-xlsx',
      label: t('links.export-xlsx'),
      icon: 'download',
      menu: true,
      href: api.exportLinksUrl('xlsx'),
    },
  ]

  if (!canManage) return exports

  return [
    {
      key: 'new',
      label: t('links.new'),
      icon: 'plus',
      primary: true,
      run: () => void open(null),
    },
    { key: 'import', label: t('links.import'), icon: 'upload', run: () => void upload() },
    {
      key: 'heading',
      label: t('links.heading-bulk'),
      icon: 'edit',
      menu: true,
      run: () => void setHeading([]),
    },
    ...exports,
  ]
})

function onBroken(): void {
  reload()
}
</script>

<template>
  <div class="wx-seo-links">
    <seo-layout :base="props.base" current="links" :actions="actions" @test="test({})">
      <wx-empty v-if="!enabled" :description="t('links.off')" />

      <wx-table
        v-else
        v-model:selected="selected"
        :data="page"
        :columns="columns"
        row-key="id"
        searchable
        :selectable="canManage"
        :select-row-label="panel('filters.select-row')"
        :select-all-label="panel('filters.select-all')"
        :clickable="canManage"
        :hover="canManage"
        flush
        :loading="loading"
        :search-placeholder="t('links.search')"
        :empty-text="t('links.list-empty')"
        :filters-count="applied.length"
        :filters-label="panel('filters.title')"
        @row-click="open"
        @state-change="load"
      >
        <template #filters>
          <wx-form-item :label="t('links.broken')">
            <wx-switch v-model="broken" :label="t('links.with-broken')" @change="onBroken" />
          </wx-form-item>
        </template>

        <template #applied>
          <wx-filter-chips :filters="applied" />
        </template>

        <template #cell-donor="{ row }">
          <wx-text mono size="sm" truncate>{{ row.donor.url }}</wx-text>
          <wx-badge v-if="row.donor.broken" type="danger">{{ t('links.gone') }}</wx-badge>
        </template>

        <template #cell-heading="{ row }">
          <wx-text v-if="row.heading" size="sm" truncate>{{ row.heading }}</wx-text>
          <wx-text v-else size="sm" tone="muted">{{ t('links.heading-default') }}</wx-text>
        </template>

        <template #cell-broken_count="{ row }">
          <wx-badge v-if="row.broken_count > 0" type="danger">{{ row.broken_count }}</wx-badge>
          <wx-text v-else size="sm" tone="muted">0</wx-text>
        </template>

        <template #cell-is_active="{ row }">
          <wx-badge :type="row.is_active ? 'success' : 'default'" dot>
            {{ row.is_active ? t('page.active') : t('page.inactive') }}
          </wx-badge>
        </template>

        <template #card-actions="{ row }">
          <wx-row-menu v-if="canManage" :actions="actionsFor(row)" :label="row.donor.url" />
        </template>

        <template #cell-actions="{ row }">
          <wx-row-menu :actions="actionsFor(row)" :label="row.donor.url" />
        </template>
      </wx-table>
    </seo-layout>

    <!-- The ticked donors and the one thing done to several at once (§18.1, decision 5). -->
    <wx-action-bar v-if="selected.length > 0" class="wx-seo-links__bar" sticky>
      <template #state>
        <wx-text weight="medium">{{ t('links.selected', { count: selected.length }) }}</wx-text>
      </template>

      <wx-button type="primary" @click="setHeading(selected.map(Number))">
        {{ t('links.heading-bulk') }}
      </wx-button>
    </wx-action-bar>
  </div>
</template>

<style scoped>
.wx-seo-links {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}

/* Two words and a number: the bar's 220px state box would push the button to a line of its own. */
.wx-seo-links__bar :deep(.wx-action-bar__state) {
  flex: 0 1 auto;
}
</style>
