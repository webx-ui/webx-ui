<script setup lang="ts">
import { computed, ref, useTemplateRef, watch } from 'vue'
import {
  useAdmin,
  useTranslate,
  WxDate,
  WxFilterChips,
  rowMenuWidth,
  WxRowMenu,
  type AppliedFilter,
  type RowAction,
} from '@webx-ui/module-admin'
import {
  WxAvatar,
  WxBadge,
  WxButton,
  WxEntityCard,
  WxFormItem,
  WxIcon,
  WxSelect,
  WxSpace,
  WxTable,
  WxText,
  useElementWidth,
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

/** Where a row stops being a row. The table and the columns read the same number. */
const CARDS = 480

const root = useTemplateRef<HTMLElement>('root')
const width = useElementWidth(root)
const asCards = computed(() => width.value > 0 && width.value < CARDS)

const t = useTranslate('webx-auth')
/* The funnel and 'reset all' are the panel's words, not this module's. `admin` is taken:
   in this file that is the panel context. */
const panel = useTranslate('webx-admin')

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

/**
 * Which of the two are on, in the reader's words.
 *
 * They stand behind the funnel now, and a shut panel says nothing about itself — a list of six
 * people showing two of them looks broken until something says why.
 */
const applied = computed<AppliedFilter[]>(() => {
  const chips: AppliedFilter[] = []

  const picked = roles.value.find((one) => one.slug === role.value)
  if (picked) {
    chips.push({
      key: 'role',
      label: `${t('admins.filter-role')}: ${picked.name}`,
      clear: () => (role.value = 'all'),
    })
  }

  if (active.value !== 'any') {
    chips.push({
      key: 'state',
      label: `${t('admins.filter-state')}: ${
        active.value === 'yes' ? t('admins.only-active') : t('admins.only-inactive')
      }`,
      clear: () => (active.value = 'any'),
    })
  }

  return chips
})

function clearFilters(): void {
  role.value = 'all'
  active.value = 'any'
}

/*
 * No widths but the one the buttons need.
 *
 * A declared width is a promise the table has to keep, and five of them add up to more than the
 * screen — so it scrolls sideways while half the row is whitespace. Left alone, the columns take
 * what their contents need. What does not fit is dropped instead, in the order it can be spared.
 */
const columns = computed<TableColumn<Admin>[]>(() => {
  if (asCards.value) return [{ key: 'card', label: '' }]

  return [
    { key: 'name', label: t('admins.name'), sortable: true },
    { key: 'email', label: t('admins.email'), sortable: true, hideBelow: 560 },
    { key: 'roles', label: t('admins.roles'), hideBelow: 760 },
    { key: 'is_active', label: t('admins.active'), align: 'center', hideBelow: 660 },
    {
      key: 'last_login_at',
      label: t('admins.last-login'),
      sortable: true,
      hideBelow: 900,
      // The first thing to go when a row becomes a card: it is a date somebody scans down a
      // column, and a card has no column to scan.
      hideOnCards: true,
    },
    {
      key: 'actions',
      label: '',
      width: rowMenuWidth,
      align: 'right',
      hidden: !props.removable,
      // A card puts them along its top instead, through the `card-actions` slot.
      hideOnCards: true,
    },
  ]
})

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

/**
 * One line, and the same `···` every other list of the panel puts a record's actions behind.
 * A bare red bin in every row says deleting somebody is the thing this list is for (§20).
 */
function actionsFor(row: Admin): RowAction[] {
  return [
    {
      key: 'delete',
      icon: 'trash',
      label: t('admins.delete'),
      danger: true,
      run: () => emit('remove', row),
    },
  ]
}

function onRow(row: Admin): void {
  if (props.picking) {
    emit('chosen', [row])

    return
  }

  emit('open', row)
}

/**
 * The listeners the table gets, rather than a handler that decides to do nothing (§13).
 *
 * Picking several is the one case where a row leads nowhere: the tick box is what chooses, and
 * a click on the row itself would have to either choose one and drop the rest or do nothing at
 * all. So it does nothing and says so — no cursor, no highlight, which is what `WxTable` reads
 * out of the missing listener. `v-bind`, not `v-on`: only that reaches it (CLAUDE.md §4).
 */
const clickable = computed(() => !(props.picking && props.multiple))

function onSelection(_keys: unknown, rows: Admin[]): void {
  selected.value = rows
  emit('selection', rows)
}

/** Reload without losing where somebody was, for a caller that changed something. */
defineExpose({ reload: () => load(last), chosen: () => selected.value })
</script>

<template>
  <div ref="root" class="wx-admin-list">
    <wx-table
      :data="page"
      :columns="columns"
      row-key="id"
      searchable
      :clickable="clickable"
      :hover="clickable"
      flush
      :loading="loading"
      :search-placeholder="t('admins.search')"
      :empty-text="t('admins.empty')"
      :selectable="picking && multiple"
      :cards-below="CARDS"
      :filters-count="applied.length"
      :filters-label="panel('filters.title')"
      @row-click="onRow"
      @state-change="load"
      @selection-change="onSelection"
    >
      <!-- Behind the funnel, and what they are set to comes back as chips beside it: two
           dropdowns standing open over six people are two controls to read before the rows. -->
      <template v-if="filters" #filters>
        <wx-form-item :label="t('admins.filter-role')">
          <wx-select v-model="role" :options="roleOptions" size="sm" />
        </wx-form-item>

        <wx-form-item :label="t('admins.filter-state')">
          <wx-select v-model="active" :options="stateOptions" size="sm" />
        </wx-form-item>

        <wx-button v-if="applied.length > 0" variant="text" size="sm" block @click="clearFilters">
          <template #icon><wx-icon name="close" /></template>
          {{ panel('filters.reset') }}
        </wx-button>
      </template>

      <template v-if="filters" #applied>
        <wx-filter-chips :filters="applied" />
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
        <wx-row-menu v-if="removable" :actions="actionsFor(row)" :label="row.name" />
      </template>

      <template #cell-actions="{ row }">
        <wx-row-menu :actions="actionsFor(row)" :label="row.name" />
      </template>

      <template #cell-last_login_at="{ row }">
        <wx-date
          :value="row.last_login_at"
          :tone="row.last_login_at ? 'default' : 'muted'"
          compact
        />
      </template>

      <!--
        Narrow, a row is one entity rather than four labelled lines: a face, a name, the address
        that identifies them and what they are allowed. `circle`, because these are people.
      -->
      <template #cell-card="{ row }">
        <wx-entity-card
          variant="plain"
          shape="circle"
          :title="row.name"
          :image="row.avatar ? avatars[row.avatar] : undefined"
          :subtitle="row.email"
        >
          <template #meta>
            <wx-badge v-if="row.is_super" type="warning" size="sm">
              {{ t('admins.super') }}
            </wx-badge>
            <wx-badge v-for="one in row.roles" :key="one.id" size="sm">{{ one.name }}</wx-badge>
            <wx-badge :type="row.is_active ? 'success' : 'default'" dot size="sm">
              {{ row.is_active ? t('admins.active') : t('admins.only-inactive') }}
            </wx-badge>
          </template>
        </wx-entity-card>
      </template>
    </wx-table>
  </div>
</template>

<style>
.wx-admin-list {
  min-width: 0;
}
</style>
