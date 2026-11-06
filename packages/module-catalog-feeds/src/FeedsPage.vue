<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  confirm,
  createModal,
  toast,
  WxBadge,
  WxTable,
  WxText,
  type TableColumn,
  type TableState,
} from '@webx-ui/core'
import {
  rowMenuWidth,
  useAdmin,
  useErrorText,
  useTranslate,
  WxDate,
  WxListScreen,
  WxRowMenu,
  type RowAction,
  type ScreenAction,
} from '@webx-ui/module-admin'
import { createFeedsApi } from './api'
import { copyAddress } from './clipboard'
import { NAMESPACE, useCatalogFeedsMessages } from './i18n'
import NewFeedDialog from './NewFeedDialog.vue'
import type { BuildStatus, FeedPreset, FeedRow, FeedsMeta, FeedsPage } from './types'

/**
 * The feeds (§11 of the feeds spec): name, format, language and what came of the last build, with
 * the address to copy and «Rebuild» in the menu of a row. «New feed» asks for a preset; everything
 * after it is the form of a copy.
 *
 * While a build runs the list reads itself again, the way the exchange's does: a build ends in the
 * queue, and nothing tells the panel.
 */
defineOptions({ name: 'WxCatalogFeedsPage' })

const props = withDefaults(defineProps<{ base?: string }>(), { base: '/catalog' })

const POLL = 4000

const admin = useAdmin()
const api = createFeedsApi(admin)
const router = useRouter()
useCatalogFeedsMessages()

const t = useTranslate(NAMESPACE)
const panel = useTranslate('webx-admin')
const message = useErrorText()

const newFeed = createModal<string | null, { presets: FeedPreset[] }>(NewFeedDialog)

const list = computed(() => `${props.base}/feeds`)
const canManage = computed(() => admin.can('catalog.manage'))

const page = ref<FeedsPage | null>(null)
const meta = ref<FeedsMeta | null>(null)
const loading = ref(true)
let pageNumber = 1
let perPage = 25
let timer: ReturnType<typeof setTimeout> | undefined
let asked = 0

const title = computed(
  () =>
    admin.state.manifest?.modules.find((module) => module.id === 'catalog-feeds')?.title ??
    t('module.title'),
)

const going = (row: FeedRow) =>
  row.last_build?.status === 'queued' || row.last_build?.status === 'running'

async function load(): Promise<void> {
  const mine = ++asked

  clearTimeout(timer)

  try {
    const answer = await api.list(pageNumber, perPage)

    if (mine !== asked) return

    page.value = answer

    if (answer.data.some(going)) timer = setTimeout(() => void load(), POLL)
  } catch (error) {
    if (mine === asked) toast.danger(message(error))
  } finally {
    if (mine === asked) loading.value = false
  }
}

function onState(state: TableState): void {
  pageNumber = state.page
  perPage = state.perPage
  void load()
}

// The table asks for its first page by itself, through `onState`.
onMounted(() => {
  if (canManage.value) void api.meta().then((answer) => (meta.value = answer))
})

onBeforeUnmount(() => {
  asked++
  clearTimeout(timer)
})

const columns = computed<TableColumn<FeedRow>[]>(() => [
  { key: 'name', label: t('feed.name'), minWidth: 200 },
  { key: 'format', label: t('feed.format'), width: 90, hideBelow: 560 },
  { key: 'locale', label: t('feed.locale'), width: 100, hideBelow: 640 },
  { key: 'products', label: t('panel.products'), width: 110, align: 'right', hideBelow: 720 },
  { key: 'built', label: t('panel.built'), width: 160, hideBelow: 840 },
  { key: 'status', label: t('panel.status'), width: 130 },
  { key: 'actions', label: '', width: rowMenuWidth, align: 'right' },
])

const TONES: Record<BuildStatus, 'default' | 'info' | 'success' | 'danger'> = {
  queued: 'default',
  running: 'info',
  done: 'success',
  failed: 'danger',
}

const statusOf = (row: FeedRow) => row.last_build?.status ?? 'none'
const toneOf = (row: FeedRow) => (row.last_build ? TONES[row.last_build.status] : 'default')

function open(row: FeedRow): void {
  void router.push(`${list.value}/${row.id}`)
}

async function create(): Promise<void> {
  const presets = meta.value?.presets ?? (await api.meta()).presets
  const preset = await newFeed({ presets })

  if (preset === null || preset === undefined) return

  void router.push({ path: `${list.value}/new`, query: preset === '' ? {} : { preset } })
}

async function rebuild(row: FeedRow): Promise<void> {
  try {
    await api.rebuild(row.id)
    toast.success(t('panel.rebuild-queued'))
    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

async function remove(row: FeedRow): Promise<void> {
  const agreed = await confirm({
    title: t('panel.delete-title', { name: row.name }),
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

function actionsFor(row: FeedRow): RowAction[] {
  const actions: RowAction[] = [
    { key: 'open', label: t('panel.open'), icon: 'edit', run: () => open(row) },
  ]

  if (!canManage.value) return actions

  actions.push(
    {
      key: 'copy',
      label: t('panel.copy-address'),
      icon: 'copy',
      disabled: row.address === null,
      run: () => void copyAddress(row.address ?? '', t('panel.address-copied')),
    },
    {
      key: 'rebuild',
      label: t('panel.rebuild'),
      icon: 'refresh',
      disabled: !row.is_active || going(row),
      hint: !row.is_active
        ? t('panel.rebuild-inactive')
        : going(row)
          ? t('panel.rebuild-going')
          : undefined,
      run: () => void rebuild(row),
    },
    {
      key: 'delete',
      label: t('panel.delete'),
      icon: 'trash',
      danger: true,
      run: () => void remove(row),
    },
  )

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
          run: () => void create(),
        },
      ]
    : [],
)
</script>

<template>
  <wx-list-screen :title="title" :actions="actions">
    <wx-table
      :loading-text="panel('filters.loading')"
      :data="page ?? []"
      :columns="columns"
      row-key="id"
      clickable
      hover
      flush
      layout="fixed"
      :loading="loading"
      :empty-text="t('panel.empty')"
      :aria-label="t('module.title')"
      @row-click="open"
      @state-change="onState"
    >
      <template #cell-name="{ row }">
        <span class="wx-catalog-feeds__name">{{ row.name }}</span>
        <wx-text v-if="!row.is_active" size="sm" tone="muted">{{ t('panel.inactive') }}</wx-text>
      </template>
      <template #cell-format="{ row }">{{ row.driver ?? t(`feed.format-${row.format}`) }}</template>
      <template #cell-products="{ row }">{{ row.last_build?.written ?? '—' }}</template>
      <template #cell-built="{ row }">
        <wx-date v-if="row.last_build?.finished_at" :value="row.last_build.finished_at" />
        <template v-else>—</template>
      </template>
      <template #cell-status="{ row }">
        <wx-badge :type="toneOf(row)" dot size="sm">{{
          t(`panel.status-${statusOf(row)}`)
        }}</wx-badge>
      </template>
      <template #cell-actions="{ row }">
        <wx-row-menu :actions="actionsFor(row)" :label="row.name" />
      </template>
    </wx-table>
  </wx-list-screen>
</template>

<style scoped>
.wx-catalog-feeds__name {
  display: block;
  overflow-wrap: anywhere;
}
</style>
