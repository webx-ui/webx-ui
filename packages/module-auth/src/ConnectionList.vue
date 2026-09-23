<script setup lang="ts">
import { computed, ref, useTemplateRef, watch } from 'vue'
import { useAdmin, useErrorText, useTranslate, WxDate } from '@webx-ui/module-admin'
import {
  confirm,
  toast,
  WxBadge,
  WxButton,
  WxEntityCard,
  WxIcon,
  WxSpace,
  WxTable,
  WxText,
  useElementWidth,
  type TableColumn,
} from '@webx-ui/core'
import { createConnectionsApi } from './connections'
import { useAuthMessages } from './i18n'
import type { Connection, ConnectionScope } from './types'

/**
 * The agents somebody let in, and the one button that ends one.
 *
 * A row is who is connected, on what terms, and when it was last heard from. The terms are
 * the part worth reading: "read only" is not a scope the client asked for, it is what the
 * person chose on the consent screen, and it outranks their own permissions.
 *
 * Ending a connection is not paged, sorted or searched, and the list is not either. A person
 * has one agent, or three; a table of them that needed a search would mean something else had
 * gone wrong.
 *
 * A connection that was ended stays in the list, greyed, because it is what the call log
 * points at — the rows an agent left behind outlive the connection that made them.
 */
const props = withDefaults(
  defineProps<{
    /** Whose connections: this person's, or everybody's (`admins.manage`). */
    scope?: ConnectionScope
  }>(),
  { scope: 'mine' },
)

/** Where a row stops being a row. */
const CARDS = 560

const admin = useAdmin()
const api = createConnectionsApi(admin)
useAuthMessages()

const t = useTranslate('webx-auth')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const root = useTemplateRef<HTMLElement>('root')
const width = useElementWidth(root)
const asCards = computed(() => width.value > 0 && width.value < CARDS)

const rows = ref<Connection[]>([])
const loading = ref(false)
/** Which one is being ended, so its own button is the one that spins. */
const ending = ref<number | null>(null)

const columns = computed<TableColumn<Connection>[]>(() => {
  if (asCards.value) return [{ key: 'card', label: '' }]

  return [
    { key: 'client', label: t('connections.client'), minWidth: 210 },
    ...(props.scope === 'all' ? [{ key: 'user', label: t('connections.who'), minWidth: 140 }] : []),
    { key: 'read_only', label: t('connections.allowed'), minWidth: 160 },
    { key: 'connected_at', label: t('connections.connected'), hideBelow: 860 },
    { key: 'last_used_at', label: t('connections.last-used'), minWidth: 130 },
    { key: 'end', label: '', align: 'right', width: 140 },
  ]
})

/** Who the agent acts as — a name, or the id alone for somebody deleted since. */
function nameOf(row: Connection): string {
  return row.user.name ?? t('calls.deleted', { id: row.user.id })
}

/**
 * What the connection may do, in words.
 *
 * "Everything you can do" rather than a list of modules, for the reason the consent screen
 * gives: the answer is the reader's own permissions, and spelling them out here would be a
 * second copy of them, wrong the day somebody's role changes.
 */
function allowed(row: Connection): string {
  if (row.read_only) return t('connections.read-only')

  if (props.scope !== 'all') return t('connections.full-you')

  // The given name: the column beside this one already says who, in full, and a badge
  // repeating it whole is what pushed the client's address onto three lines.
  const name = row.user.name?.split(' ')[0]

  return t('connections.full', { name: name || nameOf(row) })
}

async function load(): Promise<void> {
  loading.value = true

  try {
    rows.value = (await api.list(props.scope)).data
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

async function end(row: Connection): Promise<void> {
  const agreed = await confirm({
    title: t('connections.confirm-title', { client: row.client }),
    message: t('connections.confirm-text'),
    confirmText: t('connections.confirm'),
    cancelText: t('connections.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  ending.value = row.id

  try {
    const ended = await api.disconnect(row.id)

    // The row comes back rather than disappearing: what it did is still in the log.
    rows.value = rows.value.map((one) => (one.id === ended.id ? ended : one))
    toast.success(t('connections.done', { client: row.client }))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    ending.value = null
  }
}

watch(() => props.scope, load, { immediate: true })

defineExpose({ reload: load })
</script>

<template>
  <div ref="root" class="wx-connection-list">
    <wx-table
      :data="rows"
      :columns="columns"
      row-key="id"
      flush
      :loading="loading"
      :clickable="false"
      :empty-text="scope === 'all' ? t('connections.empty-all') : t('connections.empty')"
      :cards-below="CARDS"
      :row-class="(row: Connection) => (row.revoked_at ? 'is-ended' : '')"
    >
      <template #cell-client="{ row }">
        <wx-space direction="vertical" size="none">
          <wx-text :tone="row.revoked_at ? 'muted' : 'default'" weight="medium">
            {{ row.client }}
          </wx-text>
          <!-- The name is the client's own choice; the host is the part that cannot lie. -->
          <wx-text size="xs" tone="muted">{{
            t('connections.returns-to', { host: row.host })
          }}</wx-text>
        </wx-space>
      </template>

      <template #cell-user="{ row }">
        <wx-text :tone="row.user.name === null ? 'muted' : 'default'">{{ nameOf(row) }}</wx-text>
      </template>

      <template #cell-read_only="{ row }">
        <wx-badge v-if="row.revoked_at" type="default" dot>{{
          t('connections.disconnected')
        }}</wx-badge>
        <wx-badge v-else :type="row.read_only ? 'info' : 'success'" dot>{{
          allowed(row)
        }}</wx-badge>
      </template>

      <template #cell-connected_at="{ row }">
        <wx-date :value="row.connected_at" tone="muted" compact />
      </template>

      <template #cell-last_used_at="{ row }">
        <wx-date v-if="row.last_used_at" :value="row.last_used_at" tone="default" compact />
        <wx-text v-else size="sm" tone="muted">{{ t('connections.never-used') }}</wx-text>
      </template>

      <template #cell-end="{ row }">
        <wx-button
          v-if="!row.revoked_at"
          type="danger"
          variant="text"
          size="sm"
          :loading="ending === row.id"
          @click="end(row)"
        >
          {{ t('connections.disconnect') }}
        </wx-button>
      </template>

      <!-- Narrow, the client is the title and everything else is a line under it. -->
      <template #cell-card="{ row }">
        <wx-entity-card
          variant="plain"
          :title="row.client"
          :subtitle="t('connections.returns-to', { host: row.host })"
        >
          <!-- Not the first letter of the client's name, which is what the card draws for a
               title with no picture: Claude, Cursor, Codex and ChatGPT are one letter between
               them, and three grey Cs in a column read as a broken photograph. -->
          <template #media>
            <span class="wx-connection-list__glyph" aria-hidden="true"
              ><wx-icon name="link"
            /></span>
          </template>

          <template #meta>
            <wx-badge v-if="row.revoked_at" type="default" dot size="sm">{{
              t('connections.disconnected')
            }}</wx-badge>
            <wx-badge v-else :type="row.read_only ? 'info' : 'success'" dot size="sm">{{
              allowed(row)
            }}</wx-badge>
            <wx-text v-if="scope === 'all'" size="xs" tone="muted">{{ nameOf(row) }}</wx-text>
            <wx-date v-if="row.last_used_at" :value="row.last_used_at" compact />
            <wx-text v-else size="xs" tone="muted">{{ t('connections.never-used') }}</wx-text>
          </template>

          <template #actions>
            <wx-button
              v-if="!row.revoked_at"
              type="danger"
              variant="text"
              size="sm"
              :loading="ending === row.id"
              @click="end(row)"
            >
              {{ t('connections.disconnect') }}
            </wx-button>
          </template>
        </wx-entity-card>
      </template>
    </wx-table>
  </div>
</template>

<style>
.wx-connection-list {
  min-width: 0;
}

.wx-connection-list__glyph {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  color: var(--wx-text-muted);
  background: var(--wx-bg-subtle);
}

/* Ended, and still here: the log points at it, so it is greyed rather than taken away.
   Both shapes of a row, because the table draws cards below its own threshold. */
.wx-connection-list .wx-table__row.is-ended,
.wx-connection-list .wx-table__card.is-ended {
  opacity: 0.6;
}
</style>
