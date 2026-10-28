<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { confirm, createModal, toast, WxTable, WxText, type TableColumn } from '@webx-ui/core'
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
import { useRouter } from 'vue-router'
import { createUsersApi } from './api'
import GroupDialog from './GroupDialog.vue'
import { NAMESPACE, useUsersMessages } from './i18n'
import type { GroupRow } from './types'

/**
 * «Customers → Groups» (spec §16.2, decision 24): the name, the code and how many customers each
 * holds — by name, no drag, no pages. A group is written in a dialog; deleting one says how many
 * customers it holds and is never refused (§13.1).
 */
defineOptions({ name: 'WxUsersGroupsPage' })

const props = withDefaults(defineProps<{ base?: string }>(), { base: '/users' })

const admin = useAdmin()
const api = createUsersApi(admin)
const router = useRouter()
useUsersMessages()

const t = useTranslate(NAMESPACE)
const panel = useTranslate('webx-admin')
const message = useErrorText()

const edit = createModal<GroupRow | null, { group: GroupRow | null }>(GroupDialog)

const rows = ref<GroupRow[]>([])
const loading = ref(true)
const canManage = computed(() => admin.can('users.manage'))

const title = computed(
  () =>
    admin.state.manifest?.modules.find((module) => module.id === 'users-groups')?.title ??
    t('module.groups'),
)

const columns = computed<TableColumn<GroupRow>[]>(() => [
  { key: 'name', label: t('panel.group-name'), minWidth: 200 },
  { key: 'code', label: t('panel.group-code'), width: 200, hideBelow: 560 },
  { key: 'users_count', label: t('panel.group-count'), width: 130, align: 'right' },
  { key: 'actions', label: '', width: rowMenuWidth, align: 'right' },
])

async function load(): Promise<void> {
  loading.value = true

  try {
    rows.value = await api.groups()
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

async function write(group: GroupRow | null): Promise<void> {
  if (!canManage.value) return

  const saved = await edit({ group })

  if (!saved) return

  toast.success(t(group ? 'panel.group-saved' : 'panel.group-created'))
  await load()
}

async function remove(group: GroupRow): Promise<void> {
  const agreed = await confirm({
    title: t('panel.group-delete-title', { name: group.name }),
    message: t('panel.group-delete-text', { count: group.users_count }),
    confirmText: t('panel.delete'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.removeGroup(group.id)
    toast.success(t('panel.group-deleted'))
    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

function actionsFor(group: GroupRow): RowAction[] {
  const actions: RowAction[] = [
    {
      key: 'customers',
      icon: 'users',
      label: t('module.customers'),
      run: () => void router.push({ path: props.base, query: { group: group.code } }),
    },
  ]

  if (canManage.value) {
    actions.unshift({
      key: 'edit',
      icon: 'edit',
      label: t('panel.group-edit'),
      run: () => void write(group),
    })
    actions.push({
      key: 'delete',
      icon: 'trash',
      label: t('panel.delete'),
      danger: true,
      run: () => void remove(group),
    })
  }

  return actions
}

const actions = computed<ScreenAction[]>(() =>
  canManage.value
    ? [
        {
          key: 'new',
          label: t('panel.groups-new'),
          icon: 'plus',
          primary: true,
          run: () => void write(null),
        },
      ]
    : [],
)

onMounted(load)
</script>

<template>
  <div class="wx-users-groups">
    <wx-list-screen :title="title" :actions="actions">
      <wx-table
        :loading-text="panel('filters.loading')"
        :data="rows"
        :columns="columns"
        row-key="id"
        :clickable="canManage"
        hover
        flush
        :loading="loading"
        layout="fixed"
        :empty-text="t('panel.groups-empty')"
        :aria-label="title"
        @row-click="write"
      >
        <template #cell-code="{ row }">
          <wx-text size="sm" mono>{{ row.code }}</wx-text>
        </template>

        <template #cell-users_count="{ row }">
          <span class="wx-users-groups__count">{{ row.users_count }}</span>
        </template>

        <template #cell-actions="{ row }">
          <wx-row-menu :actions="actionsFor(row)" :label="row.name" />
        </template>
      </wx-table>
    </wx-list-screen>
  </div>
</template>

<style scoped>
.wx-users-groups {
  min-width: 0;
}

.wx-users-groups__count {
  font-variant-numeric: tabular-nums;
}
</style>
