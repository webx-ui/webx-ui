<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAdmin, useTranslate } from '@webx-ui/admin'
import {
  WxAction,
  WxActions,
  WxAvatar,
  WxBadge,
  WxSelect,
  WxSpace,
  WxTable,
  WxText,
  type TableColumn,
  type TableState,
} from '@webx-ui/core'
import { createAdminsApi } from './admins'
import { useAuthMessages } from './i18n'
import type { Admin, AdminPage, Role } from './types'

/**
 * The list of administrators — the screen, and the inside of the picker.
 *
 * One component for both so that searching, filtering and paging behave the same wherever
 * somebody is looking at these people. What changes is only what a row is for: opening it, or
 * choosing it.
 */
const props = withDefaults(
  defineProps<{
    /** Rows are chosen rather than opened. */
    picking?: boolean
    /** More than one may be chosen at a time. */
    multiple?: boolean
    /** Adds the column that removes somebody. */
    removable?: boolean
    /**
     * Offers the role and state filters.
     *
     * Off by default: a panel has half a dozen administrators, and a filter over six rows is a
     * control to read and decide about where a glance would have done.
     */
    filters?: boolean
    /** Turns an avatar key into an address. The panel wires this; this package cannot. */
    resolveAvatar?: (key: string) => Promise<string | null>
  }>(),
  {
    picking: false,
    multiple: false,
    removable: false,
    filters: false,
    resolveAvatar: undefined,
  },
)

const emit = defineEmits<{
  open: [admin: Admin]
  chosen: [admins: Admin[]]
  /** Which rows are ticked, for a footer that counts them. */
  selection: [admins: Admin[]]
  remove: [admin: Admin]
}>()

const admin = useAdmin()
const api = createAdminsApi(admin)
useAuthMessages()

const t = useTranslate('webx-auth')

const page = ref<AdminPage | null>(null)
const roles = ref<Role[]>([])
const loading = ref(false)
/* A word rather than an empty string: a select reads "" as nothing chosen and shows a
   blank control where the option said "all roles". */
const role = ref('all')
const active = ref<'any' | 'yes' | 'no'>('any')
const selected = ref<Admin[]>([])
const avatars = ref<Record<string, string>>({})

let last: TableState = { page: 1, perPage: 20, sort: null, search: '' }

const roleOptions = computed(() => [
  { value: 'all', label: t('admins.all-roles') },
  ...roles.value.map((one) => ({ value: one.slug, label: one.name })),
])

const stateOptions = computed(() => [
  { value: 'any', label: t('admins.any-state') },
  { value: 'yes', label: t('admins.only-active') },
  { value: 'no', label: t('admins.only-inactive') },
])

const columns = computed<TableColumn<Admin>[]>(() => [
  { key: 'name', label: t('admins.name'), sortable: true, minWidth: 220 },
  { key: 'email', label: t('admins.email'), sortable: true, minWidth: 200 },
  { key: 'roles', label: t('admins.roles'), minWidth: 180 },
  { key: 'is_active', label: t('admins.active'), width: 120, align: 'center' },
  {
    key: 'last_login_at',
    label: t('admins.last-login'),
    sortable: true,
    width: 170,
    // The first thing to go when a row becomes a card: it is a date somebody scans down a
    // column, and a card has no column to scan.
    hideOnCards: true,
  },
  {
    key: 'actions',
    label: '',
    width: 64,
    align: 'center',
    hidden: !props.removable,
    // A card puts them along its top instead, through the `card-actions` slot.
    hideOnCards: true,
  },
])

void api.roles().then((all) => (roles.value = all))

watch([role, active], () => load(last))

async function load(state: TableState): Promise<void> {
  last = state
  loading.value = true

  try {
    page.value = await api.list({
      q: state.search,
      role: role.value === 'all' ? null : role.value,
      active: active.value === 'any' ? null : active.value,
      sort: state.sort ? `${state.sort.order === 'desc' ? '-' : ''}${state.sort.key}` : 'name',
      page: state.page,
      per_page: state.perPage,
    })

    void resolveAvatars()
  } finally {
    loading.value = false
  }
}

/**
 * A row stores the library's key, not an address, and this package has no business knowing how
 * to turn one into the other. The panel hands that in; without it a row shows initials, which
 * is what it would have shown anyway for everybody who never uploaded a photograph.
 */
async function resolveAvatars(): Promise<void> {
  const resolve = props.resolveAvatar

  if (!resolve) return

  for (const row of page.value?.data ?? []) {
    if (!row.avatar || avatars.value[row.avatar]) continue

    const url = await resolve(row.avatar)

    if (url) avatars.value = { ...avatars.value, [row.avatar]: url }
  }
}

function onRow(row: Admin): void {
  if (!props.picking) {
    emit('open', row)

    return
  }

  if (!props.multiple) {
    emit('chosen', [row])
  }
}

function onSelection(_keys: unknown, rows: Admin[]): void {
  selected.value = rows
  emit('selection', rows)
}

/** Reload without losing where somebody was, for a caller that changed something. */
defineExpose({ reload: () => load(last), chosen: () => selected.value })
</script>

<template>
  <div class="wx-admin-list">
    <wx-table
      :data="page"
      :columns="columns"
      row-key="id"
      searchable
      hover
      :loading="loading"
      :search-placeholder="t('admins.search')"
      :empty-text="t('admins.empty')"
      :selectable="picking && multiple"
      @state-change="load"
      @row-click="onRow"
      @selection-change="onSelection"
    >
      <template v-if="filters" #actions>
        <wx-space size="sm">
          <wx-select v-model="role" :options="roleOptions" size="sm" style="width: 180px" />
          <wx-select v-model="active" :options="stateOptions" size="sm" style="width: 180px" />
        </wx-space>
      </template>

      <template #cell-name="{ row }">
        <wx-space size="sm" align="center">
          <wx-avatar
            :name="row.name"
            :src="row.avatar ? avatars[row.avatar] : undefined"
            size="sm"
          />
          <span>{{ row.name }}</span>
          <wx-badge v-if="row.is_super" type="warning">{{ t('admins.super') }}</wx-badge>
        </wx-space>
      </template>

      <template #cell-roles="{ row }">
        <wx-space v-if="row.roles.length > 0" size="xs" wrap>
          <wx-badge v-for="one in row.roles" :key="one.id">{{ one.name }}</wx-badge>
        </wx-space>
        <wx-text v-else size="sm" tone="muted">{{ t('admins.no-roles') }}</wx-text>
      </template>

      <template #cell-is_active="{ row }">
        <wx-badge :type="row.is_active ? 'success' : 'default'" dot>
          {{ row.is_active ? t('admins.active') : t('admins.only-inactive') }}
        </wx-badge>
      </template>

      <template #card-actions="{ row }">
        <wx-actions v-if="removable" size="sm" @click.stop>
          <wx-action type="remove" :title="t('admins.delete')" @click="emit('remove', row)" />
        </wx-actions>
      </template>

      <template #cell-actions="{ row }">
        <!-- `.stop`: the row opens the form, and removing somebody is not opening them. -->
        <wx-actions size="sm" @click.stop>
          <wx-action type="remove" :title="t('admins.delete')" @click="emit('remove', row)" />
        </wx-actions>
      </template>

      <template #cell-last_login_at="{ row }">
        <wx-text size="sm" :tone="row.last_login_at ? 'default' : 'muted'">
          {{ row.last_login_at ? new Date(row.last_login_at).toLocaleString() : t('admins.never') }}
        </wx-text>
      </template>
    </wx-table>
  </div>
</template>

<style>
.wx-admin-list {
  min-width: 0;
}
</style>
