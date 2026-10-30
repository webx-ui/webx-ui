<script setup lang="ts">
import { computed, useTemplateRef } from 'vue'
import { WxBadge, WxSkeleton, WxText, useElementWidth } from '@webx-ui/core'
import { useTranslate, WxDate } from '@webx-ui/module-admin'
import ContactLink from './ContactLink.vue'
import { NAMESPACE, useOrdersMessages } from './i18n'
import { authorOf } from './sources'
import type { MilestoneKey, OrderGlance } from './types'

/**
 * An opened row of «Orders» (decision 94): what the order holds and who it is for, without going
 * into it — the lines and under them the totals as the card's «Summary» has them, the buyer and
 * the recipient with their phones and e-mails to call or write, the buyer's comment, the pinned
 * note first and the latest of the feed, the lines of the blocks, the milestones with when. It
 * draws what it is given; the list asks the server once a row and keeps the answer for the page.
 */
defineOptions({ name: 'WxOrdersGlance' })

const props = defineProps<{
  /** The answer; null while it is on its way. */
  glance: OrderGlance | null
  /** The answer did not come. */
  failed?: boolean
}>()

useOrdersMessages()

const t = useTranslate(NAMESPACE)

/*
 * The columns by the width of the row, measured — not a container query: an element made a
 * size container inside a table cell is where a browser may size the cell before the content
 * settles, and the last line of the lines was seen cut in half.
 */
const root = useTemplateRef<HTMLElement>('root')
const width = useElementWidth(root)
const columns = computed(() =>
  width.value === 0 || width.value > 900 ? 4 : width.value > 480 ? 2 : 1,
)

const ORDER: MilestoneKey[] = [
  'placed',
  'awaiting_payment',
  'paid',
  'received',
  'cancelled',
  'refunded',
]

const milestones = computed(() =>
  ORDER.flatMap((key) => {
    const at = props.glance?.milestones[key]

    return at ? [{ key, at }] : []
  }),
)

const blocks = computed(() =>
  (props.glance?.blocks ?? []).filter((block) => block.summary !== null && block.summary !== ''),
)

const lastNote = computed(() => props.glance?.last_note ?? null)

const count = computed(() => {
  const counted = props.glance?.count

  if (!counted) return ''

  const line = t('panel.summary-count', { lines: counted.lines, units: counted.units })

  return counted.refunded_units > 0
    ? `${line} · ${t('panel.summary-units-returned', { count: counted.refunded_units })}`
    : line
})

/** What came in — «120.00 + 15.00» once a top-up came (§42.4); the sum to pay before a balance existed. */
const paid = computed(() => {
  const totals = props.glance?.totals

  if (!totals) return ''
  if (totals.paid_first && totals.paid_topups) return `${totals.paid_first} + ${totals.paid_topups}`

  return totals.paid ?? totals.payable
})

/** The mark under the totals, as the card's «Summary» puts it under «Placed». */
const source = computed(() =>
  props.glance?.source === 'quick'
    ? t('panel.source-quick')
    : props.glance?.source === 'panel'
      ? t('panel.source-panel-mark')
      : null,
)
</script>

<template>
  <div ref="root" class="wx-orders-glance">
    <wx-text v-if="failed" size="sm" tone="muted">{{ t('panel.glance-failed') }}</wx-text>

    <wx-skeleton v-else-if="!glance" :rows="3" />

    <div v-else class="wx-orders-glance__grid" :class="`is-${columns}`">
      <section class="wx-orders-glance__part">
        <h4 class="wx-orders-glance__title">{{ t('panel.glance-items') }}</h4>
        <ul class="wx-orders-glance__items">
          <li v-for="item in glance.items" :key="item.id" class="wx-orders-glance__item">
            <span class="wx-orders-glance__name">
              {{ item.name }}
              <span v-if="item.options" class="wx-orders-glance__muted">{{ item.options }}</span>
            </span>
            <span class="wx-orders-glance__qty">× {{ item.qty }}</span>
            <span class="wx-orders-glance__sum">{{ item.total }}</span>
          </li>
        </ul>
        <span class="wx-orders-glance__muted">{{ count }}</span>

        <dl class="wx-orders-glance__totals" data-test="glance-totals">
          <dt>{{ t('panel.subtotal') }}</dt>
          <dd>{{ glance.totals.subtotal }}</dd>
          <template v-if="glance.totals.adjustments">
            <dt>{{ t('panel.adjustments') }}</dt>
            <dd>{{ glance.totals.adjustments }}</dd>
          </template>
          <dt class="is-strong">{{ t('panel.total') }}</dt>
          <dd class="is-strong">{{ glance.totals.total }}</dd>
          <template v-if="glance.totals.tenders">
            <dt>{{ t('panel.tenders') }}</dt>
            <dd>−{{ glance.totals.tenders }}</dd>
            <dt class="is-strong">{{ t('panel.payable') }}</dt>
            <dd class="is-strong">{{ glance.totals.payable }}</dd>
          </template>
          <template v-if="glance.paid">
            <dt>{{ t('module.milestones.paid') }}</dt>
            <dd class="is-success">{{ paid }}</dd>
          </template>
          <template v-else-if="glance.awaiting_payment">
            <dt>{{ t('module.milestones.awaiting_payment') }}</dt>
            <dd class="is-warning">{{ glance.totals.payable }}</dd>
          </template>
          <template v-if="glance.totals.refunded">
            <dt>{{ t('panel.refunded') }}</dt>
            <dd class="is-warning">−{{ glance.totals.refunded }}</dd>
          </template>
          <template v-if="glance.totals.balance">
            <dt class="is-strong">
              {{
                t(glance.totals.balance.kind === 'over' ? 'panel.balance-over' : 'panel.balance-topup')
              }}
            </dt>
            <dd
              :class="glance.totals.balance.kind === 'over' ? 'is-danger' : 'is-warning'"
              class="is-strong"
              data-test="glance-balance"
            >
              {{ glance.totals.balance.amount }}
            </dd>
          </template>
        </dl>
        <span v-if="source" class="wx-orders-glance__source">
          <wx-badge size="sm" variant="outline">{{ source }}</wx-badge>
        </span>
      </section>

      <section class="wx-orders-glance__part">
        <h4 class="wx-orders-glance__title">{{ t('panel.glance-contacts') }}</h4>
        <span>{{ glance.customer.name }}</span>
        <span v-if="glance.customer.phone" class="wx-orders-glance__contact">
          <contact-link kind="phone" :value="glance.customer.phone" />
        </span>
        <span v-if="glance.customer.email" class="wx-orders-glance__contact">
          <contact-link kind="email" :value="glance.customer.email" />
        </span>
        <template v-if="glance.recipient">
          <span class="wx-orders-glance__gap">
            {{ t('panel.glance-recipient', { name: glance.recipient.name }) }}
          </span>
          <span v-if="glance.recipient.phone" class="wx-orders-glance__contact">
            <contact-link kind="phone" :value="glance.recipient.phone" />
          </span>
        </template>
      </section>

      <section
        v-if="glance.manager_note || lastNote || glance.comment"
        class="wx-orders-glance__part"
      >
        <h4 class="wx-orders-glance__title">{{ t('panel.glance-notes') }}</h4>
        <div v-if="glance.manager_note" class="wx-orders-glance__pinned">
          <span class="wx-orders-glance__label">{{ t('panel.pinned') }}</span>
          <p class="wx-orders-glance__text">{{ glance.manager_note }}</p>
        </div>
        <div v-if="lastNote" class="wx-orders-glance__note">
          <span class="wx-orders-glance__label">
            {{ t('panel.glance-last-note') }} ·
            {{ authorOf(t, lastNote.source, lastNote.author?.name ?? null) }} ·
            <wx-date :value="lastNote.created_at" compact size="sm" />
          </span>
          <p class="wx-orders-glance__text">{{ lastNote.body }}</p>
        </div>
        <div v-if="glance.comment" class="wx-orders-glance__note">
          <span class="wx-orders-glance__label">{{ t('module.fields.comment') }}</span>
          <p class="wx-orders-glance__text">{{ glance.comment }}</p>
        </div>
      </section>

      <section class="wx-orders-glance__part">
        <template v-if="blocks.length > 0">
          <h4 class="wx-orders-glance__title">{{ t('panel.glance-blocks') }}</h4>
          <span v-for="block in blocks" :key="block.key">{{ block.summary }}</span>
        </template>
        <h4 class="wx-orders-glance__title" :class="{ 'wx-orders-glance__gap': blocks.length > 0 }">
          {{ t('panel.glance-milestones') }}
        </h4>
        <dl class="wx-orders-glance__marks">
          <template v-for="mark in milestones" :key="mark.key">
            <dt>{{ t(`module.milestones.${mark.key}`) }}</dt>
            <dd><wx-date :value="mark.at" compact size="sm" /></dd>
          </template>
        </dl>
      </section>
    </div>
  </div>
</template>

<style scoped>
.wx-orders-glance {
  min-width: 0;
}

.wx-orders-glance__grid {
  display: grid;
  gap: var(--wx-space-16) var(--wx-space-24);
  min-width: 0;
}

.wx-orders-glance__grid.is-4 {
  grid-template-columns: minmax(0, 1.4fr) repeat(3, minmax(0, 1fr));
}

.wx-orders-glance__grid.is-2 {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.wx-orders-glance__grid.is-1 {
  grid-template-columns: minmax(0, 1fr);
}

.wx-orders-glance__part {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  min-width: 0;
  font-size: var(--wx-font-size-sm);
  line-height: var(--wx-font-line-height-normal);
  overflow-wrap: anywhere;
}

.wx-orders-glance__title {
  margin: 0;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  font-weight: var(--wx-font-weight-medium);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.wx-orders-glance__gap {
  margin-block-start: var(--wx-space-8);
}

.wx-orders-glance__items {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-orders-glance__item {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto auto;
  gap: var(--wx-space-8);
  align-items: baseline;
}

.wx-orders-glance__name {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.wx-orders-glance__qty,
.wx-orders-glance__sum {
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.wx-orders-glance__qty,
.wx-orders-glance__muted,
.wx-orders-glance__label {
  color: var(--wx-text-muted);
}

.wx-orders-glance__label {
  font-size: var(--wx-font-size-xs);
  font-weight: var(--wx-font-weight-medium);
}

.wx-orders-glance__text {
  margin: 0;
  white-space: pre-wrap;
}

/* The totals of the card's «Summary», a receipt under the lines it adds up. */
.wx-orders-glance__totals {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: var(--wx-space-2) var(--wx-space-16);
  margin: var(--wx-space-4) 0 0;
  padding-block-start: var(--wx-space-8);
  border-block-start: 1px solid var(--wx-border-default);
}

.wx-orders-glance__totals dt {
  min-width: 0;
  color: var(--wx-text-muted);
}

.wx-orders-glance__totals dd {
  margin: 0;
  text-align: end;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}

.wx-orders-glance__totals .is-strong {
  color: var(--wx-text-strong);
  font-weight: var(--wx-font-weight-semibold);
}

.wx-orders-glance__totals dd.is-success {
  color: var(--wx-color-success);
}

.wx-orders-glance__totals dd.is-warning {
  color: var(--wx-color-warning);
}

.wx-orders-glance__totals dd.is-danger {
  color: var(--wx-color-danger);
}

.wx-orders-glance__source {
  display: flex;
  justify-content: flex-end;
}

.wx-orders-glance__contact {
  min-width: 0;
}

.wx-orders-glance__pinned,
.wx-orders-glance__note {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
  min-width: 0;
}

/* The pinned note as the card draws it: the tint and the edge of a standing line. */
.wx-orders-glance__pinned {
  padding: var(--wx-space-6) var(--wx-space-10);
  border-inline-start: 3px solid var(--wx-color-warning);
  border-radius: var(--wx-radius-sm);
  background: var(--wx-color-warning-soft);
}

.wx-orders-glance__marks {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: var(--wx-space-2) var(--wx-space-12);
  margin: 0;
}

.wx-orders-glance__marks dt {
  color: var(--wx-text-muted);
}

.wx-orders-glance__marks dd {
  margin: 0;
}
</style>
