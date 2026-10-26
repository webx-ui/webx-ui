<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
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
import {
  confirm,
  createModal,
  toast,
  WxBadge,
  WxText,
  WxTable,
  type TabItem,
  type TableColumn,
  type TableState,
} from '@webx-ui/core'
import ConfiguratorCreateDialog from './ConfiguratorCreateDialog.vue'
import { createConfiguratorsApi } from './api'
import { useConfiguratorsMessages } from './i18n'
import type { ConfiguratorDetail, ConfiguratorRow } from './types'

/**
 * The configurators of the site (§13.1): a short list — a site has a handful — with what each is
 * called, whether it is on the site, the form it sends requests to and how many steps it has. A
 * new one is a name and a key, and opens straight in the editor; the bin rides beside the list.
 */
defineOptions({ name: 'WxConfiguratorsPage' })

const props = withDefaults(defineProps<{ base?: string }>(), { base: '/configurators' })

const context = useAdmin()
const api = createConfiguratorsApi(context)
const router = useRouter()
useConfiguratorsMessages()

const t = useTranslate('webx-configurator')
const message = useErrorText()

const canManage = computed(() => context.can('configurators.manage'))

const rows = ref<ConfiguratorRow[]>([])
const loading = ref(true)
const view = ref<string>('')
const term = ref('')

const inBin = computed(() => view.value === 'trashed')

const views = computed<TabItem[]>(() => [
  { value: '', label: t('configurator.all') },
  { value: 'trashed', label: t('configurator.trashed'), icon: 'trash' },
])

const create = createModal<ConfiguratorDetail>(ConfiguratorCreateDialog)

async function load(): Promise<void> {
  loading.value = true

  try {
    rows.value = await api.list({ trashed: inBin.value })
  } catch (error) {
    rows.value = []
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

watch(inBin, () => void load(), { immediate: true })

/** The whole list is here: narrowed as it is typed, by the name and by the key. */
const shown = computed(() => {
  const words = term.value.trim().toLowerCase()

  if (words === '') return rows.value

  return rows.value.filter(
    (row) => row.title.toLowerCase().includes(words) || row.key.includes(words),
  )
})

const columns = computed<TableColumn<ConfiguratorRow>[]>(() => [
  { key: 'title', label: t('configurator.col-title'), minWidth: 220 },
  { key: 'form', label: t('configurator.col-form'), width: 180, hideBelow: 900 },
  { key: 'steps', label: t('configurator.col-steps'), width: 90, align: 'right', hideBelow: 640 },
  { key: 'updated', label: t('configurator.col-updated'), width: 170, hideBelow: 760 },
  { key: 'status', label: t('configurator.col-status'), width: 170 },
  { key: 'actions', label: '', width: rowMenuWidth, align: 'right' },
])

const emptyText = computed(() => {
  if (loading.value) return undefined
  if (term.value.trim() !== '') return t('configurator.empty-search')

  return inBin.value ? t('configurator.empty-bin') : t('configurator.empty')
})

function onState(state: TableState): void {
  term.value = state.search
}

function open(row: ConfiguratorRow): void {
  if (inBin.value) return

  void router.push(`${props.base}/${row.id}`)
}

async function add(): Promise<void> {
  const made = await create({})

  if (made) void router.push(`${props.base}/${made.configurator.id}`)
}

async function remove(row: ConfiguratorRow): Promise<void> {
  const agreed = await confirm({
    title: t('configurator.delete-title', { name: row.title }),
    message: t('configurator.delete-text'),
    confirmText: t('configurator.delete'),
    cancelText: t('configurator.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.remove(row.id)
    toast.success(t('configurator.deleted'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    await load()
  }
}

async function restore(row: ConfiguratorRow): Promise<void> {
  try {
    await api.restore(row.id)
    toast.success(t('configurator.restored'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    await load()
  }
}

function actionsFor(row: ConfiguratorRow): RowAction[] {
  if (!canManage.value) return []

  if (inBin.value) {
    return [
      {
        key: 'restore',
        icon: 'refresh',
        label: t('configurator.restore'),
        run: () => void restore(row),
      },
    ]
  }

  return [
    { key: 'open', icon: 'edit', label: t('configurator.open'), run: () => open(row) },
    {
      key: 'delete',
      icon: 'trash',
      label: t('configurator.delete'),
      danger: true,
      run: () => void remove(row),
    },
  ]
}

/** On the site in green, edits waiting said by a chip of their own rather than a second colour. */
function badge(row: ConfiguratorRow): 'default' | 'success' | 'warning' {
  if (row.status === 'published' || row.status === 'modified') return 'success'

  return row.status === 'unpublished' ? 'warning' : 'default'
}

const actions = computed<ScreenAction[]>(() =>
  canManage.value
    ? [
        {
          key: 'new',
          label: t('configurator.new'),
          icon: 'plus',
          primary: true,
          run: () => void add(),
        },
      ]
    : [],
)
</script>

<template>
  <wx-list-screen v-model:view="view" :title="t('module.title')" :views="views" :actions="actions">
    <wx-table
      :data="shown"
      :columns="columns"
      row-key="id"
      searchable
      :clickable="!inBin"
      :hover="!inBin"
      flush
      layout="fixed"
      :loading="loading"
      :cards-below="0"
      :search-placeholder="t('configurator.search')"
      :empty-text="emptyText"
      :aria-label="t('module.title')"
      @row-click="open"
      @state-change="onState"
    >
      <template #cell-title="{ row }">
        <div class="wx-configurators__name">
          <wx-text weight="medium" truncate>{{ row.title || t('configurator.untitled') }}</wx-text>
          <wx-text size="sm" tone="muted" mono truncate>{{ row.key }}</wx-text>
        </div>
      </template>

      <template #cell-form="{ row }">
        <wx-text v-if="row.form" size="sm" mono truncate>{{ row.form }}</wx-text>
        <wx-text v-else size="sm" tone="muted">{{ t('configurator.no-form') }}</wx-text>
      </template>

      <template #cell-steps="{ row }">
        <wx-text size="sm">{{ row.steps }}</wx-text>
      </template>

      <template #cell-updated="{ row }">
        <wx-date :value="inBin ? (row.deleted_at ?? null) : row.updated_at" />
      </template>

      <template #cell-status="{ row }">
        <span class="wx-configurators__status">
          <wx-badge :type="badge(row)" dot>{{ t(`configurator.status-${row.status}`) }}</wx-badge>
          <wx-badge v-if="row.status === 'modified'" type="primary" round>
            {{ t('configurator.edits') }}
          </wx-badge>
        </span>
      </template>

      <template #cell-actions="{ row }">
        <wx-row-menu :actions="actionsFor(row)" :label="row.title" />
      </template>
    </wx-table>
  </wx-list-screen>
</template>

<style scoped>
.wx-configurators__name {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
  min-width: 0;
}

.wx-configurators__status {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-4);
}
</style>
