<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { WxBadge, WxEmpty, WxPagination, WxSkeleton, WxText } from '@webx-ui/core'
import { useAdmin, useHistorySubject, useTranslate, WxDate } from '@webx-ui/module-admin'
import { createOrdersApi } from './api'
import { NAMESPACE, useOrdersMessages } from './i18n'
import { toneOf } from './tones'
import type { OrderRow } from './types'

/**
 * `wx-orders-list` — the orders of one visitor (spec §13.2), the tab «Orders» the module patches
 * onto the visitor's card `users.form`. The card does not know the module: the visitor's id is
 * the one the card gives its history, and the rows are the list's own answer with `user` set.
 * A row opens the order's card.
 */
defineOptions({ name: 'WxOrdersList' })

const props = withDefaults(
  defineProps<{
    /** What the id of the card is: `user` — the visitor. */
    filter?: 'user'
  }>(),
  { filter: 'user' },
)

useOrdersMessages()

const t = useTranslate(NAMESPACE)
const admin = useAdmin()
const api = createOrdersApi(admin)
const router = useRouter()
const subject = useHistorySubject()

const PER_PAGE = 10

const rows = ref<OrderRow[]>([])
const total = ref(0)
const page = ref(1)
const perPage = ref(PER_PAGE)
const loading = ref(false)
const failed = ref(false)

const id = computed(() => {
  const value = Number(subject?.id.value)

  return Number.isInteger(value) && value > 0 ? value : null
})

async function load(): Promise<void> {
  if (id.value === null) return

  loading.value = true
  failed.value = false

  try {
    const answer = await api.list({
      [props.filter]: id.value,
      page: page.value,
      per_page: perPage.value,
    })
    rows.value = answer.data
    total.value = answer.total
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
}

watch([id, page, perPage], () => void load(), { immediate: true })

function open(row: OrderRow): void {
  void router.push({ name: 'webx.orders.show', params: { id: row.id } })
}
</script>

<template>
  <div v-if="id !== null" class="wx-orders-list-node">
    <wx-skeleton v-if="loading && rows.length === 0" :rows="3" />
    <wx-empty v-else-if="rows.length === 0" :description="t('panel.visitor-orders-empty')" />
    <template v-else>
      <ul class="wx-orders-list-node__rows">
        <li v-for="row in rows" :key="row.id">
          <button type="button" class="wx-orders-list-node__row" @click="open(row)">
            <span class="wx-orders-list-node__number">
              {{ t('panel.order-title', { number: row.number ?? `#${row.id}` }) }}
            </span>
            <span class="wx-orders-list-node__amount">{{ row.payable }}</span>
            <wx-text size="sm" tone="muted"><wx-date :value="row.placed_at" size="sm" /></wx-text>
            <span class="wx-orders-list-node__status">
              <wx-badge v-if="row.status" :type="toneOf(row.status.color)" dot size="sm">
                {{ row.status.name }}
              </wx-badge>
            </span>
          </button>
        </li>
      </ul>
      <wx-text size="sm" tone="muted">{{
        t('panel.visitor-orders-count', { count: total })
      }}</wx-text>
      <wx-pagination
        v-if="total > PER_PAGE"
        v-model:page="page"
        v-model:per-page="perPage"
        :total="total"
        size="sm"
      />
    </template>
    <wx-text v-if="failed" size="sm" tone="danger">{{ t('panel.visitor-orders-failed') }}</wx-text>
  </div>
</template>

<style scoped>
.wx-orders-list-node {
  display: grid;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-orders-list-node__rows {
  display: grid;
  gap: var(--wx-space-6);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-orders-list-node__row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: var(--wx-space-4) var(--wx-space-12);
  align-items: center;
  width: 100%;
  padding: var(--wx-space-8) var(--wx-space-12);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-surface);
  color: var(--wx-text-default);
  font: inherit;
  text-align: start;
  cursor: pointer;
}

.wx-orders-list-node__row:hover {
  border-color: var(--wx-color-primary);
}

.wx-orders-list-node__number {
  color: var(--wx-text-strong);
  font-weight: var(--wx-font-weight-semibold);
}

.wx-orders-list-node__amount {
  white-space: nowrap;
}

.wx-orders-list-node__status {
  justify-self: end;
}
</style>
