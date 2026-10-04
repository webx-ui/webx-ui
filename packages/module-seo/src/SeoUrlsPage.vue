<script setup lang="ts">
import { computed, ref, type Component } from 'vue'
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
  WxBadge,
  WxFormItem,
  WxSelect,
  WxSwitch,
  WxTable,
  WxText,
  type TableColumn,
  type TableState,
} from '@webx-ui/core'
import SeoSitemapCard from './SeoSitemapCard.vue'
import SeoImportDialog from './SeoImportDialog.vue'
import SeoUrlDialog from './SeoUrlDialog.vue'
import TestUrlDialog from './TestUrlDialog.vue'
import SeoLayout from './SeoLayout.vue'
import { createSeoApi } from './api'
import { faqEnabled } from './features'
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
/* The funnel and "reset all" are the panel's own words. */
const panel = useTranslate('webx-admin')
/* Not the server's `message`: the panel says how a request failed in its own words (§13.3). */
const message = useErrorText()

const page = ref<SeoPage<SeoUrlRule> | null>(null)
const loading = ref(false)
/* A word rather than an empty string: a select reads "" as nothing chosen and shows a blank
   control where the option said "any kind". */
const kind = ref('any')
/* Page FAQs (§18.5) are only there where a developer turned them on. */
const faqOn = computed(() => faqEnabled(context))
const withFaq = ref(false)

let last: TableState = { page: 1, perPage: 20, sort: null, search: '' }

const canManage = context.can('seo.manage')

const edit = createModal<SeoUrlRule, { rule: SeoUrlRule | null; mediaField?: Component }>(
  SeoUrlDialog,
)
const test = createModal<void, Record<string, never>>(TestUrlDialog)
const importing = createModal<boolean, { kind?: 'links' | 'faq' }>(SeoImportDialog)

const kindOptions = computed(() => [
  { value: 'any', label: t('page.any-kind') },
  { value: 'exact', label: t('page.exact') },
  { value: 'mask', label: t('page.mask') },
  { value: 'regex', label: t('page.regex') },
])

/** The one dropdown, said in the reader's words while the panel it lives in is shut. */
const applied = computed<AppliedFilter[]>(() => [
  ...(kind.value === 'any'
    ? []
    : [
        {
          key: 'kind',
          label: `${t('page.filter-kind')}: ${
            kindOptions.value.find((option) => option.value === kind.value)?.label ?? kind.value
          }`,
          clear: () => (kind.value = 'any'),
        },
      ]),
  ...(faqOn.value && withFaq.value
    ? [{ key: 'faq', label: t('faq.with-faq'), clear: () => (withFaq.value = false) }]
    : []),
])

const columns = computed<TableColumn<SeoUrlRule>[]>(() => [
  { key: 'pattern', label: t('page.address') },
  { key: 'match_type', label: t('page.kind'), hideBelow: 560 },
  { key: 'title', label: t('page.title'), hideBelow: 900 },
  { key: 'priority', label: t('page.priority'), align: 'center', hideBelow: 760 },
  {
    key: 'faq_count',
    label: t('faq.column'),
    align: 'center',
    hideBelow: 620,
    hidden: !faqOn.value,
  },
  { key: 'is_active', label: t('page.state'), align: 'center', hideBelow: 660 },
  {
    key: 'actions',
    label: '',
    width: rowMenuWidth,
    align: 'right',
    hidden: !canManage,
    hideOnCards: true,
  },
])

/**
 * What a rule offers: one line, and it is the destructive one — everything else about a rule is
 * done by opening it. Still a menu, because a red bin standing in every row shouts louder than
 * deleting a rule deserves, and because a reader should find a record's actions in the same
 * place in every list of the panel (§20).
 */
function actionsFor(rule: SeoUrlRule): RowAction[] {
  return [
    {
      key: 'delete',
      icon: 'trash',
      label: t('page.delete'),
      danger: true,
      run: () => remove(rule),
    },
  ]
}

async function load(state: TableState): Promise<void> {
  last = state
  loading.value = true

  try {
    page.value = await api.urls({
      q: state.search,
      match_type: kind.value === 'any' ? null : (kind.value as 'exact' | 'mask' | 'regex'),
      has_faq: faqOn.value && withFaq.value,
      page: state.page,
      per_page: state.perPage,
    })
  } finally {
    loading.value = false
  }
}

/**
 * Opening a rule is editing it, so a reader who may not manage these has nowhere to go: the
 * table is told so (`:clickable="canManage"`) and withholds the pointer, the highlight and the
 * click together, rather than promising a dialog that would not open (§13).
 */
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
    toast.danger(message(error))
  }
}

async function importFaq(): Promise<void> {
  if (await importing({ kind: 'faq' })) void load({ ...last, page: 1 })
}

/* The FAQ files, in the ···: they are about the pages these rules are written for. */
const faqActions = computed<ScreenAction[]>(() => {
  if (!faqOn.value) return []

  return [
    ...(canManage
      ? [
          {
            key: 'faq-import',
            label: t('faq.import'),
            icon: 'upload',
            menu: true,
            run: () => void importFaq(),
          } satisfies ScreenAction,
        ]
      : []),
    {
      key: 'faq-export-csv',
      label: t('faq.export-csv'),
      icon: 'download',
      menu: true,
      href: api.exportFaqUrl('csv'),
    },
    {
      key: 'faq-export-xlsx',
      label: t('faq.export-xlsx'),
      icon: 'download',
      menu: true,
      href: api.exportFaqUrl('xlsx'),
    },
  ]
})

/* What the section offers. Declared, because on a phone the head folds it into the ···. */
const actions = computed<ScreenAction[]>(() => [
  ...(canManage
    ? [
        {
          key: 'rule',
          label: t('page.new-rule'),
          icon: 'plus',
          primary: true,
          run: () => void open(null),
        } satisfies ScreenAction,
      ]
    : []),
  ...faqActions.value,
])

function onFaqFilter(): void {
  void load({ ...last, page: 1 })
}
</script>

<template>
  <seo-layout :base="props.base" current="rules" :actions="actions" @test="test({})">
    <!-- Above the rules because it answers the question the rules are usually opened for:
         why a page is not in the index. -->
    <seo-sitemap-card />

    <wx-table
      :data="page"
      :columns="columns"
      row-key="id"
      searchable
      :clickable="canManage"
      :hover="canManage"
      flush
      :loading="loading"
      :search-placeholder="t('page.search-rules')"
      :empty-text="t('page.empty')"
      :filters-count="applied.length"
      :filters-label="panel('filters.title')"
      @row-click="open"
      @state-change="load"
    >
      <!-- Behind the funnel, and what it is set to comes back as a chip beside it. -->
      <template #filters>
        <wx-form-item :label="t('page.filter-kind')">
          <wx-select v-model="kind" :options="kindOptions" size="sm" />
        </wx-form-item>

        <wx-switch
          v-if="faqOn"
          v-model="withFaq"
          :label="t('faq.with-faq')"
          @change="onFaqFilter"
        />
      </template>

      <template #applied>
        <wx-filter-chips :filters="applied" />
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

      <template #cell-faq_count="{ row }">
        <wx-text v-if="row.faq_count" size="sm">{{ row.faq_count }}</wx-text>
        <wx-text v-else size="sm" tone="muted">—</wx-text>
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
