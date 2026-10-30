<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  confirm,
  createModal,
  toast,
  WxAlert,
  WxBadge,
  WxFormItem,
  WxIcon,
  WxSelect,
  WxSwitch,
  WxTable,
  WxText,
  type IconName,
  type TabItem,
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
import { createNotificationsApi } from './api'
import { useNotificationsMessages } from './i18n'
import NewChannelDialog from './NewChannelDialog.vue'
import type {
  ChannelRow,
  DeliveriesPage,
  DeliveryRow,
  DeliveryStatus,
  NotificationsMeta,
} from './types'

/**
 * The section (§10.1): the channels, the log of what they sent, and the bin — three views of one
 * list screen, the open one in the address (`?view=log`), so a link to the log is a link to it.
 *
 * A channel opens on a page of its own: its form is long, and the preview beside it needs the
 * width a pane next to a list would take away.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/notifications' })

const admin = useAdmin()
const api = createNotificationsApi(admin)
const route = useRoute()
const router = useRouter()
useNotificationsMessages()

const t = useTranslate('webx-notifications')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const canManage = computed(() => admin.can('notifications.manage'))

const title = computed(
  () =>
    admin.state.manifest?.modules.find((module) => module.id === 'notifications')?.title ??
    t('module.title'),
)

type View = '' | 'log' | 'bin'

const view = computed<View>({
  get: () => {
    const asked = route.query.view

    return asked === 'log' || asked === 'bin' ? asked : ''
  },
  set: (value) => {
    void router.replace({
      query: { ...route.query, view: value === '' ? undefined : value, page: undefined },
    })
  },
})

const meta = ref<NotificationsMeta | null>(null)
const channels = ref<ChannelRow[]>([])
const bin = ref<ChannelRow[]>([])
const environment = ref('production')
const loading = ref(true)

const views = computed<TabItem[]>(() => [
  { value: '', label: t('panel.channels'), badge: channels.value.length },
  { value: 'log', label: t('panel.log') },
  { value: 'bin', label: t('panel.bin'), badge: bin.value.length || undefined },
])

async function loadChannels(): Promise<void> {
  loading.value = true

  try {
    const [live, trashed] = await Promise.all([api.channels(), api.channels({ trashed: true })])

    channels.value = live.data
    bin.value = trashed.data
    environment.value = live.environment
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

void loadChannels()
api
  .meta()
  .then((found) => (meta.value = found))
  .catch(() => undefined)

const create = createModal<{ provider: string; event: string | null }, { meta: NotificationsMeta }>(
  NewChannelDialog,
)

async function add(): Promise<void> {
  if (meta.value === null) return

  const chosen = await create({ meta: meta.value })

  if (!chosen) return

  void router.push({
    path: `${props.base}/channels/new`,
    query: { provider: chosen.provider, ...(chosen.event ? { event: chosen.event } : {}) },
  })
}

function open(row: ChannelRow): void {
  void router.push(`${props.base}/channels/${row.id}`)
}

async function toggle(row: ChannelRow, enabled: boolean): Promise<void> {
  try {
    const detail = await api.save(row.id, { enabled })

    channels.value = channels.value.map((one) => (one.id === row.id ? detail.channel : one))
    toast.success(enabled ? t('panel.enabled') : t('panel.disabled'))
  } catch (error) {
    // Switching on a channel that is not complete is refused in the server's words (§3.3).
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    toast.danger(body?.errors ? Object.values(body.errors).flat().join(' ') : message(error))
  }
}

async function copy(row: ChannelRow): Promise<void> {
  try {
    const detail = await api.copy(row.id)

    toast.success(t('panel.copied', { name: detail.channel.name }))
    await loadChannels()
  } catch (error) {
    toast.danger(message(error))
  }
}

async function remove(row: ChannelRow): Promise<void> {
  const agreed = await confirm({
    title: t('panel.delete-title', { title: row.name }),
    message: t('panel.delete-text'),
    confirmText: t('panel.delete'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.remove(row.id)
    toast.success(t('panel.deleted'))
    await loadChannels()
  } catch (error) {
    toast.danger(message(error))
  }
}

async function restore(row: ChannelRow): Promise<void> {
  try {
    await api.restore(row.id)
    toast.success(t('panel.restored'))
    await loadChannels()
  } catch (error) {
    toast.danger(message(error))
  }
}

async function purge(row: ChannelRow): Promise<void> {
  const agreed = await confirm({
    title: t('panel.purge-title', { title: row.name }),
    message: t('panel.purge-text'),
    confirmText: t('panel.purge'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.purge(row.id)
    toast.success(t('panel.purged'))
    await loadChannels()
  } catch (error) {
    toast.danger(message(error))
  }
}

function actionsFor(row: ChannelRow): RowAction[] {
  if (view.value === 'bin') {
    return canManage.value
      ? [
          {
            key: 'restore',
            label: t('panel.restore'),
            icon: 'refresh',
            run: () => void restore(row),
          },
          {
            key: 'purge',
            label: t('panel.purge'),
            icon: 'trash',
            danger: true,
            run: () => void purge(row),
          },
        ]
      : []
  }

  return [
    { key: 'edit', label: t('panel.edit'), icon: 'edit', run: () => open(row) },
    ...(canManage.value
      ? [
          {
            key: 'copy',
            label: t('panel.copy'),
            icon: 'copy' as IconName,
            run: () => void copy(row),
          },
          {
            key: 'delete',
            label: t('panel.delete'),
            icon: 'trash' as IconName,
            danger: true,
            run: () => void remove(row),
          },
        ]
      : []),
  ]
}

/*
 * The name and the menu stay at every width; the event goes first, then the last delivery and the
 * count — on a phone a row is the name, the switch and the `···`.
 */
const channelColumns = computed<TableColumn<ChannelRow>[]>(() => [
  { key: 'name', label: t('module.channel'), minWidth: 160 },
  { key: 'event', label: t('panel.new-event'), width: 200, hideBelow: 720 },
  { key: 'last_delivery', label: t('panel.column-status'), width: 170, hideBelow: 560 },
  ...(view.value === 'bin'
    ? []
    : [{ key: 'enabled', label: '', width: 64, align: 'center' as const }]),
  { key: 'actions', label: '', width: rowMenuWidth, align: 'right' },
])

const rows = computed(() => (view.value === 'bin' ? bin.value : channels.value))

function iconOf(row: { icon?: string | null; provider: string }): IconName {
  return (row.icon ?? (row.provider === 'telegram' ? 'send' : 'mail')) as IconName
}

/* ---------------------------------------------------------------------------- the log ----- */

const page = ref<DeliveriesPage | null>(null)
const logLoading = ref(false)
const status = ref<DeliveryStatus | ''>('')
const channelFilter = ref<number | ''>('')
const withTests = ref(true)
const lastState = ref<TableState | undefined>(undefined)

async function loadLog(state?: TableState): Promise<void> {
  lastState.value = state ?? lastState.value
  logLoading.value = true

  try {
    page.value = await api.deliveries({
      page: lastState.value?.page ?? 1,
      per_page: lastState.value?.perPage,
      status: status.value === '' ? null : status.value,
      channel: channelFilter.value === '' ? null : channelFilter.value,
      tests: withTests.value,
    })
    environment.value = page.value.environment
  } catch (error) {
    toast.danger(message(error))
  } finally {
    logLoading.value = false
  }
}

watch(
  view,
  (next) => {
    if (next === 'log') void loadLog()
  },
  { immediate: true },
)

watch(
  [status, channelFilter, withTests],
  () => void loadLog({ ...(lastState.value ?? {}), page: 1 } as TableState),
)

const filtersCount = computed(
  () => Number(status.value !== '') + Number(channelFilter.value !== '') + Number(!withTests.value),
)

const statusOptions = computed(() => [
  { value: '', label: t('panel.all-statuses') },
  ...(['queued', 'sent', 'failed', 'skipped'] as const).map((value) => ({
    value,
    label: t(`module.statuses.${value}`),
  })),
])

const channelOptions = computed(() => [
  { value: '', label: t('panel.all-channels') },
  ...channels.value.map((one) => ({ value: one.id, label: one.name })),
])

const logColumns = computed<TableColumn<DeliveryRow>[]>(() => [
  { key: 'created_at', label: t('panel.column-when'), width: 150 },
  { key: 'recipient', label: t('panel.column-recipient'), minWidth: 160 },
  { key: 'channel_name', label: t('panel.column-channel'), width: 180, hideBelow: 760 },
  { key: 'status', label: t('panel.column-status'), width: 140 },
  { key: 'attempts', label: t('panel.column-attempts'), width: 90, align: 'right', hideBelow: 900 },
  { key: 'actions', label: '', width: rowMenuWidth, align: 'right' },
])

const tone: Record<DeliveryStatus, 'success' | 'danger' | 'warning' | 'default'> = {
  sent: 'success',
  failed: 'danger',
  queued: 'warning',
  skipped: 'default',
}

async function retry(row: DeliveryRow): Promise<void> {
  try {
    await api.retry(row.id)
    toast.success(t('panel.retried'))
    await loadLog()
  } catch (error) {
    toast.danger(message(error))
  }
}

function logActionsFor(row: DeliveryRow): RowAction[] {
  const list: RowAction[] = []

  if (row.subject_url) {
    list.push({
      key: 'open',
      label: t('panel.open-subject'),
      icon: 'external-link',
      href: row.subject_url,
      target: '_self',
    })
  }

  if (row.channel_id !== null) {
    list.push({
      key: 'channel',
      label: t('panel.edit'),
      icon: 'edit',
      run: () => void router.push(`${props.base}/channels/${row.channel_id}`),
    })
  }

  if (row.retryable && canManage.value) {
    list.push({
      key: 'retry',
      label: t('panel.retry'),
      icon: 'refresh',
      run: () => void retry(row),
    })
  }

  return list
}

function why(row: DeliveryRow): string | null {
  if (row.error) return row.error
  if (row.reason) return t(`module.reasons.${row.reason}`)

  return null
}

const actions = computed<ScreenAction[]>(() =>
  canManage.value
    ? [
        {
          key: 'new',
          label: t('panel.new'),
          icon: 'plus',
          primary: true,
          disabled: meta.value === null,
          run: () => void add(),
        },
      ]
    : [],
)
</script>

<template>
  <wx-list-screen v-model:view="view" :title="title" :views="views" :actions="actions">
    <!-- Over the rows and not over the tabs: it is about what the channels do, not about a view. -->
    <div v-if="environment !== 'production'" class="wx-notifications-list__note">
      <wx-alert type="info" variant="soft" :description="t('panel.environment', { environment })" />
    </div>

    <wx-table
      v-if="view !== 'log'"
      :data="rows"
      :columns="channelColumns"
      row-key="id"
      hover
      clickable
      flush
      layout="fixed"
      :loading="loading"
      :cards-below="0"
      :pagination="false"
      :empty-text="view === 'bin' ? t('panel.empty-bin') : t('panel.empty-channels-text')"
      :aria-label="title"
      @row-click="(row: ChannelRow) => view !== 'bin' && open(row)"
    >
      <template #cell-name="{ row }">
        <span class="wx-notifications-list__name">
          <wx-icon :name="iconOf(row)" class="wx-notifications-list__icon" />
          <span class="wx-notifications-list__text">
            <wx-text truncate weight="medium">{{ row.name }}</wx-text>
            <wx-text size="sm" tone="muted" truncate>
              {{ row.provider_label }} ·
              {{ t('panel.recipients', { count: String(row.recipients) }) }}
            </wx-text>
          </span>
        </span>
      </template>

      <template #cell-event="{ row }">
        <wx-badge v-if="!row.event_declared" type="warning">{{
          t('panel.event-undeclared')
        }}</wx-badge>
        <wx-text v-else size="sm" :tone="row.event ? 'default' : 'muted'" truncate>
          {{ row.event_label ?? t('panel.any-event') }}
        </wx-text>
      </template>

      <template #cell-last_delivery="{ row }">
        <span v-if="row.last_delivery" class="wx-notifications-list__last">
          <wx-badge :type="tone[row.last_delivery.status]" dot>
            {{ t(`module.statuses.${row.last_delivery.status}`) }}
          </wx-badge>
          <wx-date :value="row.last_delivery.at" class="wx-notifications-list__when" />
        </span>
        <wx-text v-else size="sm" tone="muted">{{ t('panel.never-sent') }}</wx-text>
      </template>

      <template #cell-enabled="{ row }">
        <span @click.stop>
          <wx-switch
            :model-value="row.enabled"
            :disabled="!canManage"
            :aria-label="t('panel.enable')"
            size="sm"
            @update:model-value="(on) => toggle(row, Boolean(on))"
          />
        </span>
      </template>

      <template #cell-actions="{ row }">
        <span @click.stop>
          <wx-row-menu :actions="actionsFor(row)" :label="row.name" />
        </span>
      </template>
    </wx-table>

    <wx-table
      v-else
      :data="page"
      :columns="logColumns"
      row-key="id"
      hover
      flush
      layout="fixed"
      expandable
      :loading="logLoading"
      :cards-below="0"
      :filters-count="filtersCount"
      :filters-label="t('panel.filters')"
      :empty-text="t('panel.log-empty')"
      :aria-label="t('panel.log')"
      @state-change="loadLog"
    >
      <template #filters>
        <wx-form-item :label="t('panel.column-status')">
          <wx-select v-model="status" :options="statusOptions" size="sm" />
        </wx-form-item>
        <wx-form-item :label="t('panel.column-channel')">
          <wx-select v-model="channelFilter" :options="channelOptions" size="sm" />
        </wx-form-item>
        <wx-switch v-model="withTests" size="sm">{{ t('panel.with-tests') }}</wx-switch>
      </template>

      <template #cell-created_at="{ row }">
        <wx-date :value="row.sent_at ?? row.created_at" />
      </template>

      <template #cell-recipient="{ row }">
        <span class="wx-notifications-list__text">
          <wx-text truncate>{{ row.recipient_label ?? row.recipient }}</wx-text>
          <wx-text size="sm" tone="muted" truncate>
            {{ row.event_label ?? row.event }}
          </wx-text>
        </span>
      </template>

      <template #cell-channel_name="{ row }">
        <wx-text size="sm" truncate :tone="row.channel_id === null ? 'muted' : 'default'">
          {{ row.channel_name }}{{ row.channel_id === null ? ` (${t('panel.channel-gone')})` : '' }}
        </wx-text>
      </template>

      <template #cell-status="{ row }">
        <span class="wx-notifications-list__last">
          <!-- Sent into a trap is not delivered: its own word and colour, the trap beside it. -->
          <wx-badge v-if="row.status === 'sent' && row.trap" type="warning" dot :title="row.trap">
            {{ t('module.statuses.trapped') }}
          </wx-badge>
          <wx-badge v-else :type="tone[row.status]" dot>{{
            t(`module.statuses.${row.status}`)
          }}</wx-badge>
          <wx-badge v-if="row.is_test">{{ t('panel.test-mark') }}</wx-badge>
          <!-- Sent by hand from a record's card (§8.6): who asked is in the hint. -->
          <wx-badge
            v-if="row.manual"
            :title="
              row.manual_by_name ? t('panel.manual-by', { name: row.manual_by_name }) : undefined
            "
          >
            {{ t('panel.manual-mark') }}
          </wx-badge>
        </span>
      </template>

      <template #cell-attempts="{ row }">
        <wx-text size="sm">{{ row.attempts }}</wx-text>
      </template>

      <template #cell-actions="{ row }">
        <wx-row-menu :actions="logActionsFor(row)" :label="row.recipient" />
      </template>

      <template #expanded="{ row }">
        <div class="wx-notifications-list__details">
          <wx-text v-if="why(row)" size="sm" :tone="row.status === 'failed' ? 'danger' : 'muted'">
            {{ why(row) }}
          </wx-text>
          <wx-text v-if="row.unknown_variables.length > 0" size="sm" tone="muted">
            {{ t('panel.unknown-variables', { names: row.unknown_variables.join(', ') }) }}
          </wx-text>
          <wx-text v-if="row.truncated" size="sm" tone="muted">{{ t('panel.cut-mark') }}</wx-text>
          <wx-text size="sm" tone="muted" mono>{{ row.recipient }}</wx-text>
        </div>
      </template>
    </wx-table>
  </wx-list-screen>
</template>

<style scoped>
.wx-notifications-list__note {
  padding: var(--wx-space-12) var(--wx-space-16) 0;
}

.wx-notifications-list__name {
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-notifications-list__icon {
  flex: none;
  color: var(--wx-text-muted);
}

.wx-notifications-list__text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.wx-notifications-list__last {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--wx-space-6);
  min-width: 0;
}

.wx-notifications-list__when {
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
}

.wx-notifications-list__details {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  padding: var(--wx-space-8) var(--wx-space-16);
}
</style>
