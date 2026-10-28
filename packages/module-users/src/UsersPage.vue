<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter, type LocationQueryRaw } from 'vue-router'
import {
  toast,
  WxBadge,
  WxButton,
  WxDatePicker,
  WxFormItem,
  WxIcon,
  WxSelect,
  WxTable,
  WxText,
  WxTooltip,
  type DatePickerModelValue,
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
import { createUsersApi } from './api'
import { NAMESPACE, useUsersMessages } from './i18n'
import type { UserQuery, UserRow, UsersMeta, UsersPage } from './types'

/**
 * «Customers» (spec §16.2): the name and the address, the phone, the groups, the ways to sign in,
 * the registration and the last sign-in; searched by name, e-mail and phone; filtered by group,
 * a confirmed address, the way to sign in, blocked and the period of the registration. No bulk
 * actions (decision 23): the groups and the block are the card's. The filters live in the
 * address, so coming back from a card lands where the list was left.
 */
defineOptions({ name: 'WxUsersPage' })

const props = withDefaults(defineProps<{ base?: string }>(), { base: '/users' })

const admin = useAdmin()
const api = createUsersApi(admin)
const route = useRoute()
const router = useRouter()
useUsersMessages()

const t = useTranslate(NAMESPACE)
const panel = useTranslate('webx-admin')
const message = useErrorText()

const canManage = computed(() => admin.can('users.manage'))

const page = ref<UsersPage | null>(null)
const meta = ref<UsersMeta>({ groups: [], providers: [] })
const loading = ref(true)

const text = (key: string): string | null => (route.query[key] ? String(route.query[key]) : null)
const flag = (key: string): boolean | null =>
  route.query[key] === '1' ? true : route.query[key] === '0' ? false : null

const search = ref(String(route.query.q ?? ''))
const pageNumber = ref(Number(route.query.page ?? 1) || 1)
const group = ref<string | null>(text('group'))
const verified = ref<boolean | null>(flag('verified'))
const blocked = ref<boolean | null>(flag('blocked'))
const provider = ref<string | null>(text('provider'))
const from = ref<string | null>(text('from'))
const to = ref<string | null>(text('to'))
const sort = ref<string>(text('sort') ?? '-created_at')
let perPage = 25

const title = computed(
  () =>
    admin.state.manifest?.modules.find((module) => module.id === 'users')?.title ??
    t('module.customers'),
)

const yesNo = computed(() => [
  { value: '1', label: t('panel.yes') },
  { value: '0', label: t('panel.no') },
])

const verifiedOptions = computed(() => [
  { value: '1', label: t('panel.verified') },
  { value: '0', label: t('panel.unverified') },
])

const groupOptions = computed(() =>
  meta.value.groups.map((one) => ({ value: one.code, label: one.name })),
)

/** The password first, then every network of the registry — a switched-off one still filters. */
const providerOptions = computed(() => [
  { value: 'password', label: t('panel.way-password') },
  ...meta.value.providers.map((one) => ({ value: one.key, label: one.label })),
])

const sorts = computed(() => [
  { value: '-created_at', label: t('panel.sort-created') },
  { value: '-last_sign_in_at', label: t('panel.sort-last-sign-in') },
  { value: 'email', label: t('panel.sort-email') },
])

const labelOf = (options: { value: string; label: string }[], value: string | null) =>
  options.find((one) => one.value === value)?.label ?? value ?? ''

const applied = computed<AppliedFilter[]>(() => {
  const chips: AppliedFilter[] = []

  if (group.value !== null) {
    chips.push({
      key: 'group',
      label: `${t('panel.filter-group')}: ${labelOf(groupOptions.value, group.value)}`,
      clear: () => (group.value = null),
    })
  }

  if (verified.value !== null) {
    chips.push({
      key: 'verified',
      label: t(verified.value ? 'panel.verified' : 'panel.unverified'),
      clear: () => (verified.value = null),
    })
  }

  if (blocked.value !== null) {
    chips.push({
      key: 'blocked',
      label: `${t('panel.filter-blocked')}: ${t(blocked.value ? 'panel.yes' : 'panel.no')}`,
      clear: () => (blocked.value = null),
    })
  }

  if (provider.value !== null) {
    chips.push({
      key: 'provider',
      label: `${t('panel.filter-provider')}: ${labelOf(providerOptions.value, provider.value)}`,
      clear: () => (provider.value = null),
    })
  }

  if (from.value !== null) {
    chips.push({
      key: 'from',
      label: `${t('panel.filter-from')}: ${from.value}`,
      clear: () => (from.value = null),
    })
  }

  if (to.value !== null) {
    chips.push({
      key: 'to',
      label: `${t('panel.filter-to')}: ${to.value}`,
      clear: () => (to.value = null),
    })
  }

  return chips
})

function clearFilters(): void {
  group.value = null
  verified.value = null
  blocked.value = null
  provider.value = null
  from.value = null
  to.value = null
}

function query(state?: TableState): UserQuery {
  return {
    search: state?.search ?? search.value,
    group: group.value ?? undefined,
    verified: verified.value ?? undefined,
    blocked: blocked.value ?? undefined,
    provider: provider.value ?? undefined,
    from: from.value ?? undefined,
    to: to.value ?? undefined,
    sort: sort.value,
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

  if (q.search) wanted.q = q.search
  if (q.page && q.page > 1) wanted.page = String(q.page)
  if (q.sort && q.sort !== '-created_at') wanted.sort = q.sort

  for (const key of ['group', 'provider', 'from', 'to'] as const) {
    if (q[key]) wanted[key] = q[key]
  }

  for (const key of ['verified', 'blocked'] as const) {
    if (q[key] !== undefined) wanted[key] = q[key] ? '1' : '0'
  }

  void router.replace({ query: wanted })
}

function onState(state: TableState): void {
  perPage = state.perPage
  void load(state)
}

watch([group, verified, blocked, provider, from, to, sort], () => {
  pageNumber.value = 1
  remember()
  void load()
})

watch([search, pageNumber], remember)

const columns = computed<TableColumn<UserRow>[]>(() => [
  { key: 'name', label: t('panel.column-name'), minWidth: 240 },
  { key: 'phone', label: t('panel.column-phone'), width: 170, hideBelow: 900 },
  { key: 'groups', label: t('panel.column-groups'), minWidth: 140, hideBelow: 720 },
  { key: 'ways', label: t('panel.column-ways'), width: 150, hideBelow: 1040 },
  { key: 'created_at', label: t('panel.column-created'), width: 130, hideBelow: 1180 },
  { key: 'last_sign_in_at', label: t('panel.column-last-sign-in'), width: 140, hideBelow: 560 },
  { key: 'actions', label: '', width: rowMenuWidth, align: 'right' },
])

const nameOf = (row: UserRow) => row.name || row.email

/** «Google», «Password» — what the registry calls a way, its key where it does not know it. */
function wayLabel(key: string): string {
  if (key === 'password') return t('panel.way-password')

  return meta.value.providers.find((one) => one.key === key)?.label ?? key
}

function open(row: UserRow): void {
  void router.push(`${props.base}/${row.id}`)
}

function actionsFor(row: UserRow): RowAction[] {
  return [{ key: 'open', icon: 'edit', label: t('panel.open'), run: () => open(row) }]
}

const actions = computed<ScreenAction[]>(() =>
  canManage.value
    ? [
        {
          key: 'new',
          label: t('panel.new'),
          icon: 'plus',
          primary: true,
          run: () => void router.push(`${props.base}/new`),
        },
      ]
    : [],
)

const emptyText = computed(() =>
  applied.value.length > 0 || search.value ? t('panel.nothing-found') : t('panel.empty'),
)

const day = (value: DatePickerModelValue): string | null =>
  typeof value === 'string' && value !== '' ? value.slice(0, 10) : null

onMounted(async () => {
  try {
    meta.value = await api.meta()
  } catch {
    // The list works without its filters' choices; the error of the list itself says enough.
  }
})
</script>

<template>
  <div class="wx-users">
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
          <wx-form-item :label="t('panel.sort')">
            <wx-select v-model="sort" :options="sorts" size="sm" />
          </wx-form-item>
          <wx-form-item :label="t('panel.filter-group')">
            <wx-select
              v-model="group"
              :options="groupOptions"
              :placeholder="t('panel.any')"
              :empty-text="panel('filters.nothing-found')"
              filterable
              clearable
              size="sm"
            />
          </wx-form-item>
          <wx-form-item :label="t('panel.filter-verified')">
            <wx-select
              :model-value="verified === null ? null : verified ? '1' : '0'"
              :options="verifiedOptions"
              :placeholder="t('panel.any')"
              clearable
              size="sm"
              @update:model-value="
                (value: unknown) => (verified = value == null ? null : value === '1')
              "
            />
          </wx-form-item>
          <wx-form-item :label="t('panel.filter-provider')">
            <wx-select
              v-model="provider"
              :options="providerOptions"
              :placeholder="t('panel.any')"
              clearable
              size="sm"
            />
          </wx-form-item>
          <wx-form-item :label="t('panel.filter-blocked')">
            <wx-select
              :model-value="blocked === null ? null : blocked ? '1' : '0'"
              :options="yesNo"
              :placeholder="t('panel.any')"
              clearable
              size="sm"
              @update:model-value="
                (value: unknown) => (blocked = value == null ? null : value === '1')
              "
            />
          </wx-form-item>
          <wx-form-item :label="t('panel.filter-from')">
            <wx-date-picker
              :model-value="from"
              clearable
              size="sm"
              @update:model-value="(value: DatePickerModelValue) => (from = day(value))"
            />
          </wx-form-item>
          <wx-form-item :label="t('panel.filter-to')">
            <wx-date-picker
              :model-value="to"
              clearable
              size="sm"
              @update:model-value="(value: DatePickerModelValue) => (to = day(value))"
            />
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
          <div class="wx-users__name">
            <span class="wx-users__title">
              <span class="wx-users__text" :class="{ 'is-muted': !row.name }">
                {{ row.name ?? t('panel.no-name') }}
              </span>
              <wx-badge v-if="row.blocked" type="danger" size="sm">{{
                t('panel.blocked')
              }}</wx-badge>
            </span>
            <span class="wx-users__email">
              <span class="wx-users__text">{{ row.email }}</span>
              <wx-tooltip v-if="!row.verified" :content="t('panel.unverified')">
                <wx-icon class="wx-users__unverified" name="warning" size="sm" />
              </wx-tooltip>
            </span>
          </div>
        </template>

        <template #cell-phone="{ row }">
          <wx-text size="sm" :tone="row.phone ? undefined : 'muted'">{{
            row.phone ?? '—'
          }}</wx-text>
        </template>

        <template #cell-groups="{ row }">
          <div class="wx-users__groups">
            <wx-badge v-for="one in row.groups" :key="one.code" size="sm" variant="soft">
              {{ one.name }}
            </wx-badge>
          </div>
        </template>

        <template #cell-ways="{ row }">
          <div class="wx-users__ways">
            <wx-tooltip
              v-for="way in row.ways"
              :key="way"
              :content="row.ways_off.includes(way) ? t('panel.way-off') : wayLabel(way)"
            >
              <wx-badge
                size="sm"
                variant="outline"
                :type="row.ways_off.includes(way) ? 'default' : 'primary'"
                :class="{ 'is-off': row.ways_off.includes(way) }"
              >
                {{ wayLabel(way) }}
              </wx-badge>
            </wx-tooltip>
          </div>
        </template>

        <template #cell-created_at="{ row }">
          <wx-date :value="row.created_at" compact />
        </template>

        <template #cell-last_sign_in_at="{ row }">
          <wx-date v-if="row.last_sign_in_at" :value="row.last_sign_in_at" compact />
          <wx-text v-else size="sm" tone="muted">{{ t('panel.never') }}</wx-text>
        </template>

        <template #cell-actions="{ row }">
          <wx-row-menu :actions="actionsFor(row)" :label="nameOf(row)" />
        </template>
      </wx-table>
    </wx-list-screen>
  </div>
</template>

<style scoped>
.wx-users {
  min-width: 0;
}

.wx-users__name {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.wx-users__title,
.wx-users__email {
  display: flex;
  align-items: center;
  gap: var(--wx-space-6);
  min-width: 0;
}

.wx-users__text {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-users__text.is-muted {
  color: var(--wx-text-muted);
}

.wx-users__email {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.wx-users__unverified {
  flex: none;
  color: var(--wx-color-warning);
}

.wx-users__groups,
.wx-users__ways {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-4);
}

.wx-users__ways .is-off {
  text-decoration: line-through;
}
</style>
