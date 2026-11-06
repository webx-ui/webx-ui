<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter, type LocationQueryRaw } from 'vue-router'
import {
  confirm,
  localizedValue,
  toast,
  useLocales,
  WxBadge,
  WxButton,
  WxFormItem,
  WxIcon,
  WxSwitch,
  WxTable,
  WxText,
  WxTooltip,
  type TableColumn,
  type TableState,
} from '@webx-ui/core'
import {
  rowMenuWidth,
  useAdmin,
  useErrorText,
  useTranslate,
  WxDate,
  WxFilterChips,
  WxListScreen,
  WxRowMenu,
  type AppliedFilter,
  type RowAction,
  type ScreenAction,
} from '@webx-ui/module-admin'
import { createCollectionsApi, tagOf } from './api'
import { NAMESPACE, useCatalogCollectionsMessages } from './i18n'
import type { CollectionQuery, CollectionRow, CollectionsPage } from './types'

/**
 * The collections (§6.1 of the collections spec): title and key, how it is filled, the view, the
 * state and the mark of attention; filtered by «needs attention», «turned off» and the bin; searched
 * by title and key. The key is copied with its tag, which is what a developer pastes. The filters
 * live in the address, so coming back from a collection lands where the list was left.
 */
defineOptions({ name: 'WxCatalogCollectionsPage' })

const props = withDefaults(defineProps<{ base?: string }>(), { base: '/catalog' })

/** The presets this package has words for; a site's own reads as «by a filter». */
const WORDED = ['new', 'on-sale', 'label']

const admin = useAdmin()
const api = createCollectionsApi(admin)
const route = useRoute()
const router = useRouter()
const locales = useLocales()
useCatalogCollectionsMessages()

const t = useTranslate(NAMESPACE)
const panel = useTranslate('webx-admin')
const message = useErrorText()

const list = computed(() => `${props.base}/collections`)
const canManage = computed(() => admin.can('catalog.manage'))

const page = ref<CollectionsPage | null>(null)
const loading = ref(true)

const flag = (key: string) => route.query[key] === '1'

const search = ref(String(route.query.q ?? ''))
const pageNumber = ref(Number(route.query.page ?? 1) || 1)
const attention = ref(flag('attention'))
const disabled = ref(flag('disabled'))
const trashed = ref(flag('trashed'))
let perPage = 25

const title = computed(
  () =>
    admin.state.manifest?.modules.find((module) => module.id === 'catalog-collections')?.title ??
    t('module.title'),
)

const applied = computed<AppliedFilter[]>(() => {
  const chips: AppliedFilter[] = []

  for (const [key, on, word] of [
    ['attention', attention, 'panel.filter-attention'],
    ['disabled', disabled, 'panel.filter-disabled'],
    ['trashed', trashed, 'panel.filter-trashed'],
  ] as const) {
    if (on.value) chips.push({ key, label: t(word), clear: () => (on.value = false) })
  }

  return chips
})

function clearFilters(): void {
  attention.value = false
  disabled.value = false
  trashed.value = false
}

function query(state?: TableState): CollectionQuery {
  return {
    q: state?.search ?? search.value,
    attention: attention.value || undefined,
    enabled: disabled.value ? false : undefined,
    trashed: trashed.value || undefined,
    page: state?.page ?? pageNumber.value,
    per_page: state?.perPage ?? perPage,
  }
}

let asked = 0

async function load(state?: TableState): Promise<void> {
  const mine = ++asked

  loading.value = true

  try {
    const answer = await api.list(query(state))

    if (mine === asked) page.value = answer
  } catch (error) {
    toast.danger(message(error))
  } finally {
    if (mine === asked) loading.value = false
  }
}

function remember(): void {
  const wanted: LocationQueryRaw = {}

  if (search.value) wanted.q = search.value
  if (pageNumber.value > 1) wanted.page = String(pageNumber.value)
  if (attention.value) wanted.attention = '1'
  if (disabled.value) wanted.disabled = '1'
  if (trashed.value) wanted.trashed = '1'

  void router.replace({ query: wanted })
}

function onState(state: TableState): void {
  perPage = state.perPage
  void load(state)
}

watch([attention, disabled, trashed], () => {
  pageNumber.value = 1
  remember()
  void load()
})

watch([search, pageNumber], remember)

const columns = computed<TableColumn<CollectionRow>[]>(() => [
  { key: 'title', label: t('panel.column-title'), minWidth: 220 },
  { key: 'source', label: t('panel.column-source'), width: 170, hideBelow: 640 },
  { key: 'view', label: t('panel.column-view'), width: 110, hideBelow: 760 },
  { key: 'state', label: t('panel.column-state'), width: 150, hideBelow: 520 },
  { key: 'updated_at', label: t('panel.column-updated'), width: 130, hideBelow: 1000 },
  { key: 'actions', label: '', width: rowMenuWidth, align: 'right' },
])

const titleOf = (row: CollectionRow) =>
  localizedValue(row.title, locales.active.value, '') || row.key

function sourceOf(row: CollectionRow): string {
  if (row.source === 'manual') return t('collection.source-manual')

  return row.preset && WORDED.includes(row.preset)
    ? t(`collection.preset-${row.preset}`)
    : t('collection.source-filter')
}

function open(row: CollectionRow): void {
  void router.push(`${list.value}/${row.id}`)
}

async function copy(row: CollectionRow): Promise<void> {
  const tag = tagOf(row.key)

  try {
    await navigator.clipboard.writeText(tag)
    toast.success(t('panel.copied'))
  } catch {
    // No clipboard on this page: the words themselves are what the developer needs.
    toast.danger(tag)
  }
}

async function remove(row: CollectionRow): Promise<void> {
  const agreed = await confirm({
    title: t('panel.delete-title', { name: titleOf(row) }),
    message: t('panel.delete-text'),
    confirmText: t('panel.delete'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.remove(row.id)
    toast.success(t('panel.deleted'))
    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

async function restore(row: CollectionRow): Promise<void> {
  try {
    await api.restore(row.id)
    toast.success(t('panel.restored'))
    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

function actionsFor(row: CollectionRow): RowAction[] {
  if (row.deleted_at !== null) {
    return canManage.value
      ? [
          {
            key: 'restore',
            icon: 'refresh',
            label: t('panel.restore'),
            run: () => void restore(row),
          },
        ]
      : []
  }

  const actions: RowAction[] = [
    { key: 'open', icon: 'edit', label: t('panel.open'), run: () => open(row) },
  ]

  if (canManage.value) {
    actions.push({
      key: 'delete',
      icon: 'trash',
      label: t('panel.delete'),
      danger: true,
      run: () => void remove(row),
    })
  }

  return actions
}

const actions = computed<ScreenAction[]>(() =>
  canManage.value
    ? [
        {
          key: 'new',
          label: t('panel.new'),
          icon: 'plus',
          primary: true,
          run: () => void router.push(`${list.value}/new`),
        },
      ]
    : [],
)

const emptyText = computed(() =>
  applied.value.length > 0 || search.value ? t('panel.nothing-found') : t('panel.empty'),
)
</script>

<template>
  <div class="wx-catalog-collections">
    <wx-list-screen :title="title" :actions="actions">
      <wx-table
        v-model:search="search"
        v-model:page="pageNumber"
        :loading-text="panel('filters.loading')"
        :data="page"
        :columns="columns"
        row-key="id"
        searchable
        clickable
        hover
        flush
        :loading="loading"
        layout="fixed"
        :filters-count="applied.length"
        :filters-label="panel('filters.title')"
        :search-placeholder="t('panel.search')"
        :empty-text="emptyText"
        :aria-label="title"
        @row-click="open"
        @state-change="onState"
      >
        <template #filters>
          <wx-form-item>
            <wx-switch v-model="attention" size="sm" :label="t('panel.filter-attention')" />
          </wx-form-item>
          <wx-form-item>
            <wx-switch v-model="disabled" size="sm" :label="t('panel.filter-disabled')" />
          </wx-form-item>
          <wx-form-item>
            <wx-switch v-model="trashed" size="sm" :label="t('panel.filter-trashed')" />
          </wx-form-item>

          <wx-button v-if="applied.length > 0" variant="text" size="sm" block @click="clearFilters">
            <template #icon><wx-icon name="close" /></template>
            {{ panel('filters.reset') }}
          </wx-button>
        </template>

        <template #applied>
          <wx-filter-chips :filters="applied" />
        </template>

        <template #cell-title="{ row }">
          <div class="wx-catalog-collections__name">
            <span class="wx-catalog-collections__title">{{ titleOf(row) }}</span>
            <span class="wx-catalog-collections__key">
              <code class="wx-catalog-collections__code">{{ row.key }}</code>
              <wx-tooltip :content="t('panel.copy')">
                <wx-button
                  variant="text"
                  size="sm"
                  :aria-label="`${t('panel.copy')}: ${row.key}`"
                  @click.stop="copy(row)"
                >
                  <template #icon><wx-icon name="copy" size="sm" /></template>
                </wx-button>
              </wx-tooltip>
            </span>
          </div>
        </template>

        <template #cell-source="{ row }">
          <wx-text size="sm" truncate>{{ sourceOf(row) }}</wx-text>
        </template>

        <template #cell-view="{ row }">
          <wx-text size="sm" truncate>{{ t(`collection.view-${row.view}`) }}</wx-text>
        </template>

        <template #cell-state="{ row }">
          <div class="wx-catalog-collections__state">
            <wx-badge v-if="row.deleted_at" dot size="sm">{{ t('panel.in-bin') }}</wx-badge>
            <wx-badge v-else :type="row.is_enabled ? 'success' : 'default'" dot size="sm">
              {{ t(row.is_enabled ? 'panel.enabled' : 'panel.disabled') }}
            </wx-badge>
            <wx-tooltip v-if="row.attention" :content="t(`collection.attention-${row.attention}`)">
              <wx-badge type="warning" size="sm" round>
                <wx-icon name="warning" size="sm" />
                <span class="wx-catalog-collections__sr">{{ t('collection.attention') }}</span>
              </wx-badge>
            </wx-tooltip>
          </div>
        </template>

        <template #cell-updated_at="{ row }">
          <wx-date :value="row.updated_at" compact />
        </template>

        <template #cell-actions="{ row }">
          <wx-row-menu :actions="actionsFor(row)" :label="titleOf(row)" />
        </template>
      </wx-table>
    </wx-list-screen>
  </div>
</template>

<style scoped>
.wx-catalog-collections {
  min-width: 0;
}

.wx-catalog-collections__name {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.wx-catalog-collections__title {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-catalog-collections__key {
  display: flex;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
}

.wx-catalog-collections__code {
  overflow: hidden;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-catalog-collections__state {
  display: flex;
  align-items: center;
  gap: var(--wx-space-6);
}

.wx-catalog-collections__sr {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}
</style>
