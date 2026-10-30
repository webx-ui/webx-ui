<script setup lang="ts">
import { computed } from 'vue'
import { WxBadge, WxButton, WxCard, WxIcon, WxText, WxTooltip } from '@webx-ui/core'
import { useDates, useTranslate } from '@webx-ui/module-admin'
import { useOrderCard } from './card'
import { NAMESPACE, useOrdersMessages } from './i18n'
import { toneOf } from './tones'
import type { BadgeType } from '@webx-ui/core'
import type { MilestoneKey } from './types'

/**
 * `wx-orders-status` — the strip under the card's head (spec §11.3): the internal status with a
 * pencil that changes it — the same dialog as «Change status» of the head — what the buyer sees
 * beside it, the milestones the order has. Its public id is
 * `header-actions`: a neighbour adds its own button here with a patch, and it is drawn at the end
 * of the strip — «Track the parcel» of the delivery, «Check the payment» of the gateway.
 */
defineOptions({ name: 'WxOrdersStatus' })

useOrdersMessages()

const t = useTranslate(NAMESPACE)
const dates = useDates()
const card = useOrderCard()
const order = computed(() => card?.order.value ?? null)

/** The facts in the order of a life, with the tone each is read in. */
const MARKS: { key: MilestoneKey; tone: BadgeType }[] = [
  { key: 'paid', tone: 'success' },
  { key: 'received', tone: 'success' },
  { key: 'cancelled', tone: 'danger' },
  { key: 'refunded', tone: 'warning' },
]

const marks = computed(() =>
  MARKS.filter((mark) => order.value?.milestones[mark.key]).map((mark) => ({
    ...mark,
    at: order.value?.milestones[mark.key]?.at ?? null,
  })),
)
</script>

<template>
  <wx-card v-if="order" class="wx-orders-status">
    <div class="wx-orders-status__row">
      <div class="wx-orders-status__current">
        <wx-text size="sm" tone="muted">{{ t('panel.status') }}</wx-text>
        <span class="wx-orders-status__line">
          <wx-badge :type="toneOf(order.status?.color)" dot>
            {{ order.status?.name ?? '—' }}
          </wx-badge>
          <wx-tooltip v-if="order.status?.deleted" :content="t('panel.status-deleted')">
            <wx-icon class="wx-orders-status__warn" name="warning" size="sm" />
          </wx-tooltip>
          <wx-tooltip v-if="order.actions.status" :content="t('panel.status-change')">
            <wx-button
              class="wx-orders-status__change"
              size="sm"
              variant="text"
              icon="edit"
              :aria-label="t('panel.status-change')"
              :disabled="card?.busy.value"
              data-test="status-change"
              @click="card?.changeStatus()"
            />
          </wx-tooltip>
        </span>
      </div>

      <div class="wx-orders-status__current">
        <wx-text size="sm" tone="muted">{{ t('panel.buyer-sees') }}</wx-text>
        <wx-text weight="medium">{{ order.status?.public?.name ?? '—' }}</wx-text>
      </div>

      <div v-if="marks.length > 0 || order.awaiting_payment" class="wx-orders-status__marks">
        <wx-badge v-if="order.awaiting_payment" type="warning" variant="outline" size="sm">
          {{ t('module.milestones.awaiting_payment') }}
        </wx-badge>
        <wx-tooltip v-if="order.payment_failed_at" :content="dates.short(order.payment_failed_at)">
          <wx-badge type="danger" variant="outline" size="sm">
            {{ t('panel.payment-failed') }}
          </wx-badge>
        </wx-tooltip>
        <wx-tooltip v-for="mark in marks" :key="mark.key" :content="dates.short(mark.at)">
          <wx-badge :type="mark.tone" size="sm">
            {{ t(`module.milestones.${mark.key}`) }}
          </wx-badge>
        </wx-tooltip>
      </div>

      <div v-if="$slots.default" class="wx-orders-status__extra">
        <slot />
      </div>
    </div>
  </wx-card>
</template>

<style scoped>
.wx-orders-status__row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: var(--wx-space-12) var(--wx-space-24);
}

.wx-orders-status__current {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  min-width: 0;
}

.wx-orders-status__line {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-6);
  min-height: 32px;
}

.wx-orders-status__current > :last-child:not(.wx-orders-status__line) {
  display: flex;
  align-items: center;
  min-height: 32px;
}

.wx-orders-status__warn {
  color: var(--wx-color-warning);
}

.wx-orders-status__marks {
  /* Beside the values, not the labels above them. */
  align-self: flex-end;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-6);
  min-height: 32px;
}

.wx-orders-status__extra {
  align-self: flex-end;
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
  margin-inline-start: auto;
}
</style>
