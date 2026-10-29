<script setup lang="ts">
import { computed } from 'vue'
import { WxDivider, WxText } from '@webx-ui/core'
import { useTranslate, WxDate } from '@webx-ui/module-admin'
import { useOrderCard } from './card'
import { NAMESPACE, useOrdersMessages } from './i18n'
import type { MilestoneKey } from './types'

/**
 * `wx-orders-summary` — the side card of an order (spec §11.3, the node `summary`): the totals
 * as the buyer was charged, what was given back, and the dates of the milestones. Money comes
 * written by the server in the currency of the order; nothing is counted here.
 */
defineOptions({ name: 'WxOrdersSummary' })

useOrdersMessages()

const t = useTranslate(NAMESPACE)
const card = useOrderCard()
const order = computed(() => card?.order.value ?? null)

const ORDER: MilestoneKey[] = ['placed', 'paid', 'received', 'cancelled', 'refunded']

const dates = computed(() =>
  ORDER.flatMap((key) => {
    const mark = order.value?.milestones[key]

    return mark ? [{ key, at: mark.at, reference: mark.reference }] : []
  }),
)
</script>

<template>
  <div v-if="order" class="wx-orders-summary">
    <dl class="wx-orders-summary__rows">
      <dt>{{ t('panel.subtotal') }}</dt>
      <dd>{{ order.totals.subtotal }}</dd>
      <template v-if="order.totals.adjustments">
        <dt>{{ t('panel.adjustments') }}</dt>
        <dd>{{ order.totals.adjustments }}</dd>
      </template>
      <dt class="is-strong">{{ t('panel.total') }}</dt>
      <dd class="is-strong">{{ order.totals.total }}</dd>
      <template v-if="order.totals.tenders">
        <dt>{{ t('panel.tenders') }}</dt>
        <dd>−{{ order.totals.tenders }}</dd>
        <dt class="is-strong">{{ t('panel.payable') }}</dt>
        <dd class="is-strong">{{ order.totals.payable }}</dd>
      </template>
      <template v-if="order.totals.refunded">
        <dt>{{ t('panel.refunded') }}</dt>
        <dd class="is-warning">−{{ order.totals.refunded }}</dd>
      </template>
    </dl>

    <wx-divider />

    <dl class="wx-orders-summary__rows">
      <template v-for="mark in dates" :key="mark.key">
        <dt>{{ t(`module.milestones.${mark.key}`) }}</dt>
        <dd>
          <wx-date :value="mark.at" size="sm" />
          <wx-text v-if="mark.reference" size="sm" tone="muted" class="wx-orders-summary__ref">
            {{ mark.reference }}
          </wx-text>
        </dd>
      </template>
    </dl>
  </div>
</template>

<style scoped>
.wx-orders-summary__rows {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: var(--wx-space-8) var(--wx-space-16);
  margin: 0;
}

.wx-orders-summary__rows dt {
  min-width: 0;
  color: var(--wx-text-muted);
}

.wx-orders-summary__rows dd {
  margin: 0;
  text-align: end;
  white-space: nowrap;
}

.wx-orders-summary__rows .is-strong {
  color: var(--wx-text-strong);
  font-weight: var(--wx-font-weight-semibold);
}

.wx-orders-summary__rows .is-warning {
  color: var(--wx-color-warning);
}

.wx-orders-summary__ref {
  display: block;
}
</style>
