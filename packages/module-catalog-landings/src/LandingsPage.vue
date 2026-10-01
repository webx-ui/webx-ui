<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter, type LocationQueryRaw } from 'vue-router'
import {
  confirm,
  createModal,
  toast,
  useLocales,
  localizedValue,
  WxBadge,
  WxButton,
  WxFormItem,
  WxIcon,
  WxSwitch,
  WxTable,
  WxText,
  WxTooltip,
  WxTreeSelect,
  type TableColumn,
  type TableState,
  type TreeSelectValue,
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
import { useCategoryTree, type CategoryNode } from '@webx-ui/module-catalog'
import { createLandingsApi } from './api'
import GenerateDialog from './GenerateDialog.vue'
import { NAMESPACE, useCatalogLandingsMessages } from './i18n'
import type { GenerateRun, LandingQuery, LandingRow, LandingsPage } from './types'

/**
 * The landings (§8.1 of the landings spec): name and address, the base, the set as chips, the
 * products, the state and the mark of attention; filtered by the base as a tree (the whole
 * catalogue is a choice of its own), «needs attention», published, empty, the bin; searched by
 * name and address. The filters live in the address, so coming back from a landing lands where
 * the list was left.
 */
defineOptions({ name: 'WxCatalogLandingsPage' })

const props = withDefaults(defineProps<{ base?: string }>(), { base: '/catalog' })

/** The base filter's value for «the whole catalogue», beside the categories' ids. */
const ROOT = 'root'

const admin = useAdmin()
const api = createLandingsApi(admin)
const tree = useCategoryTree(admin)
const route = useRoute()
const router = useRouter()
const locales = useLocales()
useCatalogLandingsMessages()

const t = useTranslate(NAMESPACE)
const panel = useTranslate('webx-admin')
const message = useErrorText()

const generate = createModal<GenerateRun | null, { base: string }>(GenerateDialog)

const list = computed(() => `${props.base}/landings`)
const canManage = computed(() => admin.can('catalog.manage'))

const page = ref<LandingsPage | null>(null)
const loading = ref(true)

const flag = (key: string) => route.query[key] === '1'

const search = ref(String(route.query.q ?? ''))
const pageNumber = ref(Number(route.query.page ?? 1) || 1)
const chosenBase = ref<string | null>(route.query.category ? String(route.query.category) : null)
const attention = ref(flag('attention'))
const published = ref(flag('published'))
const empty = ref(flag('empty'))
const trashed = ref(flag('trashed'))
let perPage = 25

const title = computed(
  () =>
    admin.state.manifest?.modules.find((module) => module.id === 'catalog-landings')?.title ??
    t('module.title'),
)

/** The tree of categories with «the whole catalogue» on top of it. */
const baseNodes = computed<CategoryNode[]>(() => [
  {
    id: ROOT as unknown as number,
    name: t('landing.whole-catalog'),
    parent_id: null,
    children: [],
  } as unknown as CategoryNode,
  ...(tree.nodes.value ?? []),
])

const baseName = computed(() => {
  if (chosenBase.value === null) return ''
  if (chosenBase.value === ROOT) return t('landing.whole-catalog')

  const find = (nodes: CategoryNode[]): string =>
    nodes.reduce<string>(
      (found, node) =>
        found || (String(node.id) === chosenBase.value ? node.name : find(node.children ?? [])),
      '',
    )

  return find(tree.nodes.value ?? []) || `#${chosenBase.value}`
})

const applied = computed<AppliedFilter[]>(() => {
  const chips: AppliedFilter[] = []

  if (chosenBase.value !== null) {
    chips.push({
      key: 'base',
      label: `${t('panel.filter-base')}: ${baseName.value}`,
      clear: () => (chosenBase.value = null),
    })
  }

  for (const [key, on, word] of [
    ['attention', attention, 'panel.filter-attention'],
    ['published', published, 'panel.filter-published'],
    ['empty', empty, 'panel.filter-empty'],
    ['trashed', trashed, 'panel.filter-trashed'],
  ] as const) {
    if (on.value) chips.push({ key, label: t(word), clear: () => (on.value = false) })
  }

  return chips
})

function clearFilters(): void {
  chosenBase.value = null
  attention.value = false
  published.value = false
  empty.value = false
  trashed.value = false
}

function query(state?: TableState): LandingQuery {
  return {
    q: state?.search ?? search.value,
    category: chosenBase.value ?? undefined,
    attention: attention.value || undefined,
    published: published.value || undefined,
    empty: empty.value || undefined,
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
  const q = query()

  if (q.q) wanted.q = q.q
  if (q.category) wanted.category = q.category
  if (q.page && q.page > 1) wanted.page = String(q.page)

  for (const key of ['attention', 'published', 'empty', 'trashed'] as const) {
    if (q[key]) wanted[key] = '1'
  }

  void router.replace({ query: wanted })
}

function onState(state: TableState): void {
  perPage = state.perPage
  void load(state)
}

watch([chosenBase, attention, published, empty, trashed], () => {
  pageNumber.value = 1
  remember()
  void load()
})

watch([search, pageNumber], remember)

const columns = computed<TableColumn<LandingRow>[]>(() => [
  { key: 'name', label: t('panel.column-name'), minWidth: 220 },
  { key: 'category', label: t('panel.column-base'), width: 180, hideBelow: 760 },
  { key: 'chips', label: t('panel.column-set'), minWidth: 180, hideBelow: 640 },
  { key: 'products_count', label: t('panel.column-count'), width: 110, align: 'right' },
  { key: 'state', label: t('panel.column-state'), width: 170, hideBelow: 900 },
  { key: 'updated_at', label: t('panel.column-updated'), width: 130, hideBelow: 1180 },
  { key: 'actions', label: '', width: rowMenuWidth, align: 'right' },
])

const nameOf = (row: LandingRow) =>
  localizedValue(row.name, locales.active.value, '') || `#${row.id}`
const slugOf = (row: LandingRow) => localizedValue(row.slug, locales.active.value, '')

function open(row: LandingRow): void {
  void router.push(`${list.value}/${row.id}`)
}

async function publish(row: LandingRow, on: boolean): Promise<void> {
  try {
    await api.publish(row.id, on)
    toast.success(t(on ? 'panel.published-toast' : 'panel.unpublished-toast'))
    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

async function remove(row: LandingRow): Promise<void> {
  const agreed = await confirm({
    title: t('panel.delete-title', { name: nameOf(row) }),
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

async function restore(row: LandingRow): Promise<void> {
  try {
    await api.restore(row.id)
    toast.success(t('panel.restored'))
    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

function actionsFor(row: LandingRow): RowAction[] {
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

  if (row.url)
    actions.push({ key: 'site', icon: 'link', label: t('panel.open-site'), href: row.url })

  if (canManage.value) {
    actions.push(
      row.is_published
        ? {
            key: 'unpublish',
            icon: 'eye-off',
            label: t('panel.unpublish'),
            run: () => void publish(row, false),
          }
        : {
            key: 'publish',
            icon: 'eye',
            label: t('panel.publish'),
            run: () => void publish(row, true),
          },
      {
        key: 'delete',
        icon: 'trash',
        label: t('panel.delete'),
        danger: true,
        run: () => void remove(row),
      },
    )
  }

  return actions
}

async function bulk(): Promise<void> {
  const run = await generate({ base: props.base })

  if (run) await load()
}

const actions = computed<ScreenAction[]>(() =>
  canManage.value
    ? [
        {
          key: 'new',
          label: t('panel.new'),
          icon: 'plus',
          primary: true,
          run: () =>
            void router.push({
              path: `${list.value}/new`,
              query:
                chosenBase.value && chosenBase.value !== ROOT ? { category: chosenBase.value } : {},
            }),
        },
        { key: 'generate', label: t('panel.generate'), icon: 'grid', run: () => void bulk() },
      ]
    : [],
)

const emptyText = computed(() =>
  applied.value.length > 0 || search.value ? t('panel.nothing-found') : t('panel.empty'),
)

onMounted(() => {
  void tree.load().catch(() => undefined)
})
</script>

<template>
  <div class="wx-catalog-landings">
    <wx-list-screen :title="title" :actions="actions">
      <wx-table
        v-model:search="search"
        v-model:page="pageNumber"
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
          <wx-form-item :label="t('panel.filter-base')">
            <wx-tree-select
              :model-value="chosenBase"
              :nodes="baseNodes"
              node-key="id"
              label-key="name"
              children-key="children"
              check-strictly
              filterable
              clearable
              size="sm"
              @update:model-value="
                (picked: TreeSelectValue) =>
                  (chosenBase =
                    picked === null || picked === undefined || Array.isArray(picked)
                      ? null
                      : String(picked))
              "
            />
          </wx-form-item>
          <wx-form-item :label="t('panel.filter-attention')">
            <wx-switch v-model="attention" size="sm" />
          </wx-form-item>
          <wx-form-item :label="t('panel.filter-published')">
            <wx-switch v-model="published" size="sm" />
          </wx-form-item>
          <wx-form-item :label="t('panel.filter-empty')">
            <wx-switch v-model="empty" size="sm" />
          </wx-form-item>
          <wx-form-item :label="t('panel.filter-trashed')">
            <wx-switch v-model="trashed" size="sm" />
          </wx-form-item>

          <wx-button v-if="applied.length > 0" variant="text" size="sm" block @click="clearFilters">
            <template #icon><wx-icon name="close" /></template>
            {{ panel('filters.reset') }}
          </wx-button>
        </template>

        <template #applied>
          <wx-filter-chips :filters="applied" />
        </template>

        <template #cell-name="{ row }">
          <div class="wx-catalog-landings__name">
            <span class="wx-catalog-landings__title">{{ nameOf(row) }}</span>
            <span class="wx-catalog-landings__slug">/{{ slugOf(row) }}/</span>
          </div>
        </template>

        <template #cell-category="{ row }">
          <wx-text size="sm" truncate :tone="row.category === null ? 'muted' : undefined">
            {{ row.category ?? t('landing.whole-catalog') }}
          </wx-text>
        </template>

        <template #cell-chips="{ row }">
          <div class="wx-catalog-landings__chips">
            <wx-badge v-for="chip in row.chips" :key="chip.key" size="sm" variant="soft">
              {{ chip.label }}: {{ chip.text }}
            </wx-badge>
          </div>
        </template>

        <template #cell-products_count="{ row }">
          <wx-text v-if="row.products_count === null" size="sm" tone="muted">
            {{ t('panel.not-counted') }}
          </wx-text>
          <wx-badge v-else-if="row.products_count === 0" type="warning" size="sm" round>0</wx-badge>
          <span v-else class="wx-catalog-landings__count">{{ row.products_count }}</span>
        </template>

        <template #cell-state="{ row }">
          <div class="wx-catalog-landings__state">
            <wx-badge v-if="row.deleted_at" dot size="sm">{{ t('panel.in-bin') }}</wx-badge>
            <wx-badge v-else :type="row.is_published ? 'success' : 'default'" dot size="sm">
              {{ t(row.is_published ? 'panel.published' : 'panel.unpublished') }}
            </wx-badge>
            <wx-tooltip v-if="row.attention" :content="t(`landing.attention-${row.attention}`)">
              <wx-badge type="warning" size="sm" round>
                <wx-icon name="warning" size="sm" />
                <span class="wx-catalog-landings__sr">{{ t('landing.attention') }}</span>
              </wx-badge>
            </wx-tooltip>
          </div>
        </template>

        <template #cell-updated_at="{ row }">
          <wx-date :value="row.updated_at" compact />
        </template>

        <template #cell-actions="{ row }">
          <wx-row-menu :actions="actionsFor(row)" :label="nameOf(row)" />
        </template>
      </wx-table>
    </wx-list-screen>
  </div>
</template>

<style scoped>
.wx-catalog-landings {
  min-width: 0;
}

.wx-catalog-landings__name {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.wx-catalog-landings__title,
.wx-catalog-landings__slug {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-catalog-landings__slug {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.wx-catalog-landings__chips {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-4);
}

.wx-catalog-landings__count {
  font-variant-numeric: tabular-nums;
}

.wx-catalog-landings__state {
  display: flex;
  align-items: center;
  gap: var(--wx-space-6);
}

.wx-catalog-landings__sr {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}
</style>
