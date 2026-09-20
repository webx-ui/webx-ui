<script setup lang="ts">
import { computed, ref } from 'vue'
import {
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
  createModal,
  localizedValue,
  toast,
  useLocales,
  WxBadge,
  WxEmpty,
  WxSortableList,
  WxText,
} from '@webx-ui/core'
import StatusDialog from './StatusDialog.vue'
import { createInboxApi } from './api'
import { useInboxMessages } from './i18n'
import type { InboxStatus } from './types'

/**
 * The states a submission can be in (§2.11).
 *
 * Rows rather than an enum, because every panel ends up wanting its own words — and the flags
 * are what the section reads, not the keys, so renaming "New" to whatever this client calls
 * it keeps it the status a new submission gets.
 *
 * Their order is the order of the tabs over the list of submissions, which is why it is
 * dragged rather than typed.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/inbox' })

const context = useAdmin()
const api = createInboxApi(context)
const locales = useLocales()
useInboxMessages()

const t = useTranslate('webx-inbox')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const statuses = ref<InboxStatus[]>([])
const loading = ref(true)

const canManage = context.can('inbox.manage')

const edit = createModal<InboxStatus, { status: InboxStatus | null }>(StatusDialog)

function name(status: InboxStatus): string {
  return localizedValue(status.title, locales.active.value, status.key)
}

async function load(): Promise<void> {
  loading.value = true

  try {
    statuses.value = await api.statuses()
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

void load()

async function open(status: InboxStatus | null): Promise<void> {
  if (await edit({ status })) await load()
}

async function remove(status: InboxStatus): Promise<void> {
  const agreed = await confirm({
    title: t('panel.delete-status-title', { status: name(status) }),
    message: t('panel.delete-status-text'),
    confirmText: t('panel.delete'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.removeStatus(status.id)
    toast.success(t('panel.deleted'))
    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

function actionsFor(status: InboxStatus): RowAction[] {
  if (!canManage) return []

  return [
    { key: 'edit', icon: 'edit', label: t('panel.settings'), run: () => void open(status) },
    // A status with submissions in them cannot go: they would have nothing to be in. The
    // line is left out rather than greyed, the way every list of the panel does it.
    ...(status.submissions_count === 0
      ? [
          {
            key: 'delete',
            icon: 'trash' as const,
            label: t('panel.delete'),
            danger: true,
            run: () => void remove(status),
          },
        ]
      : []),
  ]
}

/* What the section offers. Declared, because on a phone the head folds it into the ···. */
const actions = computed<ScreenAction[]>(() =>
  canManage
    ? [
        {
          key: 'new',
          label: t('panel.new-status'),
          icon: 'plus',
          primary: true,
          run: () => void open(null),
        },
      ]
    : [],
)

async function reorder(): Promise<void> {
  try {
    await api.sortStatuses(statuses.value.map((status) => status.id))
  } catch (error) {
    toast.danger(message(error, t('panel.reorder-failed')))
    await load()
  }
}
</script>

<template>
  <div class="wx-inbox-statuses">
    <wx-list-screen
      :title="t('panel.statuses')"
      :back="props.base"
      :back-label="t('panel.forms')"
      :actions="actions"
    >
      <wx-empty v-if="!loading && statuses.length === 0" :title="t('panel.no-statuses')" />

      <wx-sortable-list
        v-else
        v-model="statuses"
        plain
        item-key="id"
        :item-label="name"
        :disabled="!canManage"
        :aria-label="t('panel.statuses')"
        @move="reorder"
      >
        <template #default="{ item }">
          <div class="wx-inbox-status">
            <wx-badge :type="item.color">{{ name(item) }}</wx-badge>

            <wx-text size="sm" tone="muted" truncate>
              <code>{{ item.key }}</code>
              <template v-if="item.is_default"> · {{ t('panel.status-default') }}</template>
              <template v-if="item.is_spam"> · {{ t('panel.status-spam') }}</template>
              <template v-else-if="item.is_closed"> · {{ t('panel.status-closed') }}</template>
              <template v-if="item.submissions_count">
                · {{ t('panel.in-use') }} ({{ item.submissions_count }})
              </template>
            </wx-text>
          </div>
        </template>

        <template #actions="{ item }">
          <wx-row-menu :actions="actionsFor(item)" :label="name(item)" />
        </template>
      </wx-sortable-list>
    </wx-list-screen>
  </div>
</template>

<style scoped>
.wx-inbox-statuses {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--wx-space-12);
}

.wx-inbox-statuses > .wx-list-screen {
  align-self: stretch;
}

.wx-inbox-status {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  flex: 1 1 auto;
  min-width: 0;
  flex-wrap: wrap;
}
</style>
