<script setup lang="ts">
import { computed } from 'vue'
import { WxBadge, WxButton, WxDivider, WxText } from '@webx-ui/core'
import { useTranslate, WxDate } from '@webx-ui/module-admin'
import { useOrderCard } from './card'
import { NAMESPACE, useOrdersMessages } from './i18n'
import type { MilestoneKey } from './types'

/**
 * `wx-orders-summary` — the side card of an order (spec §11.3, the node `summary`): how many
 * lines and units, the totals as the buyer was charged, what was given back and why, and the
 * dates of the milestones — the reason of a cancellation under its date. A refund recorded by
 * mistake is reversed from its plate (decision 98) and stays there struck through, with why.
 * What came in, the top-ups apart, and the balance when it says more than «to pay» (§42.4): a
 * plate «Top-up due» with «Record a top-up», or «Give back».
 * Money comes written by the server in the currency of the order; nothing is counted here.
 */
defineOptions({ name: 'WxOrdersSummary' })

useOrdersMessages()

const t = useTranslate(NAMESPACE)
const card = useOrderCard()
const order = computed(() => card?.order.value ?? null)

const ORDER: MilestoneKey[] = ['placed', 'paid', 'received', 'cancelled', 'refunded']

/**
 * Where an order came from, when not through the checkout: «Buy in one click» (§29) — the manager
 * calls back and fills in the delivery and the payment — or taken by a manager in the panel (§29.2).
 * A line of its own under «Placed»: beside the date there is no room for it.
 */
const source = computed(() => {
  const placed = order.value?.milestones.placed?.source

  return placed === 'quick'
    ? t('panel.source-quick')
    : placed === 'panel'
      ? t('panel.source-panel-mark')
      : null
})

const count = computed(() => {
  const counted = order.value?.count

  if (!counted) return null

  const line = t('panel.summary-count', { lines: counted.lines, units: counted.units })

  return counted.refunded_units > 0
    ? `${line} · ${t('panel.summary-units-returned', { count: counted.refunded_units })}`
    : line
})

const reversible = computed(() => Boolean(order.value?.actions.refund_reverse))

/** «120.00 + 15.00» once a top-up came; the whole otherwise. */
const paid = computed(() => {
  const totals = order.value?.totals

  if (!totals?.paid) return null

  return totals.paid_first && totals.paid_topups
    ? `${totals.paid_first} + ${totals.paid_topups}`
    : totals.paid
})

const balance = computed(() => order.value?.totals.balance ?? null)

/**
 * The refunds that say something — a reason, the number of the operation, a reversal — or that
 * the reader may reverse.
 */
const refunds = computed(() =>
  (order.value?.refunds ?? []).filter(
    (refund) => refund.reason || refund.reference || refund.reversed || reversible.value,
  ),
)

/** The amount on the plate when the row's total does not say it: more than one, or reversed. */
const amounts = computed(
  () => (order.value?.refunds.length ?? 0) > 1 || refunds.value.some((one) => one.reversed),
)

/**
 * The marks with their dates. The reference is the paid and the received one's — a refund's is
 * under «Refunded» of the totals — and the reason is the cancellation's.
 */
const dates = computed(() =>
  ORDER.flatMap((key) => {
    const mark = order.value?.milestones[key]

    return mark
      ? [
          {
            key,
            at: mark.at,
            reference: key === 'paid' || key === 'received' ? mark.reference : null,
            reason: key === 'cancelled' ? mark.note : null,
          },
        ]
      : []
  }),
)
</script>

<template>
  <div v-if="order" class="wx-orders-summary">
    <wx-text v-if="count" size="sm" tone="muted" class="wx-orders-summary__count">
      {{ count }}
    </wx-text>

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
      <template v-if="order.totals.refunded || order.refunds.length > 0">
        <dt>{{ t('panel.refunded') }}</dt>
        <dd :class="{ 'is-warning': order.totals.refunded }">
          {{ order.totals.refunded ? `−${order.totals.refunded}` : '—' }}
        </dd>
        <dd
          v-for="refund in refunds"
          :key="refund.id"
          class="wx-orders-summary__note"
          :class="
            refund.reversed
              ? 'wx-orders-summary__note--muted is-reversed'
              : 'wx-orders-summary__note--warning'
          "
          data-test="refund-note"
        >
          <span v-if="amounts" class="wx-orders-summary__sum">
            <s v-if="refund.reversed">−{{ refund.amount }}</s>
            <template v-else>−{{ refund.amount }}</template>
          </span>
          <p v-if="refund.reason" class="wx-orders-summary__struck">
            <span class="wx-orders-summary__label">{{ t('panel.summary-reason') }}</span>
            {{ refund.reason }}
          </p>
          <p v-if="refund.reference" class="wx-orders-summary__struck">
            <span class="wx-orders-summary__label">{{ t('panel.summary-reference') }}</span>
            {{ refund.reference }}
          </p>
          <p v-if="refund.reversed" data-test="refund-reversed">
            <span class="wx-orders-summary__label">{{ t('panel.summary-reversed') }}</span>
            {{ refund.reversed.reason }}
          </p>
          <wx-button
            v-else-if="reversible"
            class="wx-orders-summary__reverse"
            size="sm"
            variant="text"
            :disabled="card?.busy.value"
            data-test="refund-reverse"
            @click="card?.reverseRefund(refund.id)"
          >
            {{ t('panel.refund-reverse') }}
          </wx-button>
        </dd>
      </template>
      <template v-if="paid">
        <dt>{{ t('panel.summary-paid') }}</dt>
        <dd data-test="summary-paid">{{ paid }}</dd>
      </template>
      <dd
        v-if="balance"
        class="wx-orders-summary__note wx-orders-summary__balance"
        :class="
          balance.kind === 'over'
            ? 'wx-orders-summary__note--danger'
            : 'wx-orders-summary__note--warning'
        "
        data-test="summary-balance"
      >
        <span class="wx-orders-summary__balance-line">
          <span class="wx-orders-summary__balance-label">
            {{ t(balance.kind === 'over' ? 'panel.balance-over' : 'panel.balance-topup') }}
          </span>
          <span class="wx-orders-summary__balance-amount">{{ balance.amount }}</span>
        </span>
        <wx-button
          v-if="balance.kind === 'topup' && order.actions.payment"
          class="wx-orders-summary__reverse"
          size="sm"
          variant="text"
          :disabled="card?.busy.value"
          data-test="record-payment"
          @click="card?.recordPayment()"
        >
          {{ t('panel.payment-record') }}
        </wx-button>
      </dd>
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
        <dd v-if="mark.key === 'placed' && source" class="wx-orders-summary__source">
          <wx-badge size="sm" variant="outline">{{ source }}</wx-badge>
        </dd>
        <dd
          v-if="mark.reason"
          class="wx-orders-summary__note wx-orders-summary__note--danger"
          data-test="cancel-reason"
        >
          <p>
            <span class="wx-orders-summary__label">{{ t('panel.summary-reason') }}</span>
            {{ mark.reason }}
          </p>
        </dd>
      </template>
    </dl>
  </div>
</template>

<style scoped>
.wx-orders-summary__count {
  display: block;
  margin-block-end: var(--wx-space-12);
}

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

.wx-orders-summary__rows dd.is-warning {
  color: var(--wx-color-warning);
}

.wx-orders-summary__ref {
  display: block;
}

/* Lines of their own, across both columns: under the date, not squeezed beside it. */
.wx-orders-summary__rows .wx-orders-summary__source {
  grid-column: 1 / -1;
  display: flex;
  justify-content: flex-end;
  margin-block-start: calc(var(--wx-space-4) * -1);
}

/* The reason told apart from the rows: a tinted plate with the colour of the mark at its edge. */
.wx-orders-summary__rows .wx-orders-summary__note {
  grid-column: 1 / -1;
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  min-width: 0;
  padding: var(--wx-space-8) var(--wx-space-12);
  border-inline-start: 3px solid var(--wx-orders-summary-edge);
  border-radius: var(--wx-radius-sm);
  background: var(--wx-orders-summary-tint);
  color: var(--wx-text-default);
  font-size: var(--wx-font-size-sm);
  text-align: start;
  white-space: normal;
  overflow-wrap: anywhere;
}

.wx-orders-summary__note--danger {
  --wx-orders-summary-edge: var(--wx-color-danger);
  --wx-orders-summary-tint: var(--wx-color-danger-soft);
}

.wx-orders-summary__note--warning {
  --wx-orders-summary-edge: var(--wx-color-warning);
  --wx-orders-summary-tint: var(--wx-color-warning-soft);
}

/* A record reversed by mistake: no longer money given back — grey, its words struck through. */
.wx-orders-summary__note--muted {
  --wx-orders-summary-edge: var(--wx-border-strong);
  --wx-orders-summary-tint: var(--wx-bg-subtle);
}

.wx-orders-summary__note.is-reversed .wx-orders-summary__struck {
  color: var(--wx-text-muted);
  text-decoration: line-through;
}

.wx-orders-summary__balance-line {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: var(--wx-space-4) var(--wx-space-12);
}

.wx-orders-summary__balance-label,
.wx-orders-summary__balance-amount {
  color: var(--wx-text-strong);
  font-weight: var(--wx-font-weight-semibold);
}

.wx-orders-summary__balance-amount {
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.wx-orders-summary__reverse {
  align-self: flex-start;
}

.wx-orders-summary__note p {
  margin: 0;
}

.wx-orders-summary__label,
.wx-orders-summary__sum {
  color: var(--wx-text-muted);
  font-weight: var(--wx-font-weight-medium);
}
</style>
