<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import {
  rowMenuWidth,
  useAdmin,
  useErrorText,
  useTranslate,
  WxListScreen,
  WxRowMenu,
  type RowAction,
  type ScreenAction,
} from '@webx-ui/module-admin'
import {
  confirm,
  toast,
  WxBadge,
  WxIcon,
  WxLink,
  WxTable,
  WxText,
  type TableColumn,
} from '@webx-ui/core'
import { createExchangeApi, type ExchangeProfile } from './exchange'
import { useCatalogMessages } from './i18n'

/**
 * The saved profiles of the exchange (§8.1): a name, a direction, a format, and what a file of
 * that kind looks like — so the supplier's price list of every Monday is one step, not three.
 * A row opens the form; an import profile also starts the wizard with itself filled in.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/catalog' })

const context = useAdmin()
const api = createExchangeApi(context)
const router = useRouter()
useCatalogMessages()

const t = useTranslate('webx-catalog')
const message = useErrorText()

const rows = ref<ExchangeProfile[] | null>(null)
const canManage = computed(() => context.can('catalog.manage'))

async function load(): Promise<void> {
  try {
    rows.value = await api.profiles()
  } catch (error) {
    toast.danger(message(error))
    rows.value = []
  }
}

onMounted(load)

const columns = computed<TableColumn<ExchangeProfile>[]>(() => [
  { key: 'name', label: t('panel.exchange-profile-name'), minWidth: 200 },
  { key: 'direction', label: t('panel.exchange-profile-direction'), width: 150, hideBelow: 560 },
  { key: 'format', label: t('panel.exchange-format'), width: 100, hideBelow: 480 },
  { key: 'last_run_id', label: t('panel.exchange-profile-last-run'), width: 150, hideBelow: 720 },
  { key: 'actions', label: '', width: rowMenuWidth, align: 'right' },
])

const actions = computed<ScreenAction[]>(() =>
  canManage.value
    ? [
        {
          key: 'new-import',
          label: t('panel.exchange-new-import-profile'),
          icon: 'plus',
          primary: true,
          run: () =>
            void router.push({
              path: `${props.base}/exchange/profiles/new`,
              query: { direction: 'import' },
            }),
        },
        {
          key: 'new-export',
          label: t('panel.exchange-new-export-profile'),
          icon: 'plus',
          run: () =>
            void router.push({
              path: `${props.base}/exchange/profiles/new`,
              query: { direction: 'export' },
            }),
        },
      ]
    : [],
)

function edit(row: ExchangeProfile): void {
  void router.push(`${props.base}/exchange/profiles/${row.id}`)
}

async function remove(row: ExchangeProfile): Promise<void> {
  const sure = await confirm({
    title: t('panel.exchange-profile-delete-title', { name: row.name }),
    message: t('panel.exchange-profile-delete-text'),
    confirmText: t('panel.exchange-delete'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!sure) return

  try {
    await api.removeProfile(row.id)
    toast.success(t('panel.exchange-profile-deleted'))
    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

function actionsFor(row: ExchangeProfile): RowAction[] {
  const list: RowAction[] = []

  if (row.direction === 'import' && canManage.value) {
    list.push({
      key: 'run',
      icon: 'upload',
      label: t('panel.exchange-profile-run'),
      run: () =>
        void router.push({
          path: `${props.base}/exchange/import`,
          query: { profile: String(row.id) },
        }),
    })
  }

  list.push({
    key: 'edit',
    icon: 'edit',
    label: t('panel.exchange-profile-edit'),
    run: () => edit(row),
  })

  if (canManage.value) {
    list.push({
      key: 'delete',
      icon: 'trash',
      label: t('panel.exchange-delete'),
      danger: true,
      run: () => void remove(row),
    })
  }

  return list
}
</script>

<template>
  <div class="wx-catalog-profiles">
    <wx-list-screen
      :title="t('panel.exchange-profiles-title')"
      :subtitle="t('panel.exchange-profiles-help')"
      :back="`${props.base}/exchange`"
      :back-label="t('panel.exchange-title')"
      :actions="actions"
    >
      <wx-table
        :data="rows ?? []"
        :columns="columns"
        row-key="id"
        flush
        layout="fixed"
        :loading="rows === null"
        :empty-text="t('panel.exchange-profiles-empty')"
        :aria-label="t('panel.exchange-profiles-title')"
        @row-click="edit"
      >
        <template #cell-name="{ row }">
          <span class="wx-catalog-profiles__name">{{ row.name }}</span>
        </template>
        <template #cell-direction="{ row }">
          <span class="wx-catalog-profiles__direction">
            <wx-icon :name="row.direction === 'import' ? 'upload' : 'download'" size="sm" />
            {{ t(`panel.exchange-${row.direction}`) }}
          </span>
        </template>
        <template #cell-format="{ row }">
          <wx-badge size="sm">{{ row.format.toUpperCase() }}</wx-badge>
        </template>
        <template #cell-last_run_id="{ row }">
          <wx-link
            v-if="row.last_run_id !== null"
            :as="RouterLink"
            :to="{ path: `${props.base}/exchange`, query: { run: String(row.last_run_id) } }"
            @click.stop
          >
            #{{ row.last_run_id }}
          </wx-link>
          <wx-text v-else size="sm" tone="muted">—</wx-text>
        </template>
        <template #cell-actions="{ row }">
          <wx-row-menu :actions="actionsFor(row)" :label="row.name" />
        </template>
      </wx-table>
    </wx-list-screen>
  </div>
</template>

<style scoped>
.wx-catalog-profiles {
  min-width: 0;
}

.wx-catalog-profiles__name {
  display: block;
  font-weight: var(--wx-font-weight-medium);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-catalog-profiles__direction {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-6);
}
</style>
