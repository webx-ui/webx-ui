<script setup lang="ts">
import { computed, onMounted, ref, useTemplateRef } from 'vue'
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
import { createKeysApi } from './keys'
import { useAuthMessages } from './i18n'
import type { McpKey } from './types'

/**
 * Keys for programs: who each acts as, what it may reach, until when, and the button that
 * switches one off.
 *
 * A key is a connection without a browser — a watcher of a queue, a nightly import — so the list
 * reads like the connections one beside it. What differs is the end date: a connection refreshes
 * itself, a key simply stops, and the day it does is worth seeing before a script finds out.
 *
 * A key that was revoked or ran out stays, greyed: the call log points at what it did.
 */
/** Where a row stops being a row. */
const CARDS = 560

const admin = useAdmin()
const api = createKeysApi(admin)
useAuthMessages()

const t = useTranslate('webx-auth')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const root = useTemplateRef<HTMLElement>('root')
const width = useElementWidth(root)
const asCards = computed(() => width.value > 0 && width.value < CARDS)

const rows = ref<McpKey[]>([])
const loading = ref(false)
/** Which one is being revoked, so its own button is the one that spins. */
const revoking = ref<number | null>(null)

const columns = computed<TableColumn<McpKey>[]>(() => {
  if (asCards.value) return [{ key: 'card', label: '' }]

  return [
    { key: 'name', label: t('keys.name'), minWidth: 200 },
    { key: 'user', label: t('keys.who'), minWidth: 140 },
    { key: 'scopes', label: t('keys.allowed'), minWidth: 180 },
    { key: 'expires_at', label: t('keys.expires'), minWidth: 120 },
    { key: 'last_used_at', label: t('keys.last-used'), minWidth: 120, hideBelow: 860 },
    { key: 'end', label: '', align: 'right', width: 120 },
  ]
})

/** Who the key acts as — a name, or the id alone for somebody deleted since. */
function nameOf(row: McpKey): string {
  return row.user.name ?? t('keys.deleted', { id: row.user.id })
}

/** Who issued it and when, or when alone for a key issued in a terminal. */
function issued(row: McpKey): string {
  return row.created_by?.name
    ? t('keys.issued-by', { name: row.created_by.name })
    : t('keys.issued-console')
}

/**
 * What it may do, in words: "everything" by the administrator's own name, or the scopes as
 * issued — those are the words an agent's refusal will quote, so they are kept as they are.
 */
function allowed(row: McpKey): string {
  if (row.everything) {
    return t('keys.full', { name: row.user.name?.split(' ')[0] || nameOf(row) })
  }

  return row.scopes.join(', ')
}

async function load(): Promise<void> {
  loading.value = true

  try {
    rows.value = (await api.list()).data
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

async function revoke(row: McpKey): Promise<void> {
  const agreed = await confirm({
    title: t('keys.confirm-title', { name: row.name }),
    message: t('keys.confirm-text'),
    confirmText: t('keys.confirm'),
    cancelText: t('keys.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  revoking.value = row.id

  try {
    const revoked = await api.revoke(row.id)

    rows.value = rows.value.map((one) => (one.id === revoked.id ? revoked : one))
    toast.success(t('keys.revoked-done', { name: row.name }))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    revoking.value = null
  }
}

onMounted(load)

defineExpose({ reload: load })
</script>

<template>
  <div ref="root" class="wx-key-list">
    <wx-table
      :data="rows"
      :columns="columns"
      row-key="id"
      flush
      :loading="loading"
      :clickable="false"
      :empty-text="t('keys.empty')"
      :cards-below="CARDS"
      :row-class="(row: McpKey) => (row.state === 'live' ? '' : 'is-ended')"
    >
      <template #cell-name="{ row }">
        <wx-space direction="vertical" size="none">
          <wx-text :tone="row.state === 'live' ? 'default' : 'muted'" weight="medium">
            {{ row.name }}
          </wx-text>
          <wx-text size="xs" tone="muted">
            {{ issued(row) }} · <wx-date :value="row.created_at" tone="muted" compact />
          </wx-text>
        </wx-space>
      </template>

      <template #cell-user="{ row }">
        <wx-text :tone="row.user.name === null ? 'muted' : 'default'">{{ nameOf(row) }}</wx-text>
      </template>

      <template #cell-scopes="{ row }">
        <wx-badge v-if="row.state === 'revoked'" type="default" dot>{{
          t('keys.revoked')
        }}</wx-badge>
        <wx-badge v-else-if="row.state === 'expired'" type="default" dot>{{
          t('keys.expired')
        }}</wx-badge>
        <wx-badge v-else :type="row.everything ? 'success' : 'info'" dot>{{
          allowed(row)
        }}</wx-badge>
      </template>

      <template #cell-expires_at="{ row }">
        <wx-date v-if="row.expires_at" :value="row.expires_at" compact />
      </template>

      <template #cell-last_used_at="{ row }">
        <wx-date v-if="row.last_used_at" :value="row.last_used_at" compact />
        <wx-text v-else size="sm" tone="muted">{{ t('keys.never-used') }}</wx-text>
      </template>

      <template #cell-end="{ row }">
        <wx-button
          v-if="row.state === 'live'"
          type="danger"
          variant="text"
          size="sm"
          :loading="revoking === row.id"
          @click="revoke(row)"
        >
          {{ t('keys.revoke') }}
        </wx-button>
      </template>

      <!-- Narrow, the name is the title and everything else is a line under it. -->
      <template #cell-card="{ row }">
        <wx-entity-card variant="plain" :title="row.name" :subtitle="nameOf(row)">
          <template #media>
            <span class="wx-key-list__glyph" aria-hidden="true"><wx-icon name="lock" /></span>
          </template>

          <template #meta>
            <wx-badge v-if="row.state === 'revoked'" type="default" dot size="sm">{{
              t('keys.revoked')
            }}</wx-badge>
            <wx-badge v-else-if="row.state === 'expired'" type="default" dot size="sm">{{
              t('keys.expired')
            }}</wx-badge>
            <wx-badge v-else :type="row.everything ? 'success' : 'info'" dot size="sm">{{
              allowed(row)
            }}</wx-badge>
            <wx-text v-if="row.expires_at" size="xs" tone="muted">
              {{ t('keys.until') }} <wx-date :value="row.expires_at" compact />
            </wx-text>
          </template>

          <template #actions>
            <wx-button
              v-if="row.state === 'live'"
              type="danger"
              variant="text"
              size="sm"
              :loading="revoking === row.id"
              @click="revoke(row)"
            >
              {{ t('keys.revoke') }}
            </wx-button>
          </template>
        </wx-entity-card>
      </template>
    </wx-table>
  </div>
</template>

<style>
.wx-key-list {
  min-width: 0;
}

.wx-key-list__glyph {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  color: var(--wx-text-muted);
  background: var(--wx-bg-subtle);
}

/* Revoked or run out, and still here: the log points at it. Both shapes of a row. */
.wx-key-list .wx-table__row.is-ended,
.wx-key-list .wx-table__card.is-ended {
  opacity: 0.6;
}
</style>
